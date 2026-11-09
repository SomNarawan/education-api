<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Students\AdvisorSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvisorSummaryController extends Controller
{
    public function __construct(private readonly AdvisorSummaryService $summary) {}

    /**
     * API: GET /api/students/advisor-summary[?teacher_id={nontri_id}]
     *
     * A teacher always gets their own students (the query is ignored). An admin
     * gets the list of advisors and the summary of the chosen one, the first
     * advisor of the list when none is chosen.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $claims = $request->attributes->get('jwt_claims', []);
        $currentRole = $claims['current_role'] ?? null;
        $advisors = null;

        if ($currentRole === 'admin') {
            $advisors = $this->summary->advisors();
            $teacherId = $validated['teacher_id'] ?? ($advisors[0]['teacher_id'] ?? null);
        } elseif ($currentRole === 'teacher') {
            $teacherId = $claims['nontri_id'] ?? null;

            if (! is_string($teacherId) || $teacherId === '') {
                return ApiResponse::error(
                    'JWT does not contain nontri_id',
                    HttpStatus::FORBIDDEN['code']
                );
            }
        } else {
            return ApiResponse::error(
                'This endpoint is available to teachers and admins only',
                HttpStatus::FORBIDDEN['code']
            );
        }

        $data = $this->summary->summarize($teacherId);

        if ($advisors !== null) {
            $data['advisors'] = $advisors;
        }

        return ApiResponse::success($data, 'Load advisor summary successfully');
    }
}
