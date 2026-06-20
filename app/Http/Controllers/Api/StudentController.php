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

                'studentStatus',
                'admissionChannel',

                'highSchool',
                'highSchool.subdistrict',
                'highSchool.subdistrict.district',
                'highSchool.subdistrict.district.province',

                'studyPlan',
                'studyPlan.curriculum',
                'studyPlan.curriculum.program',
                'studyPlan.curriculum.program.department',
                'studyPlan.curriculum.program.department.faculty',

                'guardianTitle',
                'guardianRelationship',
            ])
            ->where('deleted_at', null);
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

                'studentStatus',

                'studyPlan',
                'studyPlan.curriculum',
                'studyPlan.curriculum.program',
                'studyPlan.curriculum.program.department',
                'studyPlan.curriculum.program.department.faculty',

                'notes',
                'notes.noteType',
            ])
            ->where('deleted_at', null);
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
     * - search_note optional
     *
     * search_text ใช้ค้นหาจาก:
     * - student_code
     * - first_name_th
     * - last_name_th
     * - student_id_card
     *
     * search_note ใช้ค้นหาจาก:
     * - notes.remark
     * - note_types.note
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
     * GET /api/students?search_note=ซึมเศร้า
     * GET /api/students?search_note=ขาดเรียน
     * GET /api/students?faculty_id=1&search_note=ซึมเศร้า
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

            $query->whereHas('studyPlan.curriculum.program.department', function ($q) use ($departmentId) {
                $q->where('id', $departmentId);
            });
        }

        if ($request->filled('faculty_id')) {
            $facultyId = $request->query('faculty_id');

            $query->whereHas('studyPlan.curriculum.program.department.faculty', function ($q) use ($facultyId) {
                $q->where('id', $facultyId);
            });
        }

        if ($request->filled('search_text')) {
            $searchText = trim((string) $request->query('search_text'));

            $query->where(function ($q) use ($searchText) {
                $q->where('student_code', 'like', '%' . $searchText . '%')
                    ->orWhere('first_name_th', 'like', '%' . $searchText . '%')
                    ->orWhere('last_name_th', 'like', '%' . $searchText . '%')
                    ->orWhere('student_id_card', 'like', '%' . $searchText . '%');
            });
        }

        if ($request->filled('search_note')) {
            $searchNote = trim((string) $request->query('search_note'));

            $query->whereHas('notes', function ($noteQuery) use ($searchNote) {
                $noteQuery->whereNull('deleted_at')
                    ->where(function ($q) use ($searchNote) {
                        $q->where('remark', 'like', '%' . $searchNote . '%')
                            ->orWhereHas('noteType', function ($noteTypeQuery) use ($searchNote) {
                                $noteTypeQuery->where('note', 'like', '%' . $searchNote . '%');
                            });
                    });
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

    /**
     * POST /api/students
     * Create a new student
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_code' => ['required', 'string'],
            'student_id_card' => ['required', 'string'],
            'title_id' => ['required', 'integer'],
            'first_name_th' => ['required', 'string'],
            'last_name_th' => ['required', 'string'],
            'first_name_en' => ['required', 'string'],
            'last_name_en' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'email' => ['required', 'email'],
            'teacher_id' => ['required', 'integer'],
            'student_status_id' => ['required', 'integer'],
            'admission_channel_id' => ['required', 'integer'],
            'high_school_id' => ['required', 'integer'],
            'study_plan_id' => ['required', 'integer'],
            'entry_year' => ['required', 'integer'],
            'gpa' => ['required', 'numeric'],
            'passed_credits' => ['nullable', 'integer'],
            'not_passed_credits' => ['nullable', 'integer'],
            'overed_credits' => ['nullable', 'integer'],

            'guardian_title_id' => ['required', 'integer'],
            'guardian_first_name_th' => ['required', 'string'],
            'guardian_last_name_th' => ['required', 'string'],
            'guardian_relationship_id' => ['required', 'integer'],
            'guardian_phone' => ['required', 'string'],
        ]);

        $item = Student::create($validated);

        $item = $this->studentDetailQuery()
            ->where('id', $item->id)
            ->first();

        return ApiResponse::success((new StudentDetailResponse($item))->resolve(), 'Create student successfully');
    }

    /**
     * PUT/PATCH /api/students/{id}
     * Update an existing student
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = Student::query()->find($id);

        if (!$item) {
            return ApiResponse::error('Student not found', 404);
        }

        $validated = $request->validate([
            'student_code' => ['sometimes', 'required', 'string'],
            'student_id_card' => ['sometimes', 'required', 'string'],
            'title_id' => ['sometimes', 'required', 'integer'],
            'first_name_th' => ['sometimes', 'required', 'string'],
            'last_name_th' => ['sometimes', 'required', 'string'],
            'first_name_en' => ['sometimes', 'required', 'string'],
            'last_name_en' => ['sometimes', 'required', 'string'],
            'phone' => ['sometimes', 'required', 'string'],
            'email' => ['sometimes', 'required', 'email'],
            'teacher_id' => ['sometimes', 'required', 'integer'],
            'student_status_id' => ['sometimes', 'required', 'integer'],
            'admission_channel_id' => ['sometimes', 'required', 'integer'],
            'high_school_id' => ['sometimes', 'required', 'integer'],
            'study_plan_id' => ['sometimes', 'required', 'integer'],
            'entry_year' => ['sometimes', 'required', 'integer'],
            'gpa' => ['sometimes', 'required', 'numeric'],
            'passed_credits' => ['sometimes', 'nullable', 'integer'],
            'not_passed_credits' => ['sometimes', 'nullable', 'integer'],
            'overed_credits' => ['sometimes', 'nullable', 'integer'],

            'guardian_title_id' => ['sometimes', 'required', 'integer'],
            'guardian_first_name_th' => ['sometimes', 'required', 'string'],
            'guardian_last_name_th' => ['sometimes', 'required', 'string'],
            'guardian_relationship_id' => ['sometimes', 'required', 'integer'],
            'guardian_phone' => ['sometimes', 'required', 'string'],
        ]);

        $item->update($validated);

        $item = $this->studentDetailQuery()
            ->where('id', $item->id)
            ->first();

        return ApiResponse::success((new StudentDetailResponse($item))->resolve(), 'Update student successfully');
    }

    /**
     * DELETE /api/students/{id}
     * Soft delete a student (set deleted_at)
     */
    public function destroy(int $id): JsonResponse
    {
        $item = Student::findOrFail($id);
        $item->delete();

        return ApiResponse::success(null, 'Delete student successfully');
    }
}