<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\DataImportResponse;
use App\Models\DataImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataImportController extends Controller
{
    /**
     * API: GET /api/imports?type={type}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:50', 'exists:import_types,type'],
        ]);

        $imports = DataImport::query()
            ->with('importType')
            ->whereHas(
                'importType',
                fn ($query) => $query->where('type', $validated['type'])
            )
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(
            DataImportResponse::collection($imports)->resolve(),
            'Load import history successfully'
        );
    }

    /**
     * API: GET /api/imports/{id}/result
     */
    public function downloadResult(int $id): BinaryFileResponse|JsonResponse
    {
        $import = DataImport::query()
            ->with('importType')
            ->find($id);

        if ($import === null) {
            return ApiResponse::error('Import not found', 404);
        }

        $path = $import->file_result_path;
        $disk = Storage::disk('local');

        if (! is_string($path) || $path === '' || ! $disk->exists($path)) {
            return ApiResponse::error('Import result file not found', 404);
        }

        $type = $import->importType?->type ?? 'import';

        return response()->download(
            $disk->path($path),
            "{$type}_import_result_{$import->id}.xlsx",
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }
}
