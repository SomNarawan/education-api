<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Enums\ListOfValueType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListOfValue\ListOfValueRequest;
use App\Services\ListOfValueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListOfValueController extends Controller
{
    public function studentCreateForm(Request $request, ListOfValueService $service): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $roles = (array) ($claims['role'] ?? []);

        if (! in_array('admin', $roles, true)) {
            return ApiResponse::error(
                __('errors.auth.admin_student_create_options_only'),
                HttpStatus::FORBIDDEN['code']
            );
        }

        return ApiResponse::success(
            $service->studentCreateFormOptions(),
            __('success.list_of_values.loaded')
        );
    }

    public function index(
        ListOfValueRequest $request,
        ListOfValueType $type,
        ListOfValueService $service
    ): JsonResponse {
        return ApiResponse::success(
            $service->get($type, $request->validated()),
            __('success.list_of_values.loaded')
        );
    }
}
