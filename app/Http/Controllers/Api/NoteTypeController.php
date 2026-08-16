<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\NoteType;
use Illuminate\Http\JsonResponse;

class NoteTypeController extends Controller
{
    /**
     * API: GET /api/note-types
     */
    public function index(): JsonResponse
    {
        $items = NoteType::query()
            ->orderBy('note')
            ->orderBy('id')
            ->get();

        return ApiResponse::success($items, 'Load note types successfully');
    }
}
