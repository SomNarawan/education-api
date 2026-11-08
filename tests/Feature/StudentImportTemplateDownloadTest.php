<?php

namespace Tests\Feature;

use App\Services\Students\StudentImportService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentImportTemplateDownloadTest extends TestCase
{
    public function test_template_download_requires_curriculum_id(): void
    {
        $this->mock(StudentImportService::class)
            ->shouldNotReceive('createTemplate');

        $this->withoutMiddleware()
            ->getJson('/api/students/import/template')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('curriculum_id');
    }

    public function test_template_download_rejects_non_positive_curriculum_id(): void
    {
        $this->mock(StudentImportService::class)
            ->shouldNotReceive('createTemplate');

        $this->withoutMiddleware()
            ->getJson('/api/students/import/template?curriculum_id=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('curriculum_id');
    }

    public function test_template_download_passes_curriculum_id_to_service(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('student-template.xlsx', 'xlsx');

        $this->mock(StudentImportService::class)
            ->shouldReceive('createTemplate')
            ->once()
            ->with(123)
            ->andReturn(Storage::disk('local')->path('student-template.xlsx'));

        $this->withoutMiddleware()
            ->get('/api/students/import/template?curriculum_id=123')
            ->assertOk()
            ->assertDownload('Import Student Template.xlsx');
    }
}
