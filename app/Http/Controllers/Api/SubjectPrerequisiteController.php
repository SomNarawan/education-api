<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubjectPrerequisite;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class SubjectPrerequisiteController extends Controller
{
    public function index(): JsonResponse
    {
        $items = SubjectPrerequisite::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load subject prerequisites successfully'
        );
    }
}
