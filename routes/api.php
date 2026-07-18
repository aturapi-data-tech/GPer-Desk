<?php

use App\Http\Controllers\Api\IncidentReportController;
use App\Http\Controllers\Api\PatientSafetyIncidentController;
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

/*
 | REST API — Pelaporan Insiden Keselamatan Pasien (IKP) untuk bridging (EMR/SIMRS).
 | Auth: header Authorization: Bearer <INCIDENT_API_TOKEN>
 | Base: /api/patient-safety-incidents
 */
Route::middleware('api.token')->prefix('patient-safety-incidents')->group(function () {
    Route::get('/', [PatientSafetyIncidentController::class, 'index']);
    Route::post('/', [PatientSafetyIncidentController::class, 'store']);
    Route::get('/{reportNo}', [PatientSafetyIncidentController::class, 'show']);
    Route::patch('/{reportNo}/status', [PatientSafetyIncidentController::class, 'updateStatus']);
    Route::patch('/{reportNo}/grading', [PatientSafetyIncidentController::class, 'updateGrading']);
});
