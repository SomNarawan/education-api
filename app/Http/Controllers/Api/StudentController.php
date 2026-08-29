<?php

namespace App\Http\Controllers\Api;

use App\Actions\Students\SaveStudent;
use App\Actions\Students\UpdateStudentAdvisor;
use App\Constants\HttpStatus;
use App\Constants\Status;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ListStudentsRequest;
use App\Http\Requests\Student\ListStudyingStudentsRequest;
use App\Http\Requests\Student\StudentWriteRequest;
use App\Http\Requests\Student\UpdateStudentAdvisorRequest;
use App\Http\Responses\StudentDetailResponse;
use App\Http\Responses\StudentListResponse;
use App\Http\Responses\StudentWithoutAdvisorResponse;
use App\Models\Student;
use App\Services\Students\StudentQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentQueryService $studentQuery,
        private readonly SaveStudent $saveStudent,
        private readonly UpdateStudentAdvisor $updateStudentAdvisor,
    ) {}

    /**
     * API: GET /api/students
     */
    public function index(ListStudentsRequest $request): JsonResponse
    {
        $students = $this->studentQuery->list($request->validated());

        return ApiResponse::success(
            StudentListResponse::collection($students)->resolve(),
            'Load students successfully'
        );
    }

    /**
     * API: GET /api/students/studying?teacher_id={id}
     */
    public function studying(ListStudyingStudentsRequest $request): JsonResponse
    {
        $students = $this->studentQuery->list($request->validated());

        return ApiResponse::success(
            StudentListResponse::collection($students)->resolve(),
            'Load studying students successfully'
        );
    }

    /**
     * API: GET /api/students/studying/without-advisor?department_id={id}
     */
    public function studyingWithoutAdvisor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('system_departments', 'id')->where('status', Status::ACTIVE),
            ],
        ]);

        $students = Student::query()
            ->with('title')
            ->studying()
            ->where('system_department_id', $validated['department_id'])
            ->whereNull('teacher_id')
            ->orderBy('student_code')
            ->get();

        return ApiResponse::success(
            StudentWithoutAdvisorResponse::collection($students)->resolve(),
            'Load studying students without advisor successfully'
        );
    }

    /**
     * API: PATCH /api/students/advisor
     */
    public function updateAdvisor(UpdateStudentAdvisorRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->updateStudentAdvisor->execute($request->validated()),
            'Update student advisors successfully'
        );
    }

    /**
     * API: GET /api/students/{id}
     */
    public function show(int $id): JsonResponse
    {
        $student = $this->studentQuery->detail($id);

        if ($student === null) {
            return ApiResponse::error('Student not found', HttpStatus::NOT_FOUND['code']);
        }

        return ApiResponse::success(
            (new StudentDetailResponse($student))->resolve(),
            'Load student successfully'
        );
    }

    /**
     * API: POST /api/students
     */
    public function store(StudentWriteRequest $request): JsonResponse
    {
        $student = $this->saveStudent->create($request->validated());
        $student = $this->studentQuery->detail($student->id);

        return ApiResponse::success(
            (new StudentDetailResponse($student))->resolve(),
            'Create student successfully',
            HttpStatus::CREATED['code']
        );
    }

    /**
     * API: PUT|PATCH /api/students/{id}
     */
    public function update(StudentWriteRequest $request, int $id): JsonResponse
    {
        $student = Student::query()->find($id);

        if ($student === null) {
            return ApiResponse::error('Student not found', HttpStatus::NOT_FOUND['code']);
        }

        $this->saveStudent->update($student, $request->validated());
        $student = $this->studentQuery->detail($student->id);

        return ApiResponse::success(
            (new StudentDetailResponse($student))->resolve(),
            'Update student successfully'
        );
    }

    /**
     * API: DELETE /api/students/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $student = Student::query()->find($id);

        if ($student === null) {
            return ApiResponse::error('Student not found', HttpStatus::NOT_FOUND['code']);
        }

        $student->delete();

        return ApiResponse::success(null, 'Delete student successfully');
    }
}
