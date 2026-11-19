<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Constants\Status;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

abstract class MasterDataController extends Controller
{
    /** @var class-string<Model> */
    protected string $modelClass;

    protected string $nameField;

    protected int $nameMaxLength;

    public function index(): JsonResponse
    {
        $items = $this->newQuery()
            ->orderBy($this->nameField)
            ->orderBy('id')
            ->get()
            ->map(fn (Model $item): array => $this->responseData($item));

        return ApiResponse::success(
            $items,
            __('success.master_data.list_loaded', ['resource' => $this->resourceName()])
        );
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->newQuery()->find($id);

        if ($item === null) {
            return $this->notFoundResponse();
        }

        return ApiResponse::success(
            $this->responseData($item),
            __('success.master_data.loaded', ['resource' => $this->resourceName()])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return $this->missingActorResponse();
        }

        $item = $this->newQuery()->create([
            ...$request->validate($this->writeRules()),
            'status' => Status::ACTIVE,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            $this->responseData($item),
            __('success.master_data.created', ['resource' => $this->resourceName()]),
            HttpStatus::CREATED['code']
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->newQuery()->find($id);

        if ($item === null) {
            return $this->notFoundResponse();
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return $this->missingActorResponse();
        }

        $item->update([
            ...$request->validate($this->writeRules($id)),
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            $this->responseData($item->refresh()),
            __('success.master_data.updated', ['resource' => $this->resourceName()])
        );
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(Status::activeStatuses()),
            ],
        ]);

        $item = $this->newQuery()->find($id);

        if ($item === null) {
            return $this->notFoundResponse();
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return $this->missingActorResponse();
        }

        $item->update([
            ...$validated,
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            $this->responseData($item->refresh()),
            __('success.master_data.status_updated', ['resource' => $this->resourceName()])
        );
    }

    private function newQuery()
    {
        return $this->modelClass::query();
    }

    private function resourceName(): string
    {
        return __('success.master_data.resources.'.$this->nameField);
    }

    protected function writeRules(?int $ignoreId = null): array
    {
        $table = (new $this->modelClass)->getTable();

        return [
            $this->nameField => [
                'required',
                'string',
                "max:{$this->nameMaxLength}",
                Rule::unique($table, $this->nameField)->ignore($ignoreId),
            ],
        ];
    }

    protected function responseData(Model $item): array
    {
        return [
            'id' => (int) $item->getKey(),
            $this->nameField => $item->getAttribute($this->nameField),
            'status' => $item->getAttribute('status'),
            'created_at' => $item->getAttribute('created_at'),
            'created_by' => $item->getAttribute('created_by'),
            'updated_at' => $item->getAttribute('updated_at'),
            'updated_by' => $item->getAttribute('updated_by'),
        ];
    }

    private function notFoundResponse(): JsonResponse
    {
        return ApiResponse::error(
            __('errors.master_data.not_found.'.$this->nameField),
            HttpStatus::NOT_FOUND['code']
        );
    }

    private function missingActorResponse(): JsonResponse
    {
        return ApiResponse::error(
            __('errors.common.actor_missing'),
            HttpStatus::UNPROCESSABLE_ENTITY['code']
        );
    }
}
