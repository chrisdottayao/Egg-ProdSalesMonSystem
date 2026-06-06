<?php

namespace App\Http\Controllers;

use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\ForecastEvaluation;
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

        return compact('summary', 'dailyData', 'startDate', 'endDate', 'period');
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
}
