<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ImportStudentsRequest;
use App\Services\Students\StudentImportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    private const TEMPLATE_FILE_NAME = 'Import Student Template.xlsx';

    private const XLSX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __invoke(
        ImportStudentsRequest $request,
        StudentImportService $studentImport,
    ): BinaryFileResponse {
        $validated = $request->validated();
        $result = $studentImport->import(
            $request->file('file'),
            (int) $validated['curriculum_id'],
            (int) $validated['study_plan_id'],
            $request->attributes->get('jwt_claims', []),
        );

        return response()->download(
            $result['absolute_path'],
            $result['download_name'],
            [
                'Content-Type' => self::XLSX_CONTENT_TYPE,
                'X-Import-Id' => (string) $result['import']->id,
                'X-Import-Total' => (string) $result['import']->total_count,
                'X-Import-Success' => (string) $result['import']->success_count,
                'X-Import-Failed' => (string) $result['import']->failed_count,
            ],
        );
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $templatePath = resource_path('templates/'.self::TEMPLATE_FILE_NAME);

        abort_unless(is_file($templatePath), 404, 'Student import template not found');

        return response()->download(
            $templatePath,
            self::TEMPLATE_FILE_NAME,
            ['Content-Type' => self::XLSX_CONTENT_TYPE],
        );
    }
}
