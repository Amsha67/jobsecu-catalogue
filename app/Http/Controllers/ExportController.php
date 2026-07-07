<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GoogleSheetsService;
use App\Services\CsvExporter;

class ExportController extends Controller
{
    public function exporter(Request $request)
    {
        $request->validate(['onglet' => 'required|string']);
        $onglet = $request->input('onglet');

        try {
            $sheets = new GoogleSheetsService();
            $donnees = $sheets->lireToutesLesLignes($onglet);
            $exporter = new CsvExporter();
            $csv = $exporter->generer($donnees);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="jobsecu-' . strtolower(str_replace(' ', '-', $onglet)) . '.csv"',
            ]);

        } catch (\Exception $e) {
            return response()->json(['erreur' => $e->getMessage()], 500);
        }
    }
}