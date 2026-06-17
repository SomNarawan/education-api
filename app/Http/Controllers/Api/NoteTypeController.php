<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\NoteType;
use Illuminate\Http\JsonResponse;

class NoteTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $items = NoteType::all();

        return ApiResponse::success($items, 'Load note types successfully');
    }
}