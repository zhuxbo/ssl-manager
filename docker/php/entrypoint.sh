#!/usr/bin/env bash
# 后端容器启动初始化：依赖安装 → .env 配置（指向容器内 mysql/redis）→ 密钥 → 等库 → 迁移 → 首次 seed → 启动
set -euo pipefail

cd /var/www

# .env 内键值的幂等写入：存在则替换、注释则解注释、缺失则追加
set_env() {
    ENV_KEY="$1" ENV_VALUE="$2" ENV_FILE="${3:-.env}" php -r '
        $key = getenv("ENV_KEY");
        $value = str_replace(["\\", "\"", "$", "\n", "\r"], ["\\\\", "\\\"", "\\$", "\\n", "\\r"], getenv("ENV_VALUE"));
        $line = $key."=\"".$value."\"";
        $file = getenv("ENV_FILE");
        $text = file_get_contents($file);
        $text = preg_replace_callback("/^(?:# *)?".preg_quote($key, "/")."=.*$/m", fn () => $line, $text, -1, $count);
        file_put_contents($file, $count ? $text : rtrim($text).PHP_EOL.$line.PHP_EOL);
    '
}

echo "==> [1/6] 准备 .env"
[ -f .env ] || cp .env.example .env

echo "==> [2/6] 安装 Composer 依赖（首次较慢，之后走宿主 vendor 缓存）"
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

echo "==> [3/6] 写入开发配置（数据库支持 DEV_DB_* 覆盖）"
set_env APP_ENV local
set_env APP_DEBUG true
set_env DB_CONNECTION mysql
set_env DB_HOST "${DEV_DB_HOST:-mysql}"
set_env DB_PORT "${DEV_DB_PORT:-3306}"
set_env DB_DATABASE ssl_manager
set_env DB_USERNAME "${DEV_DB_USERNAME:-root}"
set_env DB_PASSWORD "${DEV_DB_PASSWORD:-password}"
set_env REDIS_HOST redis
set_env REDIS_PORT 6379

# 两种模式均同步测试连接，避免切换模式后测试仍连接上一个数据库主机。
[ -f .env.testing ] || cp .env .env.testing
set_env DB_CONNECTION mysql .env.testing
set_env DB_HOST "${DEV_DB_HOST:-mysql}" .env.testing
set_env DB_PORT "${DEV_DB_PORT:-3306}" .env.testing
set_env DB_USERNAME "${DEV_DB_USERNAME:-root}" .env.testing
set_env DB_PASSWORD "${DEV_DB_PASSWORD:-password}" .env.testing
set_env DB_DATABASE ssl_manager_test .env.testing
php artisan config:clear

echo "==> [4/6] 生成密钥（仅当缺失）"
grep -qE "^APP_KEY=.+" .env || php artisan key:generate --force
grep -qE "^JWT_SECRET=.+" .env || php artisan jwt:secret --force

# DEV_DB_* 只用于启动初始化，不覆盖 make test 的 DB_DATABASE 或隔离测试连接。
export DEV_DB_HOST="${DEV_DB_HOST:-mysql}" DEV_DB_PORT="${DEV_DB_PORT:-3306}"
export DEV_DB_USERNAME="${DEV_DB_USERNAME:-root}" DEV_DB_PASSWORD="${DEV_DB_PASSWORD:-password}"
echo "==> [5/6] 等待 MySQL 就绪"
until php -r "new PDO('mysql:host='.getenv('DEV_DB_HOST').';port='.getenv('DEV_DB_PORT'), getenv('DEV_DB_USERNAME'), getenv('DEV_DB_PASSWORD'));" 2>/dev/null; do
    sleep 1
done

# 按 MySQL 版本选择 collation（与 bt-install.sh / 生产一致）：8.0+→0900_ai_ci，5.7→unicode_520_ci，MariaDB→unicode_ci
DB_COLLATION=$(php -r "
\$p = new PDO('mysql:host='.getenv('DEV_DB_HOST').';port='.getenv('DEV_DB_PORT'), getenv('DEV_DB_USERNAME'), getenv('DEV_DB_PASSWORD'));
\$v = \$p->query('SELECT VERSION()')->fetchColumn();
if (stripos(\$v, 'mariadb') !== false) { echo 'utf8mb4_unicode_ci'; }
elseif (version_compare(preg_replace('/[^0-9.].*/', '', \$v), '8.0', '>=')) { echo 'utf8mb4_0900_ai_ci'; }
else { echo 'utf8mb4_unicode_520_ci'; }
" 2>/dev/null || echo 'utf8mb4_unicode_ci')
set_env DB_COLLATION "$DB_COLLATION"
set_env DB_COLLATION "$DB_COLLATION" .env.testing
echo "==> DB_COLLATION=${DB_COLLATION}（按 MySQL 版本自动选择）"

# 测试 base 库（make test 注入 DB_DATABASE=ssl_manager_test，并行 worker 在其上派生 _test_<token>）
php -r "(new PDO('mysql:host='.getenv('DEV_DB_HOST').';port='.getenv('DEV_DB_PORT'), getenv('DEV_DB_USERNAME'), getenv('DEV_DB_PASSWORD')))->exec('CREATE DATABASE IF NOT EXISTS ssl_manager_test CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION}');"

echo "==> [6/6] 迁移数据库"
php artisan migrate --force

# 首次初始化：种子数据（管理员 admin / 123456、会员等级、设置、通知模板）
if [ ! -f storage/app/.docker-initialized ]; then
    echo "==> 首次初始化：db:seed"
    php artisan db:seed --force
    php artisan storage:link 2>/dev/null || true
    touch storage/app/.docker-initialized
fi

php artisan config:clear || true

echo "==> 后端就绪：容器内 0.0.0.0:8000 → 宿主 http://localhost:5300"
exec "$@"
