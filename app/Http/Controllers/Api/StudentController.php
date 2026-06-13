<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;

class StudentController extends Controller
{
    private function studentDetailQuery()
    {
        return Student::query()
            ->with([
                'title',

                'teacher',
                'teacher.title',

                'studentStatus',
                'admissionChannel',

                'highSchool',
                'highSchool.subdistrict',
                'highSchool.subdistrict.district',
                'highSchool.subdistrict.district.province',

                'studyPlan',
                'studyPlan.curriculum',
                'studyPlan.curriculum.department',
                'studyPlan.curriculum.department.faculty',

                'guardianTitle',
                'guardianRelationship',
            ])
            ->where('is_deleted', 0);
    }

    public function index(): JsonResponse
    {
        $items = Student::query()
            ->where('is_deleted', 0)
            ->orderBy('id')
            ->get();

        return ApiResponse::success($items, 'Load students successfully');
    }

    public function detail(): JsonResponse
    {
        $items = $this->studentDetailQuery()
            ->orderBy('id')
            ->get();

        return ApiResponse::success($items, 'Load student detail successfully');
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->studentDetailQuery()
            ->where('id', $id)
            ->first();

        if (!$item) {
            return ApiResponse::error('Student not found', 404);
        }

        return ApiResponse::success($item, 'Load student successfully');
    }

    public function advisor(Request $request): JsonResponse
    {
        $teacherId = $request->query('teacher_id');

        $query = $this->studentDetailQuery()
            ->where('student_status_id', '!=', 2);

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        $items = $query->orderBy('id')->get();

        return ApiResponse::success($items, 'Load advisor students successfully');
    }

    public function advisorGraduated(Request $request): JsonResponse
    {
        $teacherId = $request->query('teacher_id');

        $query = $this->studentDetailQuery()
            ->where('student_status_id', 2);

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        $items = $query->orderBy('id')->get();

        return ApiResponse::success($items, 'Load graduated advisor students successfully');
    }

    public function department(Request $request): JsonResponse
    {
        $departmentId = $request->query('department_id');

        $query = $this->studentDetailQuery()
            ->where('student_status_id', '!=', 2);

        if ($departmentId) {
            $query->whereHas('studyPlan.curriculum.department', function ($q) use ($departmentId) {
                $q->where('id', $departmentId);
            });
        }

        $items = $query->orderBy('id')->get();

        return ApiResponse::success($items, 'Load department students successfully');
    }

    public function departmentGraduated(Request $request): JsonResponse
    {
        $departmentId = $request->query('department_id');

        $query = $this->studentDetailQuery()
            ->where('student_status_id', 2);

        if ($departmentId) {
            $query->whereHas('studyPlan.curriculum.department', function ($q) use ($departmentId) {
                $q->where('id', $departmentId);
            });
        }

        $items = $query->orderBy('id')->get();

        return ApiResponse::success($items, 'Load graduated department students successfully');
    }

    public function faculty(Request $request): JsonResponse
    {
        $facultyId = $request->query('faculty_id');

        $query = $this->studentDetailQuery()
            ->where('student_status_id', '!=', 2);

        if ($facultyId) {
            $query->whereHas('studyPlan.curriculum.department.faculty', function ($q) use ($facultyId) {
                $q->where('id', $facultyId);
            });
        }

        $items = $query->orderBy('id')->get();

        return ApiResponse::success($items, 'Load faculty students successfully');
    }

    public function facultyGraduated(Request $request): JsonResponse
    {
        $facultyId = $request->query('faculty_id');

        $query = $this->studentDetailQuery()
            ->where('student_status_id', 2);

        if ($facultyId) {
            $query->whereHas('studyPlan.curriculum.department.faculty', function ($q) use ($facultyId) {
                $q->where('id', $facultyId);
            });
        }

        $items = $query->orderBy('id')->get();

        return ApiResponse::success($items, 'Load graduated faculty students successfully');
    }
}