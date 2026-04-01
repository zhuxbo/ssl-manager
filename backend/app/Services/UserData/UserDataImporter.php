<?php

namespace App\Services\UserData;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\OutputInterface;

class UserDataImporter
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * 干跑模式：检测冲突
     */
    public function dryRun(string $filePath, int $userId): bool
    {
        $this->validateFile($filePath, $userId);

        $this->output->writeln("解析文件：$filePath");
        $statements = $this->parseStatements($filePath);

        $totalRecords = 0;
        $totalConflicts = 0;
        $report = [];

        foreach ($statements as $table => $inserts) {
            $ids = $this->extractPrimaryKeys($table, $inserts);
            $totalRecords += count($ids);

            if (empty($ids)) {
                $report[] = [$table, count($inserts).'条', '0 条冲突', '-'];

                continue;
            }

            // 检查表是否存在
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $report[] = [$table, count($ids).'条', '表不存在', '⚠'];

                continue;
            }

            // 分批检查冲突
            $conflictIds = [];
            foreach (array_chunk($ids, 1000) as $chunk) {
                $existing = DB::table($table)->whereIn('id', $chunk)->pluck('id')->all();
                $conflictIds = array_merge($conflictIds, $existing);
            }

            $conflictCount = count($conflictIds);
            $totalConflicts += $conflictCount;

            if ($conflictCount > 0) {
                $idPreview = implode(', ', array_slice($conflictIds, 0, 5));
                if ($conflictCount > 5) {
                    $idPreview .= '...';
                }
                $report[] = [$table, count($ids).'条', "$conflictCount 条冲突", "ID: $idPreview"];
            } else {
                $report[] = [$table, count($ids).'条', '0 条冲突', '✓'];
            }
        }

        // 输出报告
        $this->output->writeln("\n冲突检测报告：");
        $this->printTable(['表名', '记录数', '冲突', '详情'], $report);
        $this->output->writeln("\n总计：$totalConflicts 条冲突 / $totalRecords 条记录");

        if ($totalConflicts === 0) {
            $this->output->writeln('<info>无冲突，可以安全导入</info>');

            return true;
        }

        $this->output->writeln('<comment>存在冲突，导入时冲突记录将被跳过</comment>');

        return false;
    }

    /**
     * 实际导入
     */
    public function import(string $filePath, int $userId): void
    {
        $this->validateFile($filePath, $userId);

        $this->output->writeln("导入文件：$filePath");
        $statements = $this->parseStatements($filePath);

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $report = [];

            foreach ($statements as $table => $inserts) {
                $imported = 0;
                $skipped = 0;

                foreach ($inserts as $sql) {
                    try {
                        DB::unprepared($sql);
                        // 粗略统计（按 VALUES 中的行数）
                        $imported += substr_count($sql, '),(') + 1;
                    } catch (\Throwable $e) {
                        $skipped += substr_count($sql, '),(') + 1;
                        if (str_contains($e->getMessage(), 'Duplicate entry')) {
                            // 冲突跳过
                            continue;
                        }
                        $this->output->writeln("<comment>警告 [$table]：{$e->getMessage()}</comment>");
                    }
                }

                if ($imported > 0 || $skipped > 0) {
                    $report[] = [$table, $imported, $skipped];
                    $this->output->writeln("导入 {$table}：$imported 条".($skipped > 0 ? "，跳过 $skipped 条" : ''));
                }
            }

            $this->output->writeln("\n<info>导入完成</info>");
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * 查找用户的导出文件
     *
     * @return string[] 文件路径列表（按时间排序）
     */
    public static function findExportFiles(int $userId): array
    {
        $exportDir = storage_path('app/private/exports/users');
        $pattern = "$exportDir/{$userId}_*.sql";
        $files = glob($pattern) ?: [];
        sort($files);

        return $files;
    }

    /**
     * 验证文件
     */
    private function validateFile(string $filePath, int $userId): void
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException("文件不存在：$filePath");
        }

        // 读取头部注释验证 user_id
        $handle = fopen($filePath, 'r');
        $foundUserId = false;

        for ($i = 0; $i < 10; $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            if (preg_match('/^-- user_id:\s*(\d+)/', $line, $matches)) {
                if ((int) $matches[1] !== $userId) {
                    fclose($handle);

                    throw new \RuntimeException("文件中的 user_id ({$matches[1]}) 与指定的 user_id ($userId) 不匹配");
                }
                $foundUserId = true;
                break;
            }
        }

        fclose($handle);

        if (! $foundUserId) {
            throw new \RuntimeException('文件格式无效：未找到 user_id 标记');
        }
    }

    /**
     * 解析 SQL 文件，按表名分组 INSERT 语句
     *
     * @return array<string, string[]> 表名 => INSERT 语句列表
     */
    private function parseStatements(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $statements = [];

        // 匹配所有 INSERT INTO 语句
        preg_match_all('/INSERT INTO `(\w+)`[^;]+;/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $table = $match[1];
            $statements[$table][] = $match[0];
        }

        return $statements;
    }

    /**
     * 从 INSERT 语句中提取主键 ID
     */
    private function extractPrimaryKeys(string $table, array $inserts): array
    {
        $ids = [];

        foreach ($inserts as $sql) {
            // 找到 id 列在列列表中的位置
            if (! preg_match('/INSERT INTO `\w+` \(([^)]+)\) VALUES/s', $sql, $colMatch)) {
                continue;
            }

            $columns = array_map(fn ($c) => trim($c, " `\t\n\r"), explode(',', $colMatch[1]));
            $idIndex = array_search('id', $columns);
            if ($idIndex === false) {
                continue;
            }

            // 提取 VALUES 中的每组值
            if (! preg_match('/VALUES\s*(.+);$/s', $sql, $valMatch)) {
                continue;
            }

            // 逐个解析值组 - 按 ),( 分割（简化处理）
            $valuesStr = $valMatch[1];
            // 去掉最外层括号
            $valuesStr = preg_replace('/^\s*\(/', '', $valuesStr);
            $valuesStr = preg_replace('/\)\s*$/', '', $valuesStr);

            // 按 "),\n(" 或 "),(" 分割各行
            $rows = preg_split('/\)\s*,\s*\(/', $valuesStr);

            foreach ($rows as $rowStr) {
                // 解析该行的值，提取第 $idIndex 个
                $values = $this->parseValueRow($rowStr);
                if (isset($values[$idIndex])) {
                    $val = trim($values[$idIndex], " '\"");
                    if (is_numeric($val)) {
                        $ids[] = (int) $val;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * 解析一行 VALUES 中的值（处理引号内逗号）
     */
    private function parseValueRow(string $row): array
    {
        $values = [];
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $escaped = false;

        for ($i = 0; $i < strlen($row); $i++) {
            $char = $row[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escaped = true;

                continue;
            }

            if (! $inQuote && ($char === '\'' || $char === '"')) {
                $inQuote = true;
                $quoteChar = $char;
                $current .= $char;

                continue;
            }

            if ($inQuote && $char === $quoteChar) {
                $inQuote = false;
                $current .= $char;

                continue;
            }

            if (! $inQuote && $char === ',') {
                $values[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $values[] = trim($current);

        return $values;
    }

    /**
     * 简易表格输出
     */
    private function printTable(array $headers, array $rows): void
    {
        // 计算列宽
        $widths = array_map('mb_strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $cell));
            }
        }

        $separator = '+'.implode('+', array_map(fn ($w) => str_repeat('-', $w + 2), $widths)).'+';

        $this->output->writeln($separator);
        $headerLine = '|';
        foreach ($headers as $i => $header) {
            $headerLine .= ' '.str_pad($header, $widths[$i] + mb_strlen($header) - strlen($header)).' |';
        }
        $this->output->writeln($headerLine);
        $this->output->writeln($separator);

        foreach ($rows as $row) {
            $line = '|';
            foreach ($row as $i => $cell) {
                $cell = (string) $cell;
                $line .= ' '.str_pad($cell, $widths[$i] + mb_strlen($cell) - strlen($cell)).' |';
            }
            $this->output->writeln($line);
        }

        $this->output->writeln($separator);
    }
}
