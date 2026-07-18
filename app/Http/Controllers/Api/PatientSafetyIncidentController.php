<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PatientSafetyIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API Pelaporan Insiden Keselamatan Pasien (IKP) — untuk bridging sistem
 * lain (EMR/SIMRS push insiden ke sini). Semua endpoint butuh header
 * Authorization: Bearer <INCIDENT_API_TOKEN>.
 */
class PatientSafetyIncidentController extends Controller
{
    /** GET /api/patient-safety-incidents — daftar (filter: status, incident_type, grading, per_page). */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('patient_safety_incidents')
            ->select('id', 'report_no', 'incident_type', 'title', 'chronology', 'patient_impact',
                'immediate_action', 'grading', 'location', 'incident_date', 'incident_time',
                'patient_ref', 'reporter_name', 'reporter_role', 'reporter_contact', 'is_anonymous',
                'status', 'source', 'created_at', 'updated_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('incident_type')) {
            $query->where('incident_type', $request->query('incident_type'));
        }
        if ($request->filled('grading')) {
            $query->where('grading', $request->query('grading'));
        }

        $perHalaman = min(100, max(1, (int) $request->query('per_page', 25)));
        $paginator  = $query->paginate($perHalaman);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /** POST /api/patient-safety-incidents — buat laporan (sistem lain push ke sini). */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'incident_type'    => 'required|string|in:' . implode(',', PatientSafetyIncidentService::TYPES),
            'title'            => 'required|string|max:255',
            'chronology'       => 'required|string|max:5000',
            'patient_impact'   => 'nullable|string|max:5000',
            'immediate_action' => 'nullable|string|max:5000',
            'grading'          => 'nullable|string|in:' . implode(',', PatientSafetyIncidentService::GRADINGS),
            'location'         => 'nullable|string|max:255',
            'incident_date'    => 'nullable|date',
            'incident_time'    => 'nullable|string|max:5',
            'patient_ref'      => 'nullable|string|max:100',
            'reporter_name'    => 'nullable|string|max:150',
            'reporter_role'    => 'nullable|string|max:100',
            'reporter_contact' => 'nullable|string|max:150',
            'is_anonymous'     => 'nullable|boolean',
        ]);

        $laporanBaru = PatientSafetyIncidentService::create($validated, 'api');

        return response()->json([
            'success'   => true,
            'message'   => 'Laporan IKP tersimpan.',
            'report_no' => $laporanBaru['report_no'],
            'id'        => $laporanBaru['id'],
        ], 201);
    }

    /** GET /api/patient-safety-incidents/{reportNo} — detail satu laporan. */
    public function show(string $reportNo): JsonResponse
    {
        $laporan = DB::table('patient_safety_incidents')->where('report_no', $reportNo)->first();
        if (! $laporan) {
            return response()->json(['success' => false, 'message' => 'Laporan tidak ditemukan.'], 404);
        }
        return response()->json(['success' => true, 'data' => $laporan]);
    }

    /** PATCH /api/patient-safety-incidents/{reportNo}/status — update status. */
    public function updateStatus(Request $request, string $reportNo): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', PatientSafetyIncidentService::STATUSES),
        ]);

        $jumlahTerubah = DB::table('patient_safety_incidents')->where('report_no', $reportNo)
            ->update(['status' => $validated['status'], 'updated_at' => now()]);

        if (! $jumlahTerubah) {
            return response()->json(['success' => false, 'message' => 'Laporan tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Status diperbarui.', 'status' => $validated['status']]);
    }

    /** PATCH /api/patient-safety-incidents/{reportNo}/grading — set grading matriks risiko (tim mutu). */
    public function updateGrading(Request $request, string $reportNo): JsonResponse
    {
        $validated = $request->validate([
            'grading' => 'required|string|in:' . implode(',', PatientSafetyIncidentService::GRADINGS),
        ]);

        $jumlahTerubah = DB::table('patient_safety_incidents')->where('report_no', $reportNo)
            ->update(['grading' => $validated['grading'], 'updated_at' => now()]);

        if (! $jumlahTerubah) {
            return response()->json(['success' => false, 'message' => 'Laporan tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Grading diperbarui.', 'grading' => $validated['grading']]);
    }
}
