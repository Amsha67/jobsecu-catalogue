<?php
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;

Route::get('/', function () {
    return view('app');
});

Route::get('/api/credits-claude', [App\Http\Controllers\PdfController::class, 'verifierCredits']);
Route::post('/api/traiter-pdf', [PdfController::class, 'traiter']);
Route::post('/api/exporter-csv', [ExportController::class, 'exporter']);