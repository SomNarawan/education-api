<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Responses\NoteListResponse;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Note::query()
            ->whereNull('deleted_at')
            ->when(
                $request->filled('student_id'),
                fn($query) => $query->where(
                    'student_id',
                    $request->query('student_id')
                )
            )
            ->orderBy('id')
            ->get();

        $data = NoteListResponse::collection($items);

        return ApiResponse::success($data, 'Load notes successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'note' => trim((string) $request->input('note')),
        ]);

        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'note' => ['required', 'string', 'min:1'],
        ]);

        $item = Note::create([
            'student_id' => $validated['student_id'],
            'note' => $validated['note'],
        ]);

        return ApiResponse::success(
            new NoteListResponse($item),
            'Create note successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $item = Note::query()
            ->whereNull('deleted_at')
            ->find($id);

        if (!$item) {
            return ApiResponse::error('Note not found', 404);
        }

        $item->update([
            'deleted_at' => now(),
        ]);

        return ApiResponse::success(
            null,
            'Delete note successfully'
        );
    }
}