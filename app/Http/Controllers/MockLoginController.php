<?php

namespace App\Http\Controllers;

use App\Constants\HttpStatus;
use App\Models\SystemTeacher;
use App\Services\JwtIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $systemTeachers = SystemTeacher::query()
            ->when($q !== '', fn ($query) => $query
                ->where('nontri_id', 'like', "%{$q}%")
                ->orWhere('full_name_th', 'like', "%{$q}%"))
            ->orderBy('full_name_th')
            ->limit(20)
            ->get(['nontri_id', 'full_name_th', 'department_id'])
            ->map(fn ($systemTeacher) => [
                ...$systemTeacher->toArray(),
                'is_admin' => in_array($systemTeacher->nontri_id, config('mock_login.admin_nontri_ids'), true),
            ]);

        return response()->json($systemTeachers);
    }

    public function issueAdmin(JwtIssuer $issuer): RedirectResponse
    {
        return $this->redirectWithToken($issuer, [
            'nontri_id' => 'mock-admin',
            'name' => 'Mock Admin',
            'role' => ['admin'],
            'current_role' => 'admin',
            'department_id' => null,
        ]);
    }

    public function issueSystemTeacher(Request $request, string $nontriId, JwtIssuer $issuer): RedirectResponse
    {
        $systemTeacher = SystemTeacher::where('nontri_id', $nontriId)->first();

        abort_unless(
            $systemTeacher,
            HttpStatus::NOT_FOUND['code'],
            "ไม่พบ system teacher nontri_id={$nontriId} — เช็คว่า sync แล้วหรือพิมพ์ผิด"
        );

        $isAdmin = $request->boolean('admin')
            || in_array($systemTeacher->nontri_id, config('mock_login.admin_nontri_ids'), true);

        $role = $isAdmin ? ['teacher', 'admin'] : ['teacher'];

        return $this->redirectWithToken($issuer, [
            'nontri_id' => $systemTeacher->nontri_id,
            'name' => $systemTeacher->full_name_th,
            'role' => $role,
            'current_role' => 'teacher',
            'department_id' => $systemTeacher->department_id,
        ]);
    }

    private function redirectWithToken(JwtIssuer $issuer, array $claims): RedirectResponse
    {
        $claims += [
            'iat' => time(),
            'exp' => time() + 8 * 3600,
        ];

        $frontendUrl = rtrim((string) config('mock_login.frontend_url'), '/');
        $token = $issuer->issue($claims);

        return redirect("{$frontendUrl}/auth/callback?token={$token}");
    }
}
