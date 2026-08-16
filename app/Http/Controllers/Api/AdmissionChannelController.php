<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AdmissionChannel;
use Illuminate\Http\JsonResponse;

class AdmissionChannelController extends Controller
{
    /**
     * API: GET /api/admission-channels
     */
    public function index(): JsonResponse
    {
        $items = AdmissionChannel::query()
            ->orderBy('channel_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items, 'Load admission channels successfully'
        );
    }
}
