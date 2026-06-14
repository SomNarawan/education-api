<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Http\Responses\StudentListResponse;
use App\Http\Responses\StudentDetailResponse;

class StudentController extends Controller
{
    /**
     * Query สำหรับโหลดข้อมูลนิสิตแบบละเอียด
     * ใช้กับ:
     * - GET /api/students/detail
     * - GET /api/students/{id}
     */
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

    /**
     * Query สำหรับโหลดข้อมูลนิสิตแบบรายการ
     * ใช้กับ:
     * - GET /api/students
     */
    private function studentQuery()
    {
        return Student::query()
            ->with([
                'title',

                'teacher',
                'teacher.title',

                'studentStatus',

                'studyPlan',
                'studyPlan.curriculum',
                'studyPlan.curriculum.department',
                'studyPlan.curriculum.department.faculty',
            ])
            ->where('is_deleted', 0);
    }

    /**
     * GET /api/students
     *
     * โหลดรายชื่อนิสิตทั้งหมดแบบข้อมูลย่อ
     * และรองรับการ filter ผ่าน query params
     *
     * Query Params:
     * - teacher_id optional
     * - department_id optional
     * - faculty_id optional
     * - student_status_id optional
     * - search_text optional
     *
     * search_text ใช้ค้นหาจาก:
     * - student_code
     * - first_name_th
     * - last_name_th
     * - student_id_card
     *
     * Examples:
     * GET /api/students
     * GET /api/students?teacher_id=1
     * GET /api/students?teacher_id=1&student_status_id=2
     * GET /api/students?department_id=1
     * GET /api/students?department_id=1&student_status_id=2
     * GET /api/students?faculty_id=1
     * GET /api/students?faculty_id=1&student_status_id=2
     * GET /api/students?search_text=602050
     * GET /api/students?search_text=สม
     * GET /api/students?search_text=ใจ
     * GET /api/students?search_text=1234
     * GET /api/students?faculty_id=1&search_text=สม
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->studentQuery();

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->query('teacher_id'));
        }

        if ($request->filled('student_status_id')) {
            $query->where('student_status_id', $request->query('student_status_id'));
        }

        if ($request->filled('department_id')) {
            $departmentId = $request->query('department_id');

            $query->whereHas('studyPlan.curriculum.department', function ($q) use ($departmentId) {
                $q->where('id', $departmentId);
            });
        }

        if ($request->filled('faculty_id')) {
            $facultyId = $request->query('faculty_id');

            $query->whereHas('studyPlan.curriculum.department.faculty', function ($q) use ($facultyId) {
                $q->where('id', $facultyId);
            });
        }

        if ($request->filled('search_text')) {
            $searchText = trim($request->query('search_text'));

            $query->where(function ($q) use ($searchText) {
                $q->where('student_code', 'like', '%' . $searchText . '%')
                    ->orWhere('first_name_th', 'like', '%' . $searchText . '%')
                    ->orWhere('last_name_th', 'like', '%' . $searchText . '%')
                    ->orWhere('student_id_card', 'like', '%' . $searchText . '%');
            });
        }

        $items = $query->orderBy('id')->get();

        $data = StudentListResponse::collection($items)->resolve();

        return ApiResponse::success($data, 'Load students successfully');
    }

    /**
     * GET /api/students/{id}
     *
     * โหลดรายละเอียดนิสิตรายคน
     *
     * Example:
     * GET /api/students/1
     */
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
}