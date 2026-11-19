<?php

namespace App\Http\Controllers\Api;

use App\Constants\Status;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grade\ImportGradesRequest;
use App\Http\Responses\DataImportResponse;
use App\Jobs\ProcessGradeImport;
use App\Models\DataImport;
use App\Models\ImportType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class GradeImportController extends Controller
{
    private const TEMPLATE_FILE_NAME = 'Import Grade Template.xlsx';

    private const XLSX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __invoke(ImportGradesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $importType = ImportType::query()
            ->where('type', 'grade')
            ->where('status', Status::ACTIVE)
            ->first();

        if ($importType === null) {
            throw ValidationException::withMessages([
                'file' => __('errors.import.grade_type_missing'),
            ]);
        }

        $file = $request->file('file');
        $claims = $request->attributes->get('jwt_claims', []);
        $import = DataImport::query()->create([
            'import_type_id' => $importType->id,
            'curriculum_id' => (int) $validated['curriculum_id'],
            'curriculum_code' => mb_substr(trim($validated['curriculum_name']), 0, 255),
            'curriculum_plan_id' => null,
            'curriculum_plan_name_th' => null,
            'file_name' => $file->getClientOriginalName(),
            'status' => Status::PROCESSING,
            'imported_by' => $this->importedBy($claims),
            'started_at' => now(),
        ]);

        try {
            $extension = strtolower($file->getClientOriginalExtension());
            $path = Storage::disk('local')->putFileAs(
                "imports/grades/{$import->id}",
                $file,
                "source.{$extension}",
            );

            if (! is_string($path) || $path === '') {
                throw new \RuntimeException(__('errors.import.grade_file_store_failed'));
            }

            ProcessGradeImport::dispatch($import->id, $path);
        } catch (Throwable $exception) {
            $import->update([
                'status' => Status::FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        return ApiResponse::success(
            (new DataImportResponse($import->refresh()))->resolve(),
            __('success.import.grade_queued'),
            202,
        );
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $templatePath = resource_path('templates/'.self::TEMPLATE_FILE_NAME);

        abort_unless(is_file($templatePath), 404, __('errors.import.grade_template_not_found'));

        return response()->download(
            $templatePath,
            self::TEMPLATE_FILE_NAME,
            ['Content-Type' => self::XLSX_CONTENT_TYPE],
        );
    }

    private function importedBy(array $claims): string
    {
        foreach (['nontri_id', 'name', 'given_name'] as $claim) {
            if (isset($claims[$claim]) && is_scalar($claims[$claim]) && trim((string) $claims[$claim]) !== '') {
                return mb_substr(trim((string) $claims[$claim]), 0, 150);
            }
        }

        return 'unknown';
    }
}
