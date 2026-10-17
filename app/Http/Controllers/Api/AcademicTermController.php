<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicTerm\AdvanceAcademicTermRequest;
use App\Models\AcademicTermRollover;
use App\Services\AcademicTermRolloverService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use UnexpectedValueException;

class AcademicTermController extends Controller
{
    public function preview(
        Request $request,
        AcademicTermRolloverService $service
    ): JsonResponse {
        if (! $this->isAdmin($request)) {
            return $this->forbidden();
        }

        return ApiResponse::success(
            $service->preview(),
            'Load academic term rollover preview successfully'
        );
    }

    public function history(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return $this->forbidden();
        }

        $history = AcademicTermRollover::query()
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AcademicTermRollover $rollover): array => [
                'id' => (int) $rollover->id,
                'transitions' => $rollover->transition_summary,
                'affected_count' => (int) $rollover->affected_count,
                'created_by' => $rollover->created_by,
                'created_at' => $rollover->created_at,
            ]);

        return ApiResponse::success(
            $history,
            'Load academic term rollover history successfully'
        );
    }

    public function advance(
        AdvanceAcademicTermRequest $request,
        AcademicTermRolloverService $service
    ): JsonResponse {
        if (! $this->isAdmin($request)) {
            return $this->forbidden();
        }

        $actor = $this->actorFromJwt($request);

        if ($actor === null) {
            return ApiResponse::error(
                'JWT does not contain an actor identifier',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            );
        }

        try {
            $rollover = $service->advance(
                $request->validated('preview_token'),
                $actor,
            );
        } catch (DomainException $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                HttpStatus::CONFLICT['code']
            );
        } catch (UnexpectedValueException $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                'Unable to advance academic term',
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }

        return ApiResponse::success([
            'id' => (int) $rollover->id,
            'transitions' => $rollover->transition_summary,
            'affected_count' => (int) $rollover->affected_count,
            'created_by' => $rollover->created_by,
            'created_at' => $rollover->created_at,
        ], 'Advance academic term successfully');
    }

    private function isAdmin(Request $request): bool
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $roles = $claims['role'] ?? [];
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array('admin', $roles, true);
    }

    private function forbidden(): JsonResponse
    {
        return ApiResponse::error(
            'Only administrators can manage academic terms',
            HttpStatus::FORBIDDEN['code']
        );
    }
}
