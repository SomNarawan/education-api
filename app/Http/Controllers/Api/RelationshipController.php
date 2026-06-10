<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Relationship;
use Illuminate\Http\JsonResponse;

class RelationshipController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Relationship::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load relationships successfully'
        );
    }
}