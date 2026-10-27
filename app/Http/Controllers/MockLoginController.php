<?php

namespace App\Http\Controllers;

use App\Constants\HttpStatus;
use App\Models\Student;
use App\Services\JwtIssuer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JsonException;

class MockLoginController extends Controller
{
    public function __construct()
    {
        abort_unless(config('mock_login.enabled'), HttpStatus::NOT_FOUND['code']);
    }

    public function picker()
    {
        return view('mock-login');
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $systemTeachers = collect($this->mockUsers())
            ->filter(fn (array $systemTeacher) => $q === ''
                || str_contains(mb_strtolower($systemTeacher['nontri_id']), mb_strtolower($q))
                || str_contains(mb_strtolower($systemTeacher['full_name_th']), mb_strtolower($q)))
            ->sortBy('full_name_th')
            ->take(20)
            ->values();

        return response()->json($systemTeachers);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $students = Student::query()
            ->with('systemDepartment.systemFaculty')
            ->when($terms !== [], function (Builder $query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->where(function (Builder $termQuery) use ($term): void {
                        $pattern = "%{$term}%";

                        $termQuery
                            ->where('student_code', 'like', $pattern)
                            ->orWhere('first_name_th', 'like', $pattern)
                            ->orWhere('last_name_th', 'like', $pattern);
                    });
                }
            })
            ->orderBy('first_name_th')
            ->orderBy('last_name_th')
            ->limit(20)
            ->get()
            ->map(fn (Student $student): array => $this->studentLoginData($student))
            ->values();

        return response()->json($students);
    }

    public function issueAdmin(JwtIssuer $issuer): RedirectResponse
    {
        return $this->redirectWithToken($issuer, [
            'nontri_id' => 'mock-admin',
            'name' => 'Mock Admin',
            'role' => ['admin'],
            'current_role' => 'admin',
            'department_id' => null,
            'faculty_id' => null,
        ]);
    }

    public function issueSystemTeacher(Request $request, string $nontriId, JwtIssuer $issuer): RedirectResponse
    {
        $systemTeacher = collect($this->mockUsers())->firstWhere('nontri_id', $nontriId);

        abort_unless(
            $systemTeacher,
            HttpStatus::NOT_FOUND['code'],
            "ไม่พบ mock user nontri_id={$nontriId} — เช็คไฟล์ resources/mocks/mock-login.json"
        );

        $isAdmin = $request->boolean('admin')
            || $systemTeacher['is_admin'];

        $role = $isAdmin ? ['teacher', 'admin'] : ['teacher'];

        return $this->redirectWithToken($issuer, [
            'nontri_id' => $systemTeacher['nontri_id'],
            'name' => $systemTeacher['full_name_th'],
            'role' => $role,
            'current_role' => 'teacher',
            'department_id' => $systemTeacher['department_id'],
            'faculty_id' => $systemTeacher['faculty_id'],
        ]);
    }

    public function issueStudent(string $studentCode, JwtIssuer $issuer): RedirectResponse
    {
        $student = Student::query()
            ->with('systemDepartment.systemFaculty')
            ->where('student_code', $studentCode)
            ->first();

        abort_unless(
            $student,
            HttpStatus::NOT_FOUND['code'],
            "ไม่พบนิสิตรหัส {$studentCode} ในตาราง students"
        );

        $studentData = $this->studentLoginData($student);

        return $this->redirectWithToken($issuer, [
            'nontri_id' => $studentData['student_code'],
            'name' => $studentData['full_name_th'],
            'role' => ['student'],
            'current_role' => 'student',
            'department_id' => $studentData['department_id'],
            'faculty_id' => $studentData['faculty_id'],
            'study_plan_id' => $studentData['study_plan_id'],
        ], (string) config('mock_login.student_frontend_url'));
    }

    /**
     * @return array<int, array{nontri_id: string, full_name_th: string, department_id: int|null, faculty_id: int|null, is_admin: bool}>
     */
    private function mockUsers(): array
    {
        $content = file_get_contents(resource_path('mocks/mock-login.json'));

        if ($content === false) {
            abort(500, 'ไม่สามารถอ่านไฟล์ resources/mocks/mock-login.json ได้');
        }

        try {
            $users = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException|\ValueError $exception) {
            abort(500, 'ไม่สามารถอ่านไฟล์ resources/mocks/mock-login.json ได้');
        }

        abort_unless(is_array($users), 500, 'รูปแบบไฟล์ resources/mocks/mock-login.json ไม่ถูกต้อง');

        return $users;
    }

    /**
     * @return array{student_code: string, full_name_th: string, department_id: int|null, department_name: string|null, faculty_id: int|null, study_plan_id: int}
     */
    private function studentLoginData(Student $student): array
    {
        return [
            'student_code' => (string) $student->student_code,
            'full_name_th' => trim(
                ($student->first_name_th ?? '')
                .' '
                .($student->last_name_th ?? '')
            ),
            'department_id' => $student->system_department_id,
            'department_name' => $student->systemDepartment?->th_name,
            'faculty_id' => $student->systemDepartment?->system_faculty_id,
            'study_plan_id' => (int) $student->study_plan_id,
        ];
    }

    private function redirectWithToken(
        JwtIssuer $issuer,
        array $claims,
        ?string $frontendUrl = null
    ): RedirectResponse {
        $claims += [
            'iat' => time(),
            'exp' => time() + 8 * 3600,
        ];

        $frontendUrl = rtrim(
            $frontendUrl ?? (string) config('mock_login.frontend_url'),
            '/'
        );
        $token = $issuer->issue($claims);

        return redirect("{$frontendUrl}/auth/callback?token={$token}");
    }
}
