<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListOfValueType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListOfValue\ListOfValueRequest;
use App\Services\ListOfValueService;
use Illuminate\Http\JsonResponse;

class ListOfValueController extends Controller
{
    public function index(
        ListOfValueRequest $request,
        ListOfValueType $type,
        ListOfValueService $service
    ): JsonResponse {
        return ApiResponse::success(
            $service->get($type, $request->validated()),
            'Load list of values successfully'
        );
    }
}
