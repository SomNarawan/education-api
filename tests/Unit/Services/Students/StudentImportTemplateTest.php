<?php

namespace Tests\Unit\Services\Students;

use App\Actions\Students\SaveStudent;
use App\Enums\ListOfValueType;
use App\Services\Excel\XlsxDataValidationWriter;
use App\Services\ListOfValueService;
use App\Services\Students\StudentImportService;
use App\Services\StudyPlans\StudentStudyPlanService;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Shuchkin\SimpleXLSX;
use Tests\TestCase;
use ZipArchive;

class StudentImportTemplateTest extends TestCase
{
    public function test_template_contains_master_data_and_dropdown_validations(): void
    {
        $path = $this->createTemplate();
        $xlsx = SimpleXLSX::parse($path);

        $this->assertInstanceOf(SimpleXLSX::class, $xlsx);
        $this->assertSame(['Sheet1', 'Master Data'], $xlsx->sheetNames());
        $this->assertSame([
            'คำนำหน้า',
            'อาจารย์ที่ปรึกษา',
            'ช่องทางรับเข้า',
            'โรงเรียน ม.ปลาย',
            'ความสัมพันธ์',
            'สถานะปัจจุบัน',
        ], $xlsx->rows(1)[0]);
        $this->assertSame([
            'นาย',
            'อ. สมชาย',
            'TCAS',
            'โรงเรียนสาธิต',
            'บิดา',
            'กำลังศึกษา',
        ], $xlsx->rows(1)[1]);

        $this->assertSame([
            'student_titles' => "'Master Data'!\$A\$2:\$A\$2",
            'student_advisors' => "'Master Data'!\$B\$2:\$B\$2",
            'student_admission_channels' => "'Master Data'!\$C\$2:\$C\$2",
            'student_high_schools' => "'Master Data'!\$D\$2:\$D\$2",
            'student_relationships' => "'Master Data'!\$E\$2:\$E\$2",
            'student_statuses' => "'Master Data'!\$F\$2:\$F\$2",
        ], $this->definedNames($path));
        $expectedValidations = [
            'C3:C1002' => 'student_titles',
            'K3:K1002' => 'student_advisors',
            'L3:L1002' => 'student_admission_channels',
            'M3:M1002' => 'student_high_schools',
            'N3:N1002' => 'student_titles',
            'Q3:Q1002' => 'student_relationships',
            'S3:S1002' => 'student_statuses',
        ];
        $actualValidations = $this->validations($path);
        ksort($expectedValidations);
        ksort($actualValidations);

        $this->assertSame($expectedValidations, $actualValidations);
    }

    public function test_template_handles_empty_lists_and_xml_special_characters(): void
    {
        $specialAdmissionChannel = 'TCAS & Portfolio <พิเศษ> "รอบ 1"';
        $path = $this->createTemplate([
            ListOfValueType::CurriculumPersonnel->value => [],
            ListOfValueType::HighSchools->value => [],
            ListOfValueType::AdmissionChannels->value => [
                ['id' => 3, 'name_th' => $specialAdmissionChannel, 'name_en' => null],
            ],
        ]);
        $xlsx = SimpleXLSX::parse($path);

        $this->assertInstanceOf(SimpleXLSX::class, $xlsx);
        $this->assertSame($specialAdmissionChannel, $xlsx->rows(1)[1][2]);
        $this->assertSame(
            "'Master Data'!\$B\$2:\$B\$2",
            $this->definedNames($path)['student_advisors'],
        );
        $this->assertSame(
            "'Master Data'!\$D\$2:\$D\$2",
            $this->definedNames($path)['student_high_schools'],
        );
    }

    public function test_validation_writer_rejects_a_non_xlsx_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('not-an-xlsx.txt', 'plain text');

        $this->expectException(RuntimeException::class);

        app(XlsxDataValidationWriter::class)->apply(
            Storage::disk('local')->path('not-an-xlsx.txt'),
            ['student_titles' => "'Master Data'!\$A\$2:\$A\$2"],
            ['C3:C1002' => 'student_titles'],
        );
    }

    private function createTemplate(array $overrides = []): string
    {
        Storage::fake('local');

        $options = array_replace([
            ListOfValueType::Titles->value => [
                ['id' => 1, 'name_th' => 'นาย', 'name_en' => 'Mr.'],
            ],
            ListOfValueType::CurriculumPersonnel->value => [
                ['id' => 'teacher-1', 'name_th' => 'อ. สมชาย', 'name_en' => null],
            ],
            ListOfValueType::AdmissionChannels->value => [
                ['id' => 2, 'name_th' => 'TCAS', 'name_en' => null],
            ],
            ListOfValueType::HighSchools->value => [
                ['id' => 3, 'name_th' => 'โรงเรียนสาธิต', 'name_en' => null],
            ],
            ListOfValueType::Relationships->value => [
                ['id' => 4, 'name_th' => 'บิดา', 'name_en' => null],
            ],
            ListOfValueType::StudentStatuses->value => [
                ['id' => 5, 'name_th' => 'กำลังศึกษา', 'name_en' => null],
            ],
        ], $overrides);

        $listOfValues = $this->mock(ListOfValueService::class);
        $listOfValues->shouldReceive('get')
            ->andReturnUsing(function (ListOfValueType $type, array $filters = []) use ($options) {
                if ($type === ListOfValueType::CurriculumPersonnel) {
                    $this->assertSame(['curriculum_id' => 123], $filters);
                }

                return collect($options[$type->value]);
            });
        $this->mock(SaveStudent::class);
        $this->mock(StudentStudyPlanService::class);

        return app(StudentImportService::class)->createTemplate(123);
    }

    private function definedNames(string $path): array
    {
        $xml = $this->zipEntry($path, 'xl/workbook.xml');
        $document = new DOMDocument;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $names = [];

        foreach ($xpath->query('//*[local-name()="definedName"]') as $node) {
            $names[$node->attributes->getNamedItem('name')->nodeValue] = $node->textContent;
        }

        return $names;
    }

    private function validations(string $path): array
    {
        $xml = $this->zipEntry($path, 'xl/worksheets/sheet1.xml');
        $document = new DOMDocument;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $validations = [];

        foreach ($xpath->query('//*[local-name()="dataValidation"]') as $node) {
            $formula = $xpath->query('./*[local-name()="formula1"]', $node)->item(0);
            $validations[$node->attributes->getNamedItem('sqref')->nodeValue] = $formula->textContent;

            $this->assertSame('1', $node->attributes->getNamedItem('allowBlank')->nodeValue);
            $this->assertSame('1', $node->attributes->getNamedItem('showErrorMessage')->nodeValue);
        }

        return $validations;
    }

    private function zipEntry(string $path, string $entry): string
    {
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path) === true);
        $contents = $archive->getFromName($entry);
        $archive->close();
        $this->assertIsString($contents);

        return $contents;
    }
}
