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

    protected string $singularLabel;

    protected string $pluralLabel;

    public function index(): JsonResponse
    {
        $items = $this->newQuery()
            ->orderBy($this->nameField)
            ->orderBy('id')
            ->get()
            ->map(fn (Model $item): array => $this->responseData($item));

        return ApiResponse::success(
            $items,
            "Load {$this->pluralLabel} successfully"
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
            "Load {$this->singularLabel} successfully"
        );
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return $this->missingActorResponse();
        }

        $item = $this->newQuery()->create([
            ...$this->validateName($request),
            'status' => Status::ACTIVE,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            $this->responseData($item),
            "Create {$this->singularLabel} successfully",
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
            ...$this->validateName($request),
            'updated_by' => $actor,
        ]);

        return ApiResponse::success(
            $this->responseData($item->refresh()),
            "Update {$this->singularLabel} successfully"
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
            "Update {$this->singularLabel} status successfully"
        );
    }

    private function newQuery()
    {
        return $this->modelClass::query();
    }

    private function validateName(Request $request): array
    {
        return $request->validate([
            $this->nameField => [
                'required',
                'string',
                "max:{$this->nameMaxLength}",
            ],
        ]);
    }

    private function responseData(Model $item): array
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
            ucfirst($this->singularLabel).' not found',
            HttpStatus::NOT_FOUND['code']
        );
    }

    private function missingActorResponse(): JsonResponse
    {
        return ApiResponse::error(
            'JWT does not contain name, nontri_id, or sub',
            HttpStatus::UNPROCESSABLE_ENTITY['code']
        );
    }

    private function actorFromJwt(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $actor = $claims['nontri_id']
            ?? null;

        return is_scalar($actor) && trim((string) $actor) !== ''
            ? trim((string) $actor)
            : null;
    }
}
