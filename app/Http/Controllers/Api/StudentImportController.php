<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ImportStudentsRequest;
use App\Services\Students\StudentImportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    public function __invoke(
        ImportStudentsRequest $request,
        StudentImportService $studentImport,
    ): BinaryFileResponse {
        $result = $studentImport->import(
            $request->file('file'),
            $request->attributes->get('jwt_claims', []),
        );

        return response()->download(
            $result['absolute_path'],
            $result['download_name'],
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-Import-Id' => (string) $result['import']->id,
                'X-Import-Total' => (string) $result['import']->total_count,
                'X-Import-Success' => (string) $result['import']->success_count,
                'X-Import-Failed' => (string) $result['import']->failed_count,
            ],
        );
    }
}
