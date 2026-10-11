<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * S20: one "Import data" panel where the user picks Production, Sales or
 * both. Each file is handed to the existing importer (unchanged, still
 * reachable on its old route), which runs in its own transaction — so a
 * failed Sales file cannot undo a Production import that already succeeded.
 */
class CombinedImportController extends Controller
{
    public function store(Request $request)
    {
        $csv = 'nullable|file|mimes:csv,txt|max:10240';
        $request->validate([
            'types'         => 'required|array|min:1',
            'types.*'       => 'in:production,sales',
            'building_file' => $csv,
            'grading_file'  => $csv,
            'sales_file'    => [in_array('sales', (array) $request->input('types'), true) ? 'required' : 'nullable', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'types.required'      => 'Choose what to import: Production, Sales, or both.',
            'sales_file.required' => 'Choose the sales CSV file, or untick Sales.',
        ]);

        $types = $request->input('types');
        if (in_array('production', $types, true) && ! $request->hasFile('building_file') && ! $request->hasFile('grading_file')) {
            throw ValidationException::withMessages([
                'building_file' => 'Choose a building daily CSV and/or an egg grading CSV, or untick Production.',
            ]);
        }

        $jobs = [];
        if (in_array('production', $types, true)) {
            $jobs[] = ['Production — building daily', 'building_file', [BulkImportController::class, 'importBuildingDaily'], 'building_import_summary', 'imports.errors'];
            $jobs[] = ['Production — egg grading', 'grading_file', [BulkImportController::class, 'importEggGrading'], 'grading_import_summary', 'imports.errors'];
        }
        if (in_array('sales', $types, true)) {
            $jobs[] = ['Sales', 'sales_file', [EggSaleController::class, 'importSales'], 'import_summary', 'sales.import.errors'];
        }

        $results = [];
        foreach ($jobs as [$label, $field, [$controller, $method], $summaryKey, $errorRoute]) {
            if (! $request->hasFile($field)) {
                continue;
            }

            // A fresh request carrying just this file as csv_file — what the
            // importer expects. (duplicate() would keep the parent's cached files.)
            $subRequest = Request::create($request->url(), 'POST', [], [], ['csv_file' => $request->file($field)]);
            $subRequest->setUserResolver($request->getUserResolver());
            $subRequest->setLaravelSession($request->session());

            try {
                app($controller)->{$method}($subRequest);

                // The importer flashed its summary for its own page; take it here.
                $summary = session()->pull($summaryKey, []);
                $token   = session()->pull('import_error_token');
                $results[] = [
                    'type'      => $label,
                    'ok'        => true,
                    'summary'   => $summary,
                    'error_url' => $token ? route($errorRoute, $token) : null,
                ];
            } catch (ValidationException $e) {
                $results[] = ['type' => $label, 'ok' => false, 'message' => collect($e->errors())->flatten()->implode(' ')];
            } catch (\Throwable $e) {
                report($e);
                $results[] = ['type' => $label, 'ok' => false, 'message' => 'Import failed and nothing from this file was saved. ' . $e->getMessage()];
            }
        }

        return redirect()->route('daily-entry.index')->with('combined_import_results', $results);
    }
}
