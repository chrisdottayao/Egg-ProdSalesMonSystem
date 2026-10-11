<?php

namespace App\Exports;

use App\Support\FarmReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Phase 3 item 11: the complete report as one workbook — a "Report" cover
 * sheet, one sheet per ticked section, and "Basis & definitions". Charts
 * become their data table (Excel sheets hold the numbers, not the picture).
 */
class CompleteReportExport implements WithMultipleSheets
{
    public function __construct(private array $data)
    {
    }

    public function sheets(): array
    {
        /** @var FarmReport $report */
        $report = $this->data['report'];
        $sheets = [new ViewExport('reports.complete.sheet', $this->data + ['section' => 'cover'], 'Report')];

        foreach (FarmReport::SECTIONS as $key => $title) {
            if ($report->includes($key)) {
                $section  = $key === 'charts' ? 'chart_data' : $key;
                $sheets[] = new ViewExport('reports.complete.sheet', $this->data + ['section' => $section], $title);
            }
        }
        $sheets[] = new ViewExport('reports.complete.sheet', $this->data + ['section' => 'definitions'], 'Basis & definitions');

        return $sheets;
    }
}
