<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAssignmentRequest;
use App\Http\Requests\Api\UpdateAssignmentRequest;
use App\Http\Requests\ListAssignmentsRequest;
use App\Http\Resources\AssignmentCollection;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AssignmentController extends Controller
{
    /** GET /courses/{course}/assignments?status=&page= */
    public function index(ListAssignmentsRequest $request, Course $course): AssignmentCollection
    {
        Gate::authorize('view', $course);

        $assignments = $course->assignments()
            ->visibleTo($request->user())
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return new AssignmentCollection($assignments);
    }

    /** POST /assignments -> 201 */
    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        // course_id sudah lolos 'exists' di request, jadi findOrFail aman.
        $course = Course::findOrFail($request->validated('course_id'));
        Gate::authorize('create', [Assignment::class, $course]);

        // created_by dari server, BUKAN dari input. Field yang tidak dikirim
        // memakai default model (status=draft, max_score=100, allow_late=true).
        $assignment = Assignment::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return AssignmentResource::make($assignment)
            ->response()
            ->setStatusCode(201);
    }

    /** PUT|PATCH /assignments/{assignment} -> 200 */
    public function update(UpdateAssignmentRequest $request, Assignment $assignment): AssignmentResource
    {
        Gate::authorize('update', $assignment);

        $assignment->update($request->validated());

        return new AssignmentResource($assignment);
    }

    /** DELETE /assignments/{assignment} -> 204 */
    public function destroy(Assignment $assignment): Response
    {
        Gate::authorize('delete', $assignment);

        // Berkas fisik (lampiran soal + seluruh berkas pengumpulan beserta revisinya)
        // dibersihkan oleh Assignment::booted() setelah baris berhasil dihapus,
        // sehingga hasilnya sama persis dengan penghapusan lewat web.
        $assignment->delete();

        return response()->noContent();
    }
}