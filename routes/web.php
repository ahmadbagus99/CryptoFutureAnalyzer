<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AnalysisController::class, 'index'])->name('analysis.index');
Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
