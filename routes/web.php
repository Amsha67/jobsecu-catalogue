<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;

Route::get('/', function () {
    return view('app');
});

Route::post('/api/traiter-pdf', [PdfController::class, 'traiter']);