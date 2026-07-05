<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IncidentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API pelaporan insiden (whistleblowing) — untuk bridging sistem lain.
 * Semua endpoint butuh header Authorization: Bearer <INCIDENT_API_TOKEN>.
 */
class IncidentReportController extends Controller
{
    /** GET /api/incident-reports — daftar (filter: status, category, per_page). */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('incident_reports')
            ->select('id', 'report_no', 'category', 'title', 'description', 'location',
                'incident_date', 'reporter_name', 'reporter_contact', 'is_anonymous',
                'status', 'source', 'created_at', 'updated_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
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

    /** POST /api/incident-reports — buat laporan (sistem lain push ke sini). */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category'         => 'required|string|in:' . implode(',', IncidentReportService::CATEGORIES),
            'title'            => 'required|string|max:255',
            'description'      => 'required|string|max:5000',
            'location'         => 'nullable|string|max:255',
            'incident_date'    => 'nullable|date',
            'reporter_name'    => 'nullable|string|max:150',
            'reporter_contact' => 'nullable|string|max:150',
            'is_anonymous'     => 'nullable|boolean',
        ]);

        $laporanBaru = IncidentReportService::create($validated, 'api');

        return response()->json([
            'success'   => true,
            'message'   => 'Laporan tersimpan.',
            'report_no' => $laporanBaru['report_no'],
            'id'        => $laporanBaru['id'],
        ], 201);
    }

    /** GET /api/incident-reports/{reportNo} — detail satu laporan. */
    public function show(string $reportNo): JsonResponse
    {
        $laporan = DB::table('incident_reports')->where('report_no', $reportNo)->first();
        if (! $laporan) {
            return response()->json(['success' => false, 'message' => 'Laporan tidak ditemukan.'], 404);
        }
        return response()->json(['success' => true, 'data' => $laporan]);
    }

    /** PATCH /api/incident-reports/{reportNo}/status — update status. */
    public function updateStatus(Request $request, string $reportNo): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', IncidentReportService::STATUSES),
        ]);

        $jumlahTerubah = DB::table('incident_reports')->where('report_no', $reportNo)
            ->update(['status' => $validated['status'], 'updated_at' => now()]);

        if (! $jumlahTerubah) {
            return response()->json(['success' => false, 'message' => 'Laporan tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Status diperbarui.', 'status' => $validated['status']]);
    }
}
