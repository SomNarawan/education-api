<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PortalMain\GetUserDetailsBulkRequest;
use App\Http\Requests\PortalMain\SearchNontriByAnyRequest;
use App\Http\Requests\PortalMain\SearchNontriRequest;
use App\Http\Responses\PortalMain\PortalMainStudentResponse;
use App\Services\PortalMain\PortalMainStudentService;
use Illuminate\Http\JsonResponse;

class PortalMainStudentController extends Controller
{
    public function __construct(private readonly PortalMainStudentService $service) {}

    /**
     * API: GET /api/portal-main-student/check-user/{nontriId}
     */
    public function checkUser(string $nontriId): JsonResponse
    {
        return response()->json([
            'nontriId' => $nontriId,
            'exists' => $this->service->nontriIdExists($nontriId),
        ]);
    }

    /**
     * API: GET /api/portal-main-student/get-user-data-by-nontri/{nontriId}
     */
    public function getUserDataByNontri(string $nontriId): JsonResponse
    {
        $student = $this->service->findByNontriId($nontriId);

        if ($student === null) {
            return response()->json(['message' => 'User not found'], HttpStatus::NOT_FOUND['code']);
        }

        return response()->json((new PortalMainStudentResponse($student, $nontriId))->resolve());
    }

    /**
     * API: POST /api/portal-main-student/get-user-data-list-by-nontri
     */
    public function getUserDataListByNontri(GetUserDetailsBulkRequest $request): JsonResponse
    {
        $studentsByNontriId = $this->service->findManyByNontriIds($request->validated('nontriIds'));

        $responses = collect($studentsByNontriId)
            ->map(fn ($student, string $nontriId) => (new PortalMainStudentResponse($student, $nontriId))->resolve())
            ->values();

        return response()->json($responses);
    }

    /**
     * API: GET /api/portal-main-student/search-nontri-by-any?search={keyword}
     */
    public function searchNontriByAny(SearchNontriByAnyRequest $request): JsonResponse
    {
        return response()->json([
            'nontriIds' => $this->service->searchNontriIdsByAnyKeyword($request->validated('search')),
        ]);
    }

    /**
     * API: GET /api/portal-main-student/search-nontri
     */
    public function searchNontri(SearchNontriRequest $request): JsonResponse
    {
        return response()->json([
            'nontriIds' => $this->service->searchNontriIdsByFields(
                $request->validated('nontriId'),
                $request->validated('fullName'),
                $request->validated('agency'),
            ),
        ]);
    }
}
