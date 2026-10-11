<?php

namespace App\Http\Controllers;

use App\Exports\AuditReportExport;
use App\Exports\BatchTraceabilityExport;
use App\Exports\ReportExport;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\ForecastEvaluation;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Support\AuditLogFilter;
use App\Support\ManilaDate;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

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
        $totalSpoiled  = $productions->sum(fn ($p) => $p->spoilage_count ?? 0);
        $totalMortality = $productions->sum('mortality');
        // Farm-wide, not per-building — same convention BuildingPerformanceService
        // uses for revenue/expenses (Expense::building_id is nullable/per-building,
        // but this report has never scoped to a single building).
        $totalExpenses = (float) Expense::whereBetween('date', [$startDate, $endDate])->sum('amount');
        $totalRevenue  = $sales->sum('total_amount');

        $summary = [
            'total_eggs_produced' => $totalProduced,
            'total_eggs_sold'     => $totalSold,
            'total_revenue'       => $totalRevenue,
            'total_mortality'     => $totalMortality,
            'total_expenses'      => $totalExpenses,
            'net_income'          => round($totalRevenue - $totalExpenses, 2),
            'avg_production_rate' => $productions->count()
                ? round($productions->avg(fn($p) => $p->production_rate), 1)
                : 0,
            'avg_sales_rate'      => $totalProduced > 0
                ? round(($totalSold / $totalProduced) * 100, 1)
                : 0,
            // Remaining = Total Collected - Total Sold - Spoiled. spoilage_count
            // defaults to 0 in the schema and is rarely populated historically —
            // that's expected, not an error, so it's coalesced above rather than
            // left to blow up on a null.
            'remaining_eggs'      => $totalProduced - $totalSold - $totalSpoiled,
        ];

        $dailyData = EggProduction::whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get()
            ->map(function ($prod) {
                $sold      = EggSale::whereDate('date', $prod->date)->sum('quantity');
                $revenue   = EggSale::whereDate('date', $prod->date)->sum('total_amount');
                $expenses  = Expense::whereDate('date', $prod->date)->sum('amount');
                return [
                    'date'      => $prod->date->format('M d'),
                    'eggs'      => $prod->eggs_collected,
                    'sold'      => $sold,
                    'revenue'   => $revenue,
                    'prod_rate' => $prod->production_rate,
                    'spoiled'   => $prod->spoilage_count ?? 0,
                    'mortality' => $prod->mortality ?? 0,
                    'expenses'  => (float) $expenses,
                ];
            });

        // Real batch traceability query
        $batches = EggProduction::with('eggSales')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        return compact('summary', 'dailyData', 'batches', 'startDate', 'endDate', 'period');
    }

    public function index(Request $request)
    {
        $data = $this->getReportData($request);
        $data['forecastEvaluations'] = ForecastEvaluation::latest('evaluated_at')->take(10)->get();

        // Audit Log section (Phase 3 item 2) — replaces the inconsistency-only
        // report; flagged rows remain a badge and a filter.
        $data['auditFilter']  = AuditLogFilter::fromRequest($request, $data['startDate'], $data['endDate']);
        $data['auditLogs']    = $data['auditFilter']->query()->paginate(25, ['*'], 'audit_page')->withQueryString()->fragment('audit-log');
        $data['auditOptions'] = AuditLogFilter::options();

        return view('reports.index', $data);
    }

    /** Phase 3 item 2: every report download is itself recorded in the Audit Log. */
    private function logExport(string $report, string $format, array $details = []): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'export',
            'model_type' => 'Report',
            'details'    => ['report' => $report, 'format' => $format] + $details,
        ]);
    }

    private function auditExportData(Request $request): array
    {
        $range  = $this->getReportData($request);
        $filter = AuditLogFilter::fromRequest($request, $range['startDate'], $range['endDate']);
        // Exports cap at 10,000 rows; narrow the filter for more.
        $logs   = $filter->query()->limit(10000)->get();

        return [
            'auditLogs'     => $logs,
            'auditFilter'   => $filter,
            'generatedBy'   => auth()->user(),
            'generatedAt'   => ManilaDate::now(),
        ];
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getReportData($request);
        $this->logExport('Production & Sales Report', 'pdf', ['range' => "{$data['startDate']} to {$data['endDate']}"]);
        $pdf  = Pdf::loadView('reports.pdf', $data)->setPaper('a4', 'landscape');

        $filename = 'report_' . $data['startDate'] . '_to_' . $data['endDate'] . '.pdf';
        return $pdf->download($filename);
    }

    public function exportCsv(Request $request)
    {
        $data      = $this->getReportData($request);
        $this->logExport('Production & Sales Report', 'csv', ['range' => "{$data['startDate']} to {$data['endDate']}"]);
        $dailyData = $data['dailyData'];
        $summary   = $data['summary'];

        $rows   = [];
        $rows[] = ['Date', 'Eggs Produced', 'Eggs Sold', 'Mortality', 'Revenue (PHP)', 'Utility Expenses (PHP)', 'Prod Rate (%)', 'Remaining'];

        foreach ($dailyData as $row) {
            $rows[] = [
                $row['date'],
                $row['eggs'],
                $row['sold'],
                $row['mortality'],
                number_format($row['revenue'], 2),
                number_format($row['expenses'], 2),
                $row['prod_rate'],
                $row['eggs'] - $row['sold'] - $row['spoiled'],
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', $summary['total_eggs_produced'], $summary['total_eggs_sold'], $summary['total_mortality'],
                   number_format($summary['total_revenue'], 2), number_format($summary['total_expenses'], 2),
                   $summary['avg_production_rate'] . '%', $summary['remaining_eggs']];
        $rows[] = ['NET INCOME', number_format($summary['net_income'], 2)];

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

    public function exportExcel(Request $request)
    {
        $data = $this->getReportData($request);
        $this->logExport('Production & Sales Report', 'excel', ['range' => "{$data['startDate']} to {$data['endDate']}"]);
        $filename = 'report_' . $data['startDate'] . '_to_' . $data['endDate'] . '.xlsx';

        return Excel::download(new ReportExport($data), $filename);
    }

    // ── Batch Traceability Exports ──────────────────────────────────────────

    public function exportBatchPdf(Request $request)
    {
        $data = $this->getReportData($request);
        $this->logExport('Batch Traceability', 'pdf', ['range' => "{$data['startDate']} to {$data['endDate']}"]);
        $pdf  = Pdf::loadView('reports.batch_pdf', $data)->setPaper('a4', 'landscape');

        $filename = 'batch_traceability_' . $data['startDate'] . '_to_' . $data['endDate'] . '.pdf';
        return $pdf->download($filename);
    }

    public function exportBatchCsv(Request $request)
    {
        $data    = $this->getReportData($request);
        $this->logExport('Batch Traceability', 'csv', ['range' => "{$data['startDate']} to {$data['endDate']}"]);
        $batches = $data['batches'];

        $rows   = [];
        $rows[] = ['Batch ID', 'Collection Date', 'Qty Collected', 'Qty Sold', 'Remaining Stock', 'Spoilage', 'Sell-Through (%)', 'Status'];

        foreach ($batches as $b) {
            $rows[] = [
                'HB-' . $b->id,
                $b->date->format('Y-m-d'),
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

    public function exportBatchExcel(Request $request)
    {
        $data = $this->getReportData($request);
        $this->logExport('Batch Traceability', 'excel', ['range' => "{$data['startDate']} to {$data['endDate']}"]);
        $filename = 'batch_traceability_' . $data['startDate'] . '_to_' . $data['endDate'] . '.xlsx';

        return Excel::download(new BatchTraceabilityExport($data), $filename);
    }

    // ── Audit Logs Exports ────────────────────────────────────────────────

    public function exportAuditPdf(Request $request)
    {
        $data = $this->auditExportData($request);
        $this->logExport('Audit Log', 'pdf', ['filters' => $data['auditFilter']->describe(), 'rows' => $data['auditLogs']->count()]);
        $pdf  = Pdf::loadView('reports.audit_pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('audit_log_' . $data['auditFilter']->from . '_to_' . $data['auditFilter']->to . '.pdf');
    }

    public function exportAuditCsv(Request $request)
    {
        $data      = $this->auditExportData($request);
        $auditLogs = $data['auditLogs'];
        $this->logExport('Audit Log', 'csv', ['filters' => $data['auditFilter']->describe(), 'rows' => $auditLogs->count()]);

        $rows   = [];
        $rows[] = ['SPC Farm Magalang — Audit Log'];
        $rows[] = ['Filters', $data['auditFilter']->describe()];
        $rows[] = ['Generated by', $data['generatedBy']->name . ' (' . $data['generatedBy']->role . ')', 'Generated at (Asia/Manila)', $data['generatedAt']->format('Y-m-d H:i')];
        $rows[] = [];
        $rows[] = ['When (Asia/Manila)', 'User', 'Role', 'Action', 'Record / Module', 'Record ID', 'Details', 'IP Address', 'Flagged', 'Rule', 'Resolved'];

        foreach ($auditLogs as $a) {
            $rows[] = [
                $a->created_at->copy()->setTimezone(ManilaDate::TZ)->format('Y-m-d H:i:s'),
                $a->user?->name ?? 'System / not signed in',
                $a->user?->role ?? '',
                $a->action_label,
                $a->module_label,
                $a->model_id,
                AuditLogFilter::detailsText($a),
                $a->ip_address,
                $a->inconsistency_flagged ? 'Yes' : 'No',
                $a->inconsistency_rule,
                $a->inconsistency_flagged ? ($a->resolved ? 'Yes' : 'No') : '',
            ];
        }

        $filename = 'audit_log_' . $data['auditFilter']->from . '_to_' . $data['auditFilter']->to . '.csv';

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

    public function exportAuditExcel(Request $request)
    {
        $data = $this->auditExportData($request);
        $this->logExport('Audit Log', 'excel', ['filters' => $data['auditFilter']->describe(), 'rows' => $data['auditLogs']->count()]);

        return Excel::download(new AuditReportExport($data), 'audit_log_' . $data['auditFilter']->from . '_to_' . $data['auditFilter']->to . '.xlsx');
    }
}

