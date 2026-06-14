<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Http\Responses\StudentIndexResponse;
use App\Http\Responses\StudentListResponse;
use App\Http\Responses\StudentDetailResponse;

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

    private function studentQuery()
    {
        return Student::query()
            ->with([
                'title',

                'teacher',
                'teacher.title',

                'studyPlan',
                'studyPlan.curriculum',
                'studyPlan.curriculum.department',
                'studyPlan.curriculum.department.faculty',
            ])
            ->where('is_deleted', 0);
    }

    public function index(): JsonResponse
    {
        $items = Student::query()
            ->where('is_deleted', 0)
            ->orderBy('id')
            ->get();

        $data = StudentIndexResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load students successfully');
    }

    public function detail(): JsonResponse
    {
        $items = $this->studentDetailQuery()
            ->orderBy('id')
            ->get();

        $data = StudentDetailResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load student detail successfully');
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->studentDetailQuery()
            ->where('id', $id)
            ->first();

        if (!$item) {
            return ApiResponse::error('Student not found', 404);
        }

        $data = (new StudentDetailResponse($item))->resolve();

        return ApiResponse::success($data, 'Load student successfully');
    }

    public function advisor(Request $request): JsonResponse
    {
        $teacherId = $request->query('teacher_id');

        $query = $this->studentQuery()
            ->where('student_status_id', '!=', 2);

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        $items = $query->orderBy('id')->get();

        $data = StudentListResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load advisor students successfully');
    }

    public function advisorGraduated(Request $request): JsonResponse
    {
        $teacherId = $request->query('teacher_id');

        $query = $this->studentQuery()
            ->where('student_status_id', 2);

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        $items = $query->orderBy('id')->get();

        $data = StudentListResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load graduated advisor students successfully');
    }

    public function department(Request $request): JsonResponse
    {
        $departmentId = $request->query('department_id');

        $query = $this->studentQuery()
            ->where('student_status_id', '!=', 2);

        if ($departmentId) {
            $query->whereHas('studyPlan.curriculum.department', function ($q) use ($departmentId) {
                $q->where('id', $departmentId);
            });
        }

        $items = $query->orderBy('id')->get();

        $data = StudentListResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load department students successfully');
    }

    public function departmentGraduated(Request $request): JsonResponse
    {
        $departmentId = $request->query('department_id');

        $query = $this->studentQuery()
            ->where('student_status_id', 2);

        if ($departmentId) {
            $query->whereHas('studyPlan.curriculum.department', function ($q) use ($departmentId) {
                $q->where('id', $departmentId);
            });
        }

        $items = $query->orderBy('id')->get();

        $data = StudentListResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load graduated department students successfully');
    }

    public function faculty(Request $request): JsonResponse
    {
        $facultyId = $request->query('faculty_id');

        $query = $this->studentQuery()
            ->where('student_status_id', '!=', 2);

        if ($facultyId) {
            $query->whereHas('studyPlan.curriculum.department.faculty', function ($q) use ($facultyId) {
                $q->where('id', $facultyId);
            });
        }

        $items = $query->orderBy('id')->get();

        $data = StudentListResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load faculty students successfully');
    }

    public function facultyGraduated(Request $request): JsonResponse
    {
        $facultyId = $request->query('faculty_id');

        $query = $this->studentQuery()
            ->where('student_status_id', 2);

        if ($facultyId) {
            $query->whereHas('studyPlan.curriculum.department.faculty', function ($q) use ($facultyId) {
                $q->where('id', $facultyId);
            });
        }

        $items = $query->orderBy('id')->get();

        $data = StudentListResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load graduated faculty students successfully');
    }
}
