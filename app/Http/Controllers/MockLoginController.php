<?php

namespace App\Http\Controllers;

use App\Constants\HttpStatus;
use App\Services\JwtIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JsonException;

class MockLoginController extends Controller
{
    public function __construct()
    {
        abort_unless(
            config('mock_login.enabled') && ! app()->environment('production'),
            HttpStatus::NOT_FOUND['code']
        );
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

        $students = collect($this->mockStudents())
            ->filter(fn (array $student) => $q === ''
                || str_contains(mb_strtolower($student['student_code']), mb_strtolower($q))
                || str_contains(mb_strtolower($student['full_name_th']), mb_strtolower($q)))
            ->sortBy('full_name_th')
            ->take(20)
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
        $student = collect($this->mockStudents())->firstWhere('student_code', $studentCode);

        abort_unless(
            $student,
            HttpStatus::NOT_FOUND['code'],
            "ไม่พบ mock student student_code={$studentCode} — เช็คไฟล์ resources/mocks/mock-student-login.json"
        );

        return $this->redirectWithToken($issuer, [
            'nontri_id' => $student['student_code'],
            'name' => $student['full_name_th'],
            'role' => ['student'],
            'current_role' => 'student',
            'department_id' => $student['department_id'],
            'faculty_id' => $student['faculty_id'],
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
     * @return array<int, array{student_code: string, full_name_th: string, department_id: int|null, department_name: string|null, faculty_id: int|null}>
     */
    private function mockStudents(): array
    {
        $content = file_get_contents(resource_path('mocks/mock-student-login.json'));

        if ($content === false) {
            abort(500, 'ไม่สามารถอ่านไฟล์ resources/mocks/mock-student-login.json ได้');
        }

        try {
            $students = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException|\ValueError $exception) {
            abort(500, 'ไม่สามารถอ่านไฟล์ resources/mocks/mock-student-login.json ได้');
        }

        abort_unless(is_array($students), 500, 'รูปแบบไฟล์ resources/mocks/mock-student-login.json ไม่ถูกต้อง');

        return $students;
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
