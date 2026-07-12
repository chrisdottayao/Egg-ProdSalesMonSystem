<?php

namespace App\Http\Controllers;

use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\ForecastEvaluation;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportsController extends Controller
{
    private function getReportData(Request $request): array
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate   = $request->input('end_date', Carbon::today()->format('Y-m-d'));
        $period    = $request->input('period', 'Daily');

        $productions = EggProduction::whereBetween('date', [$startDate, $endDate])->get();
        $sales       = EggSale::whereBetween('date', [$startDate, $endDate])->get();

        $totalProduced = $productions->sum('eggs_collected');
        $totalSold     = $sales->sum('quantity');

        $summary = [
            'total_eggs_produced' => $totalProduced,
            'total_eggs_sold'     => $totalSold,
            'total_revenue'       => $sales->sum('total_amount'),
            'avg_production_rate' => $productions->count()
                ? round($productions->avg(fn($p) => $p->production_rate), 1)
                : 0,
            'avg_sales_rate'      => $totalProduced > 0
                ? round(($totalSold / $totalProduced) * 100, 1)
                : 0,
            'remaining_eggs'      => $totalProduced - $totalSold,
        ];

        $dailyData = EggProduction::whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get()
            ->map(function ($prod) {
                $sold    = EggSale::whereDate('date', $prod->date)->sum('quantity');
                $revenue = EggSale::whereDate('date', $prod->date)->sum('total_amount');
                return [
                    'date'      => $prod->date->format('M d'),
                    'eggs'      => $prod->eggs_collected,
                    'sold'      => $sold,
                    'revenue'   => $revenue,
                    'prod_rate' => $prod->production_rate,
                ];
            });

        // Real batch traceability query
        $batches = EggProduction::with('eggSales')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        // Real audit log query
        $auditLogs = AuditLog::with('user')
            ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
            ->orderBy('created_at', 'desc')
            ->get();

        return compact('summary', 'dailyData', 'batches', 'auditLogs', 'startDate', 'endDate', 'period');
    }

    public function index(Request $request)
    {
        $data = $this->getReportData($request);
        $data['forecastEvaluations'] = ForecastEvaluation::latest('evaluated_at')->take(10)->get();
        return view('reports.index', $data);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getReportData($request);
        $pdf  = Pdf::loadView('reports.pdf', $data)->setPaper('a4', 'landscape');

        $filename = 'report_' . $data['startDate'] . '_to_' . $data['endDate'] . '.pdf';
        return $pdf->download($filename);
    }

    public function exportCsv(Request $request)
    {
        $data      = $this->getReportData($request);
        $dailyData = $data['dailyData'];
        $summary   = $data['summary'];

        $rows   = [];
        $rows[] = ['Date', 'Eggs Produced', 'Eggs Sold', 'Revenue (PHP)', 'Prod Rate (%)', 'Remaining'];

        foreach ($dailyData as $row) {
            $rows[] = [
                $row['date'],
                $row['eggs'],
                $row['sold'],
                number_format($row['revenue'], 2),
                $row['prod_rate'],
                $row['eggs'] - $row['sold'],
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', $summary['total_eggs_produced'], $summary['total_eggs_sold'],
                   number_format($summary['total_revenue'], 2), $summary['avg_production_rate'] . '%',
                   $summary['remaining_eggs']];

        $filename = 'report_' . $data['startDate'] . '_to_' . $data['endDate'] . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Batch Traceability Exports ──────────────────────────────────────────

    public function exportBatchPdf(Request $request)
    {
        $data = $this->getReportData($request);
        $pdf  = Pdf::loadView('reports.batch_pdf', $data)->setPaper('a4', 'landscape');

        $filename = 'batch_traceability_' . $data['startDate'] . '_to_' . $data['endDate'] . '.pdf';
        return $pdf->download($filename);
    }

    public function exportBatchCsv(Request $request)
    {
        $data    = $this->getReportData($request);
        $batches = $data['batches'];

        $rows   = [];
        $rows[] = ['Batch ID', 'Collection Date', 'Egg Size', 'Qty Collected', 'Qty Sold', 'Remaining Stock', 'Spoilage', 'Sell-Through (%)', 'Status'];

        foreach ($batches as $b) {
            $rows[] = [
                'HB-' . $b->id,
                $b->date->format('Y-m-d'),
                $b->egg_size,
                $b->eggs_collected,
                $b->quantity_sold,
                $b->remaining_stock,
                $b->spoilage_count,
                $b->sell_through_rate . '%',
                $b->batch_status,
            ];
        }

        $filename = 'batch_traceability_' . $data['startDate'] . '_to_' . $data['endDate'] . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Audit Logs Exports ────────────────────────────────────────────────

    public function exportAuditPdf(Request $request)
    {
        $data = $this->getReportData($request);
        $pdf  = Pdf::loadView('reports.audit_pdf', $data)->setPaper('a4', 'landscape');

        $filename = 'audit_report_' . $data['startDate'] . '_to_' . $data['endDate'] . '.pdf';
        return $pdf->download($filename);
    }

    public function exportAuditCsv(Request $request)
    {
        $data      = $this->getReportData($request);
        $auditLogs = $data['auditLogs'];

        $rows   = [];
        $rows[] = ['Entry Date', 'User', 'Action', 'Model Type', 'Model ID', 'Rule Violated / Details', 'Status'];

        foreach ($auditLogs as $a) {
            $rows[] = [
                $a->created_at->format('Y-m-d H:i'),
                $a->user ? $a->user->name : 'System',
                strtoupper($a->action),
                $a->model_type,
                $a->model_id,
                $a->inconsistency_flagged ? ('[VIOLATION: ' . $a->inconsistency_rule . '] ' . json_encode($a->details)) : json_encode($a->details),
                $a->inconsistency_flagged ? 'Flagged' : 'Clean',
            ];
        }

        $filename = 'audit_report_' . $data['startDate'] . '_to_' . $data['endDate'] . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}

