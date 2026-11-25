<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Students\AdvisorHistoryService;
use App\Services\Students\AdvisorSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvisorSummaryController extends Controller
{
    public function __construct(
        private readonly AdvisorSummaryService $summary,
        private readonly AdvisorHistoryService $history,
    ) {}

    /**
     * API: GET /api/students/advisor-summary[?teacher_id={nontri_id}]
     *
     * Counts of the advisees now. A teacher always gets their own students (the
     * query is ignored). An admin gets the list of advisors and the summary of
     * the chosen one, the first advisor of the list when none is chosen.
     */
    public function overview(Request $request): JsonResponse
    {
        $advisor = $this->advisorOf($request);
        if ($advisor instanceof JsonResponse) {
            return $advisor;
        }

        [$teacherId, $advisors] = $advisor;
        $data = $this->summary->overview($teacherId);

        if ($advisors !== null) {
            $data['advisors'] = $advisors;
        }

        return ApiResponse::success($data, __('success.advisor_summary.loaded'));
    }

    /** API: GET /api/students/advisor-summary/history — GPA by study year and plan status counts per term. */
    public function history(Request $request): JsonResponse
    {
        $advisor = $this->advisorOf($request);
        if ($advisor instanceof JsonResponse) {
            return $advisor;
        }

        return ApiResponse::success(
            $this->history->summarize($this->summary->studentRows($advisor[0])),
            __('success.advisor_summary.loaded')
        );
    }

    /** API: GET /api/students/advisor-summary/history/{academicYear}/{semester} — the students of one term. */
    public function termStudents(Request $request, int $academicYear, int $semester): JsonResponse
    {
        $advisor = $this->advisorOf($request);
        if ($advisor instanceof JsonResponse) {
            return $advisor;
        }

        return ApiResponse::success(
            ['students' => $this->history->termStudents($this->summary->studentRows($advisor[0]), $academicYear, $semester)],
            __('success.advisor_summary.loaded')
        );
    }

    /**
     * @return array{0: string|null, 1: list<array<string, mixed>>|null}|JsonResponse
     */
    private function advisorOf(Request $request): array|JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $claims = $request->attributes->get('jwt_claims', []);
        $currentRole = $claims['current_role'] ?? null;

        if ($currentRole === 'admin') {
            $advisors = $this->summary->advisors();

            return [$validated['teacher_id'] ?? ($advisors[0]['teacher_id'] ?? null), $advisors];
        }

        if ($currentRole === 'teacher') {
            $teacherId = $claims['nontri_id'] ?? null;

            if (! is_string($teacherId) || $teacherId === '') {
                return ApiResponse::error(
                    __('errors.auth.nontri_id_missing'),
                    HttpStatus::FORBIDDEN['code']
                );
            }

            return [$teacherId, null];
        }

        return ApiResponse::error(
            __('errors.auth.teacher_admin_only'),
            HttpStatus::FORBIDDEN['code']
        );
    }
}
