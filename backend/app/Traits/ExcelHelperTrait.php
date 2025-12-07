<?php

namespace App\Traits;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel 通用辅助方法
 */
trait ExcelHelperTrait
{
    /**
     * 格式化周期显示
     */
    protected function formatPeriod($period): string
    {
        if ($period >= 12) {
            $years = $period / 12;
            if ($years == 1) {
                return '1年';
            }

            return $years.'年';
        }

        return $period.'个月';
    }

    /**
     * 设置Excel表头样式
     */
    protected function setHeaderStyle($sheet, $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);
    }

    /**
     * 设置数据区域样式
     */
    protected function setDataStyle($sheet, $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    /**
     * 执行单元格合并
     */
    protected function mergeCells($sheet, array $mergeRanges): void
    {
        foreach ($mergeRanges as $range) {
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * 自动调整列宽
     */
    protected function autoSizeColumns($sheet, $startColumn, $endColumn): void
    {
        foreach (range($startColumn, $endColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * 生成Excel下载响应
     */
    protected function createDownloadResponse(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.rawurlencode($filename).'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * 格式化价格显示
     */
    protected function formatPrice($price): string
    {
        return $price > 0 ? number_format($price, 2) : '-';
    }
}
