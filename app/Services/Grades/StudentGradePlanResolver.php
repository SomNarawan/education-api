<?php

namespace App\Services\Grades;

use App\Contracts\CmisApi;
use App\Models\Student;
use RuntimeException;

class StudentGradePlanResolver
{
    private array $plans = [];

    public function __construct(private readonly CmisApi $cmisApi) {}

    public function resolve(?Student $student, int $curriculumId): array
    {
        if ($student === null) {
            throw new RuntimeException('ไม่พบข้อมูลนิสิตในระบบ');
        }

        if ((int) $student->curriculum_id !== $curriculumId) {
            throw new RuntimeException('นิสิตไม่ได้อยู่ในหลักสูตรที่เลือก');
        }

        $studyPlanId = (int) $student->study_plan_id;

        if ($studyPlanId < 1) {
            throw new RuntimeException('นิสิตยังไม่มีข้อมูลแผนการเรียน');
        }

        $this->plans[$studyPlanId] ??= $this->cmisApi->getCurriculumPlanCourses($studyPlanId);

        return [
            'study_plan_id' => $studyPlanId,
            'courses' => $this->plans[$studyPlanId],
        ];
    }
}
