<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Title\TitleWriteRequest;
use App\Http\Requests\Title\UpdateTitleStatusRequest;
use App\Http\Responses\TitleResponse;
use App\Models\Title;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TitleController extends Controller
{
    /**
     * API: GET /api/titles
     */
    public function index(): JsonResponse
    {
        $items = Title::query()
            ->orderBy('title_name_th')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            TitleResponse::collection($items)->resolve(),
            'Load titles successfully'
        );
    }

    /**
     * API: GET /api/titles/{id}
     */
    public function show(int $id): JsonResponse
    {
        $title = Title::query()->find($id);

        if ($title === null) {
            return ApiResponse::error('Title not found', 404);
        }

        return ApiResponse::success(
            (new TitleResponse($title))->resolve(),
            'Load title successfully'
        );
    }

    /**
     * API: POST /api/titles
     */
    public function store(TitleWriteRequest $request): JsonResponse
    {
        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                422
            );
        }

        $title = Title::query()->create([
            ...$request->validated(),
            'status' => Title::STATUS_ACTIVE,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            (new TitleResponse($title))->resolve(),
            'Create title successfully',
            201
        );
    }

    /**
     * API: PUT /api/titles/{id}
     */
    public function update(TitleWriteRequest $request, int $id): JsonResponse
    {
        $title = Title::query()->find($id);

        if ($title === null) {
            return ApiResponse::error('Title not found', 404);
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                422
            );
        }

        $title->update([
            ...$request->validated(),
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            (new TitleResponse($title->refresh()))->resolve(),
            'Update title successfully'
        );
    }

    /**
     * API: PATCH /api/titles/{id}/status
     */
    public function updateStatus(UpdateTitleStatusRequest $request, int $id): JsonResponse
    {
        $title = Title::query()->find($id);

        if ($title === null) {
            return ApiResponse::error('Title not found', 404);
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                422
            );
        }

        $title->update([
            ...$request->validated(),
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            (new TitleResponse($title->refresh()))->resolve(),
            'Update title status successfully'
        );
    }

    /**
     * API: DELETE /api/titles/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $title = Title::query()->find($id);

        if ($title === null) {
            return ApiResponse::error('Title not found', 404);
        }

        $title->delete();

        return ApiResponse::success(null, 'Delete title successfully');
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
