<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionChannel;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class AdmissionChannelController extends Controller
{
    public function index(): JsonResponse
    {
        $items = AdmissionChannel::orderBy('id')->get();

        return ApiResponse::success(
            $items, 'Load admission channels successfully'
        );
    }
}
