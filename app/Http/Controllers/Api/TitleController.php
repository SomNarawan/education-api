<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Constants\Status;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Title\TitleWriteRequest;
use App\Http\Requests\Title\UpdateTitleStatusRequest;
use App\Http\Responses\TitleResponse;
use App\Models\Title;
use Illuminate\Http\JsonResponse;

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
            return ApiResponse::error('Title not found', HttpStatus::NOT_FOUND['code']);
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
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            );
        }

        $title = Title::query()->create([
            ...$request->validated(),
            'status' => Status::ACTIVE,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            (new TitleResponse($title))->resolve(),
            'Create title successfully',
            HttpStatus::CREATED['code']
        );
    }

    /**
     * API: PUT /api/titles/{id}
     */
    public function update(TitleWriteRequest $request, int $id): JsonResponse
    {
        $title = Title::query()->find($id);

        if ($title === null) {
            return ApiResponse::error('Title not found', HttpStatus::NOT_FOUND['code']);
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
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
            return ApiResponse::error('Title not found', HttpStatus::NOT_FOUND['code']);
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
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
}
