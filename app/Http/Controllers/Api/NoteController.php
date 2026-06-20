<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NoteType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Responses\NoteListResponse;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Note::withTrashed()
            ->with('noteType')
            ->when(
                $request->filled('student_id'),
                fn ($query) => $query->where(
                    'student_id',
                    $request->query('student_id')
                )
            )
            ->orderByDesc('id')
            ->get();

        $data = NoteListResponse::collection($items);

        return ApiResponse::success($data, 'Load notes successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'note_type_id' => ['required', 'integer', 'exists:note_types,id'],
            'remark' => ['nullable', 'string'],
        ]);

        $noteType = NoteType::query()->find($validated['note_type_id']);

        $isOther = $noteType && trim($noteType->note) === 'อื่นๆ';

        if ($isOther && blank($request->input('remark'))) {
            return ApiResponse::error('กรุณากรอก remark', 422);
        }

        $item = Note::create([
            'student_id' => $validated['student_id'],
            'note_type_id' => $validated['note_type_id'],
            'remark' => $isOther ? trim($validated['remark'] ?? '') : null,
        ]);

        $item->load('noteType');

        return ApiResponse::success(
            new NoteListResponse($item),
            'Create note successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $item = Note::findOrFail($id);
        $item->delete();

        return ApiResponse::success(null, 'Delete note successfully');
    }
}