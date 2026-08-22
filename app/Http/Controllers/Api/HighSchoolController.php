<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Constants\Status;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\HighSchool\HighSchoolWriteRequest;
use App\Http\Requests\HighSchool\UpdateHighSchoolStatusRequest;
use App\Http\Responses\HighSchoolResponse;
use App\Models\HighSchool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HighSchoolController extends Controller
{
    /**
     * API: GET /api/high-schools
     */
    public function index(): JsonResponse
    {
        $items = HighSchool::query()
            ->with('subdistrict.district.province')
            ->orderBy('school_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            HighSchoolResponse::collection($items)->resolve(),
            'Load high schools successfully'
        );
    }

    /**
     * API: GET /api/high-schools/{id}
     */
    public function show(int $id): JsonResponse
    {
        $highSchool = HighSchool::query()
            ->with('subdistrict.district.province')
            ->find($id);

        if ($highSchool === null) {
            return ApiResponse::error('High school not found', HttpStatus::NOT_FOUND['code']);
        }

        return ApiResponse::success(
            (new HighSchoolResponse($highSchool))->resolve(),
            'Load high school successfully'
        );
    }

    /**
     * API: POST /api/high-schools
     */
    public function store(HighSchoolWriteRequest $request): JsonResponse
    {
        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            );
        }

        $highSchool = HighSchool::query()->create([
            ...$request->validated(),
            'status' => Status::ACTIVE,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        $highSchool->load('subdistrict.district.province');

        return ApiResponse::success(
            (new HighSchoolResponse($highSchool))->resolve(),
            'Create high school successfully',
            HttpStatus::CREATED['code']
        );
    }

    /**
     * API: PUT /api/high-schools/{id}
     */
    public function update(HighSchoolWriteRequest $request, int $id): JsonResponse
    {
        $highSchool = HighSchool::query()->find($id);

        if ($highSchool === null) {
            return ApiResponse::error('High school not found', HttpStatus::NOT_FOUND['code']);
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            );
        }

        $highSchool->update([
            ...$request->validated(),
            'updated_by' => $actor,
        ]);

        $highSchool->refresh()->load('subdistrict.district.province');

        return ApiResponse::success(
            (new HighSchoolResponse($highSchool))->resolve(),
            'Update high school successfully'
        );
    }

    /**
     * API: PATCH /api/high-schools/{id}/status
     */
    public function updateStatus(UpdateHighSchoolStatusRequest $request, int $id): JsonResponse
    {
        $highSchool = HighSchool::query()->find($id);

        if ($highSchool === null) {
            return ApiResponse::error('High school not found', HttpStatus::NOT_FOUND['code']);
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            );
        }

        $highSchool->update([
            ...$request->validated(),
            'updated_by' => $actor,
        ]);

        $highSchool->refresh()->load('subdistrict.district.province');

        return ApiResponse::success(
            (new HighSchoolResponse($highSchool))->resolve(),
            'Update high school status successfully'
        );
    }

    private function actorFromJwt(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $actor = $claims['name']
            ?? $claims['nontri_id']
            ?? $claims['sub']
            ?? null;

        return is_scalar($actor) && trim((string) $actor) !== ''
            ? trim((string) $actor)
            : null;
    }
}
