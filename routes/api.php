<?php

use App\Http\Controllers\Api\IncidentReportController;
use Illuminate\Support\Facades\Route;

/*
 | REST API — Pelaporan Insiden (whistleblowing) untuk bridging sistem lain.
 | Auth: header Authorization: Bearer <INCIDENT_API_TOKEN>
 | Base: /api/incident-reports
 */
Route::middleware('api.token')->prefix('incident-reports')->group(function () {
    Route::get('/', [IncidentReportController::class, 'index']);
    Route::post('/', [IncidentReportController::class, 'store']);
    Route::get('/{reportNo}', [IncidentReportController::class, 'show']);
    Route::patch('/{reportNo}/status', [IncidentReportController::class, 'updateStatus']);
});
