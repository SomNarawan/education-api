<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Note\ListNotesRequest;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Responses\NoteListResponse;
use App\Models\Note;
use App\Models\NoteType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * API: GET /api/notes?student_id={id}
     */
    public function index(ListNotesRequest $request): JsonResponse
    {
        $notes = Note::withTrashed()
            ->with('noteType')
            ->where('student_id', $request->validated('student_id'))
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(
            NoteListResponse::collection($notes),
            'Load notes successfully'
        );
    }

    /**
     * API: POST /api/notes
     */
    public function store(StoreNoteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $isOther = NoteType::query()
            ->whereKey($validated['note_type_id'])
            ->where('note', 'อื่นๆ')
            ->exists();
        $createdBy = $this->actorFromJwt($request);

        if ($createdBy === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            );
        }

        $note = Note::query()->create([
            'student_id' => $validated['student_id'],
            'note_type_id' => $validated['note_type_id'],
            'remark' => $isOther ? trim($validated['remark']) : null,
            'created_by' => $createdBy,
        ]);
        $note->load('noteType');

        return ApiResponse::success(
            new NoteListResponse($note),
            'Create note successfully',
            HttpStatus::CREATED['code']
        );
    }

    /**
     * API: DELETE /api/notes/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $note = Note::query()->find($id);

        if ($note === null) {
            return ApiResponse::error('Note not found', HttpStatus::NOT_FOUND['code']);
        }

        $deletedBy = $this->actorFromJwt($request);

        if ($deletedBy === null) {
            return ApiResponse::error(
                'JWT does not contain name, nontri_id, or sub',
                HttpStatus::UNPROCESSABLE_ENTITY['code']
            );
        }

        $note->forceFill(['deleted_by' => $deletedBy])->save();
        $note->delete();

        return ApiResponse::success(null, 'Delete note successfully');
    }

    private function actorFromJwt(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $actor = $claims['name']
            ?? $claims['nontri_id']
            ?? $claims['sub']
            ?? null;

        return is_scalar($actor) && trim((string) $actor) !== ''
            ? trim((string) $actor)
            : null;
    }
}
