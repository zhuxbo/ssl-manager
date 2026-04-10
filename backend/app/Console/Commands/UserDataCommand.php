<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserData\UserDataExporter;
use App\Services\UserData\UserDataImporter;
use App\Services\UserData\UserDataPurger;
use App\Services\UserData\UserDataTableRegistry;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class UserDataCommand extends Command
{
    protected $signature = 'user:data
        {action : 操作类型 (export|import|purge)}
        {user_id : 用户ID}
        {--force : 跳过交互确认}
        {--chunk-size=1000 : 分批处理大小}
        {--file= : import 时指定文件路径}
        {--dry-run : import 时仅检测冲突，不实际写入}';

    protected $description = '用户数据管理（导出/导入/清理）';

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $userId = $this->argument('user_id');
        $chunkSize = (int) $this->option('chunk-size');

        // 验证参数
        $validator = Validator::make(
            ['action' => $action, 'user_id' => $userId, 'chunk_size' => $chunkSize],
            [
                'action' => 'required|in:export,import,purge',
                'user_id' => 'required|integer|min:1',
                'chunk_size' => 'required|integer|min:100|max:10000',
            ],
            [
                'action.in' => '操作类型必须是 export、import 或 purge',
                'user_id.integer' => '用户ID必须是整数',
                'user_id.min' => '用户ID必须大于0',
                'chunk_size.min' => '分批大小不能小于100',
                'chunk_size.max' => '分批大小不能大于10000',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return CommandAlias::FAILURE;
        }

        $userId = (int) $userId;

        // 查找用户（import 时用户可能不存在）
        $user = User::find($userId);

        if (! $user && $action !== 'import') {
            $this->error("用户 ID '$userId' 不存在");

            return CommandAlias::FAILURE;
        }

        return match ($action) {
            'export' => $this->handleExport($user, $chunkSize),
            'import' => $this->handleImport($userId, $user),
            'purge' => $this->handlePurge($user, $chunkSize),
        };
    }

    /**
     * 处理导出
     */
    private function handleExport(User $user, int $chunkSize): int
    {
        $this->showUserInfo($user);

        if (! $this->option('force') && ! $this->confirm("确定要导出用户 '$user->username' (ID: $user->id) 的数据吗？")) {
            $this->info('操作已取消');

            return CommandAlias::SUCCESS;
        }

        try {
            $exporter = new UserDataExporter($this->output, $chunkSize);
            $exporter->export($user);

            return CommandAlias::SUCCESS;
        } catch (Exception $e) {
            $this->error("导出失败：{$e->getMessage()}");

            return CommandAlias::FAILURE;
        }
    }

    /**
     * 处理导入
     */
    private function handleImport(int $userId, ?User $user): int
    {
        $filePath = $this->option('file');
        $dryRun = $this->option('dry-run');

        // 确定导入文件
        if (! $filePath) {
            $files = UserDataImporter::findExportFiles($userId);

            if (empty($files)) {
                $this->error("未找到用户 $userId 的导出文件");
                $this->line('请使用 --file 选项指定文件路径，或先执行导出：');
                $this->line("  php artisan user:data export $userId");

                return CommandAlias::FAILURE;
            }

            if (count($files) === 1) {
                $filePath = $files[0];
                $this->info("找到导出文件：$filePath");
            } else {
                $filePath = $this->choice(
                    '找到多个导出文件，请选择',
                    $files,
                    count($files) - 1 // 默认选最新的
                );
            }
        }

        try {
            $importer = new UserDataImporter($this->output);

            if ($dryRun) {
                $this->info('干跑模式：仅检测冲突，不实际写入');
                $importer->dryRun($filePath, $userId);

                return CommandAlias::SUCCESS;
            }

            // 实际导入确认
            if ($user) {
                $this->warn("用户 $userId 已存在，导入时冲突记录将被跳过");
            }

            if (! $this->option('force') && ! $this->confirm('确定要执行导入吗？')) {
                $this->info('操作已取消');

                return CommandAlias::SUCCESS;
            }

            $importer->import($filePath, $userId);

            return CommandAlias::SUCCESS;
        } catch (Exception $e) {
            $this->error("导入失败：{$e->getMessage()}");

            return CommandAlias::FAILURE;
        }
    }

    /**
     * 处理清理
     *
     * @throws Throwable
     */
    private function handlePurge(User $user, int $chunkSize): int
    {
        // 前置条件（不可跳过）
        if ($user->status !== 0) {
            $this->error("用户未禁用（当前 status={$user->status}），请先禁用用户再执行清理");

            return CommandAlias::FAILURE;
        }

        $exportFiles = UserDataImporter::findExportFiles($user->id);
        if (empty($exportFiles)) {
            $this->error("未找到用户 {$user->id} 的导出文件，请先执行导出：");
            $this->line("  php artisan user:data export {$user->id}");

            return CommandAlias::FAILURE;
        }

        $latestFile = end($exportFiles);
        $this->info("找到导出文件：{$latestFile}（".date('Y-m-d H:i:s', filemtime($latestFile)).'）');

        // 显示用户信息和统计
        $this->showUserInfo($user);
        $this->showStatistics($user);

        // 交互确认
        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('警告：此操作将彻底删除用户的所有数据，且无法恢复！');

            if (! $this->confirm("确定要删除用户 '$user->username' (ID: $user->id) 的所有数据吗？")) {
                $this->info('操作已取消');

                return CommandAlias::SUCCESS;
            }

            // 密码验证
            $this->warn('为确保安全，请输入该用户的登录密码进行验证：');
            $password = $this->secret('用户密码');

            if (empty($password) || ! Hash::check($password, $user->password)) {
                $this->error('密码验证失败，操作已取消');

                return CommandAlias::FAILURE;
            }

            $this->info('密码验证通过');

            if (! $this->confirm('请再次确认，真的要删除吗？')) {
                $this->info('操作已取消');

                return CommandAlias::SUCCESS;
            }
        }

        // 开始清理
        $this->info("\n开始清理用户数据...");

        try {
            $purger = new UserDataPurger($this->output, $chunkSize, $latestFile);
            $purger->purge($user);

            $this->newLine();
            $this->info("用户 '$user->username' (ID: $user->id) 的所有数据已成功清理");

            return CommandAlias::SUCCESS;
        } catch (Exception $e) {
            $this->error("\n清理失败：{$e->getMessage()}");
            $this->error("错误位置：{$e->getFile()}:{$e->getLine()}");

            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return CommandAlias::FAILURE;
        }
    }

    /**
     * 显示用户信息
     */
    private function showUserInfo(User $user): void
    {
        $this->table(
            ['字段', '值'],
            [
                ['用户ID', $user->id],
                ['用户名', $user->username],
                ['邮箱', $user->email ?? '无'],
                ['手机', $user->mobile ?? '无'],
                ['状态', $user->status === 0 ? '禁用' : '启用'],
                ['创建时间', $user->created_at],
            ]
        );
    }

    /**
     * 显示数据统计
     */
    private function showStatistics(User $user): void
    {
        $this->info("\n关联数据统计：");
        $stats = UserDataTableRegistry::getStatistics($user);
        $this->table(['数据类型', '数量'], $stats);
    }
}
