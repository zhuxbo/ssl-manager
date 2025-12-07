<?php

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class PurgeUserCommand extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'user:purge {user_id : 用户ID} {--force : 强制删除，跳过确认} {--chunk-size=1000 : 分批处理大小}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '彻底清理用户的所有数据（包括订单、证书、联系人、组织等）';

    /**
     * 执行命令
     *
     * @throws Throwable
     */
    public function handle(): int
    {
        $userId = $this->argument('user_id');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk-size');

        // 验证参数
        $validator = Validator::make(
            [
                'user_id' => $userId,
                'chunk_size' => $chunkSize,
            ],
            [
                'user_id' => 'required|integer|min:1',
                'chunk_size' => 'required|integer|min:100|max:10000',
            ],
            [
                'user_id.required' => '用户ID不能为空',
                'user_id.integer' => '用户ID必须是整数',
                'user_id.min' => '用户ID必须大于0',
                'chunk_size.required' => '分批大小不能为空',
                'chunk_size.integer' => '分批大小必须是整数',
                'chunk_size.min' => '分批大小不能小于100',
                'chunk_size.max' => '分批大小不能大于10000',
            ]
        );

        if ($validator->fails()) {
            $this->error('参数验证失败：');
            foreach ($validator->errors()->all() as $error) {
                $this->line("  - $error");
            }

            return CommandAlias::FAILURE;
        }

        // 查找用户
        $user = User::find($userId);
        if (! $user) {
            $this->error("用户 ID '$userId' 不存在");

            return CommandAlias::FAILURE;
        }

        // 显示用户信息
        $this->warn('即将删除以下用户的所有数据：');
        $this->table(
            ['字段', '值'],
            [
                ['用户ID', $user->id],
                ['用户名', $user->username],
                ['邮箱', $user->email],
                ['手机', $user->phone ?? '无'],
                ['创建时间', $user->created_at],
            ]
        );

        // 统计关联数据
        $this->info("\n关联数据统计：");
        $stats = $this->getDataStatistics($user);
        $this->table(
            ['数据类型', '数量'],
            $stats
        );

        // 确认删除
        if (! $force) {
            $this->newLine();
            $this->warn('警告：此操作将彻底删除用户的所有数据，且无法恢复！');

            if (! $this->confirm("确定要删除用户 '$user->username' (ID: $user->id) 的所有数据吗？")) {
                $this->info('操作已取消');

                return CommandAlias::SUCCESS;
            }

            // 验证用户密码
            $this->newLine();
            $this->warn('为确保安全，请输入该用户的登录密码进行验证：');
            $password = $this->secret('用户密码');

            if (empty($password)) {
                $this->error('密码不能为空');
                $this->info('操作已取消');

                return CommandAlias::FAILURE;
            }

            if (! Hash::check($password, $user->password)) {
                $this->error('密码验证失败，操作已取消');

                return CommandAlias::FAILURE;
            }

            $this->info('✓ 密码验证通过');

            // 二次确认
            $this->newLine();
            if (! $this->confirm('请再次确认，真的要删除吗？')) {
                $this->info('操作已取消');

                return CommandAlias::SUCCESS;
            }
        }

        // 开始删除
        $this->info("\n开始清理用户数据...");
        $this->info("分批大小：$chunkSize 条/批");

        try {
            // 按顺序删除关联数据（分批处理，不使用事务，避免长时间锁表）
            $this->deleteUserData($user, $chunkSize);

            // 最后删除用户本身
            $this->info('删除用户账户...');
            DB::transaction(function () use ($user) {
                $user->delete();
            });

            $this->newLine();
            $this->info("✓ 用户 '$user->username' (ID: $user->id) 的所有数据已成功清理");

            return CommandAlias::SUCCESS;
        } catch (Exception $e) {
            $this->error("\n删除失败：{$e->getMessage()}");
            $this->error("错误位置：{$e->getFile()}:{$e->getLine()}");

            if ($this->output->isVerbose()) {
                $this->error("\n堆栈跟踪：");
                $this->line($e->getTraceAsString());
            }

            return CommandAlias::FAILURE;
        }
    }

    /**
     * 获取用户关联数据统计
     */
    private function getDataStatistics(User $user): array
    {
        $stats = [
            ['订单', $user->orders()->count()],
            ['证书', $user->certs()->count()],
            ['联系人', $user->contacts()->count()],
            ['组织', $user->organizations()->count()],
            ['API令牌', $user->apiTokens()->count()],
            ['回调配置', $user->callbacks()->count()],
            ['CNAME委托', $user->cnameDelegations()->count()],
            ['充值记录', $user->funds()->count()],
            ['交易记录', $user->transactions()->count()],
            ['发票', $user->invoices()->count()],
            ['发票额度限制', $user->invoiceLimits()->count()],
            ['通知', $user->notifications()->count()],
        ];

        // 添加其他表的统计（没有模型关联的表）
        $stats[] = ['第三方订单', DB::table('agisos')->where('user_id', $user->id)->count()];

        // 任务表（订单任务）
        $taskCount = DB::table('tasks')
            ->whereIn('order_id', function ($subQuery) use ($user) {
                $subQuery->select('id')
                    ->from('orders')
                    ->where('user_id', $user->id);
            })
            ->count();
        $stats[] = ['任务', $taskCount];

        // 日志表
        if (DB::getSchemaBuilder()->hasTable('user_logs')) {
            $stats[] = ['用户日志', DB::table('user_logs')->where('user_id', $user->id)->count()];
        }

        if (DB::getSchemaBuilder()->hasTable('api_logs')) {
            $stats[] = ['API日志', DB::table('api_logs')->where('user_id', $user->id)->count()];
        }

        return $stats;
    }

    /**
     * 删除用户相关数据（分批处理）
     *
     * @throws Throwable
     */
    private function deleteUserData(User $user, int $chunkSize): void
    {
        // 1. 删除通知（多态关联，直接使用 SQL）
        $this->deleteNotifications($user, $chunkSize);

        // 2. 删除证书（通过订单关联，直接使用 SQL）
        $this->deleteCertsByUser($user, $chunkSize);

        // 3. 删除订单（直接使用 SQL）
        $this->deleteTableDataInChunks('orders', $user->id, '订单', $chunkSize);

        // 4. 删除联系人（直接使用 SQL）
        $this->deleteTableDataInChunks('contacts', $user->id, '联系人', $chunkSize);

        // 5. 删除组织（直接使用 SQL）
        $this->deleteTableDataInChunks('organizations', $user->id, '组织', $chunkSize);

        // 6. 删除API令牌（直接使用 SQL）
        $this->deleteTableDataInChunks('api_tokens', $user->id, 'API令牌', $chunkSize);

        // 7. 删除充值记录（直接使用 SQL，大数据量更快）
        $this->deleteTableDataInChunks('funds', $user->id, '充值记录', $chunkSize);

        // 8. 删除交易记录（直接使用 SQL，大数据量更快）
        $this->deleteTableDataInChunks('transactions', $user->id, '交易记录', $chunkSize);

        // 9. 删除发票（直接使用 SQL，大数据量更快）
        $this->deleteTableDataInChunks('invoices', $user->id, '发票', $chunkSize);

        // 10. 删除发票额度限制（直接使用 SQL，大数据量更快）
        $this->deleteTableDataInChunks('invoice_limits', $user->id, '发票额度限制', $chunkSize);

        // 11. 删除 CNAME 委托（直接使用 SQL）
        $this->deleteTableDataInChunks('cname_delegations', $user->id, 'CNAME委托', $chunkSize);

        // 12. 删除回调配置（直接使用 SQL）
        $this->deleteTableDataInChunks('callbacks', $user->id, '回调配置', $chunkSize);

        // 13. 删除第三方订单（agisos）
        $this->deleteTableDataInChunks('agisos', $user->id, '阿奇索订单', $chunkSize);

        // 14. 删除任务（通过用户ID和订单IDs）
        $this->deleteUserTasks($user, $chunkSize);

        // 15. 删除用户日志（直接通过数据库删除）
        $this->deleteTableDataInChunks('user_logs', $user->id, '用户日志', $chunkSize);

        // 16. 删除 API 日志
        $this->deleteTableDataInChunks('api_logs', $user->id, 'API日志', $chunkSize);

        // 17. 清理 JWT 刷新令牌表（如果存在）
        if (DB::getSchemaBuilder()->hasTable('user_refresh_tokens')) {
            $this->deleteTableDataInChunks('user_refresh_tokens', $user->id, 'JWT刷新令牌', $chunkSize);
        }
    }

    /**
     * 分批删除表数据（直接通过数据库）
     *
     * @param  string  $table  表名
     * @param  mixed  $value  user_id 的值
     * @param  string  $name  数据类型名称
     * @param  int  $chunkSize  分批大小
     * @param  string  $connection  数据库连接名称（默认为 default）
     *
     * @throws Throwable
     */
    private function deleteTableDataInChunks(string $table, mixed $value, string $name, int $chunkSize, string $connection = 'default'): void
    {
        $db = $connection === 'default' ? DB::connection() : DB::connection($connection);

        if (! $db->getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $count = $db->table($table)->where('user_id', $value)->count();
        if ($count === 0) {
            return;
        }

        $this->info("删除$name ($count 条)...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $totalDeleted = 0;
        $maxIterations = ceil($count / $chunkSize) + 10; // 最大循环次数，防止死循环
        $iteration = 0;

        do {
            $iteration++;

            // 防止死循环
            if ($iteration > $maxIterations) {
                $remaining = $db->table($table)->where('user_id', $value)->count();
                $this->error("\n警告：删除循环超过预期次数！还剩 $remaining 条数据未删除");
                break;
            }

            $deleted = $db->transaction(function () use ($db, $table, $value, $chunkSize) {
                return $db->table($table)
                    ->where('user_id', $value)
                    ->limit($chunkSize)
                    ->delete();
            });

            $totalDeleted += $deleted;
            $bar->setProgress(min($totalDeleted, $count)); // 确保不超过总数

            // 释放内存
            gc_collect_cycles();
        } while ($deleted > 0);

        $bar->finish();
        $this->newLine();

        // 验证是否全部删除
        $remaining = $db->table($table)->where('user_id', $value)->count();
        if ($remaining > 0) {
            $this->warn("警告：{$name}还有 $remaining 条数据未删除");
        }
    }

    /**
     * 删除用户的证书（通过订单关联）
     *
     *
     * @throws Throwable
     */
    private function deleteCertsByUser(User $user, int $chunkSize): void
    {
        if (! DB::getSchemaBuilder()->hasTable('certs')) {
            return;
        }

        // 使用子查询避免大量订单ID导致IN子句过长
        $count = DB::table('certs')
            ->whereIn('order_id', function ($query) use ($user) {
                $query->select('id')
                    ->from('orders')
                    ->where('user_id', $user->id);
            })
            ->count();

        if ($count === 0) {
            return;
        }

        $this->info("删除证书 ($count 个)...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $totalDeleted = 0;
        $maxIterations = ceil($count / $chunkSize) + 10;
        $iteration = 0;

        do {
            $iteration++;

            if ($iteration > $maxIterations) {
                $remaining = DB::table('certs')
                    ->whereIn('order_id', function ($query) use ($user) {
                        $query->select('id')
                            ->from('orders')
                            ->where('user_id', $user->id);
                    })
                    ->count();
                $this->error("\n警告：证书删除循环超过预期次数！还剩 $remaining 条数据未删除");
                break;
            }

            $deleted = DB::transaction(function () use ($user, $chunkSize) {
                return DB::table('certs')
                    ->whereIn('order_id', function ($query) use ($user) {
                        $query->select('id')
                            ->from('orders')
                            ->where('user_id', $user->id);
                    })
                    ->limit($chunkSize)
                    ->delete();
            });

            $totalDeleted += $deleted;
            $bar->setProgress(min($totalDeleted, $count));

            // 释放内存
            gc_collect_cycles();
        } while ($deleted > 0);

        $bar->finish();
        $this->newLine();

        // 验证是否全部删除
        $remaining = DB::table('certs')
            ->whereIn('order_id', function ($query) use ($user) {
                $query->select('id')
                    ->from('orders')
                    ->where('user_id', $user->id);
            })
            ->count();
        if ($remaining > 0) {
            $this->warn("警告：证书还有 $remaining 条数据未删除");
        }
    }

    /**
     * 删除用户通知（多态关联）
     *
     *
     * @throws Throwable
     */
    private function deleteNotifications(User $user, int $chunkSize): void
    {
        if (! DB::getSchemaBuilder()->hasTable('notifications')) {
            return;
        }

        $count = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->count();

        if ($count === 0) {
            return;
        }

        $this->info("删除通知 ($count 条)...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $totalDeleted = 0;
        $maxIterations = ceil($count / $chunkSize) + 10;
        $iteration = 0;

        do {
            $iteration++;

            if ($iteration > $maxIterations) {
                $remaining = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $user->id)
                    ->count();
                $this->error("\n警告：通知删除循环超过预期次数！还剩 $remaining 条数据未删除");
                break;
            }

            $deleted = DB::transaction(function () use ($user, $chunkSize) {
                return DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $user->id)
                    ->limit($chunkSize)
                    ->delete();
            });

            $totalDeleted += $deleted;
            $bar->setProgress(min($totalDeleted, $count));

            // 释放内存
            gc_collect_cycles();
        } while ($deleted > 0);

        $bar->finish();
        $this->newLine();

        // 验证是否全部删除
        $remaining = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->count();
        if ($remaining > 0) {
            $this->warn("警告：通知还有 $remaining 条数据未删除");
        }
    }

    /**
     * 删除用户相关任务（通过用户ID和订单IDs）
     *
     *
     * @throws Throwable
     */
    private function deleteUserTasks(User $user, int $chunkSize): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tasks')) {
            return;
        }

        // 使用子查询避免大量订单ID导致IN子句过长
        $count = DB::table('tasks')
            ->where(function ($query) use ($user) {
                $query->whereIn('order_id', function ($subQuery) use ($user) {
                    $subQuery->select('id')
                        ->from('orders')
                        ->where('user_id', $user->id);
                });
            })
            ->count();

        if ($count === 0) {
            return;
        }

        $this->info("删除任务 ($count 条)...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $totalDeleted = 0;
        $maxIterations = ceil($count / $chunkSize) + 10;
        $iteration = 0;

        do {
            $iteration++;

            if ($iteration > $maxIterations) {
                $remaining = DB::table('tasks')
                    ->where(function ($query) use ($user) {
                        $query->whereIn('order_id', function ($subQuery) use ($user) {
                            $subQuery->select('id')
                                ->from('orders')
                                ->where('user_id', $user->id);
                        });
                    })
                    ->count();
                $this->error("\n警告：任务删除循环超过预期次数！还剩 $remaining 条数据未删除");
                break;
            }

            $deleted = DB::transaction(function () use ($user, $chunkSize) {
                return DB::table('tasks')
                    ->where(function ($query) use ($user) {
                        $query->whereIn('order_id', function ($subQuery) use ($user) {
                            $subQuery->select('id')
                                ->from('orders')
                                ->where('user_id', $user->id);
                        });
                    })
                    ->limit($chunkSize)
                    ->delete();
            });

            $totalDeleted += $deleted;
            $bar->setProgress(min($totalDeleted, $count));

            // 释放内存
            gc_collect_cycles();
        } while ($deleted > 0);

        $bar->finish();
        $this->newLine();

        // 验证是否全部删除
        $remaining = DB::table('tasks')
            ->where(function ($query) use ($user) {
                $query->whereIn('order_id', function ($subQuery) use ($user) {
                    $subQuery->select('id')
                        ->from('orders')
                        ->where('user_id', $user->id);
                });
            })
            ->count();
        if ($remaining > 0) {
            $this->warn("警告：任务还有 $remaining 条数据未删除");
        }
    }
}
