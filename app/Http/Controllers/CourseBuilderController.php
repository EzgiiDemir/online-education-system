<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Material;
use App\Support\ProtectedFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Instructor-facing curriculum management (§15): add/edit/delete sections
 * and activities on your own course, plus drag-and-drop reordering. Quiz
 * question/option authoring stays admin-only via Filament -- building a full
 * question builder here would be its own separate undertaking, not a
 * reorder feature; this controller only manages the section/activity shell.
 * All mutations reuse CoursePolicy::update (same rule Filament and the rest
 * of the instructor panel already use for "is this your course").
 */
class CourseBuilderController extends Controller
{
    public function storeSection(Request $r, Course $course)
    {
        $this->authorize('update', $course);
        $data = $r->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string']);
        $nextOrder = ((int) $course->sections()->max('sort_order')) + 1;
        $course->sections()->create([...$data, 'sort_order' => $nextOrder]);

        return back()->with('success', __('Bölüm eklendi.'));
    }

    public function updateSection(Request $r, CourseSection $section)
    {
        $this->authorize('update', $section->course);
        $data = $r->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string']);
        $section->update($data);

        return back()->with('success', __('Bölüm güncellendi.'));
    }

    public function destroySection(CourseSection $section)
    {
        $this->authorize('update', $section->course);
        $section->delete();

        return back()->with('success', __('Bölüm silindi.'));
    }

    public function reorderSections(Request $r, Course $course)
    {
        $this->authorize('update', $course);
        $data = $r->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $sectionIds = $course->sections()->pluck('id');
        foreach ($data['ids'] as $index => $id) {
            if ($sectionIds->contains($id)) {
                CourseSection::whereKey($id)->update(['sort_order' => $index + 1]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function storeActivity(Request $r, CourseSection $section)
    {
        $this->authorize('update', $section->course);
        $data = $r->validate([
            'title' => 'required|string|max:160',
            'type' => 'required|in:video,document,presentation,resource,assignment',
            'description' => 'nullable|string',
            'is_required' => 'nullable|boolean',
        ]);
        $nextOrder = ((int) $section->activities()->max('sort_order')) + 1;
        $section->activities()->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'is_required' => $r->boolean('is_required'),
            'sort_order' => $nextOrder,
        ]);

        return back()->with('success', __('Etkinlik eklendi.'));
    }

    public function updateActivity(Request $r, Activity $activity)
    {
        $this->authorize('update', $activity->section->course);
        $data = $r->validate(['title' => 'required|string|max:160', 'description' => 'nullable|string', 'is_required' => 'nullable|boolean']);
        $activity->update(['title' => $data['title'], 'description' => $data['description'] ?? null, 'is_required' => $r->boolean('is_required')]);

        return back()->with('success', __('Etkinlik güncellendi.'));
    }

    public function destroyActivity(Activity $activity)
    {
        $this->authorize('update', $activity->section->course);
        $activity->delete();

        return back()->with('success', __('Etkinlik silindi.'));
    }

    public function reorderActivities(Request $r, CourseSection $section)
    {
        $this->authorize('update', $section->course);
        $data = $r->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $activityIds = $section->activities()->pluck('id');
        foreach ($data['ids'] as $index => $id) {
            if ($activityIds->contains($id)) {
                Activity::whereKey($id)->update(['sort_order' => $index + 1]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * A replacement file doesn't overwrite history: the material's current
     * path/type/title is snapshotted into material_versions BEFORE being
     * swapped, so anyone who already has the old file's URL (or watched/
     * completed it) can still reach exactly what they saw -- material_id
     * itself never changes, so MaterialProgress is unaffected either way.
     */
    public function updateMaterial(Request $r, Material $material)
    {
        $this->authorize('update', $material->session->course);
        $data = $r->validate([
            'title' => 'required|string|max:255',
            'file' => ['nullable', 'file', 'max:51200', LmsController::SAFE_UPLOAD_MIMES],
        ]);

        if ($r->hasFile('file')) {
            $material->versions()->create([
                'title' => $material->title,
                'type' => $material->type,
                'path' => $material->path,
                'replaced_by' => Auth::id(),
            ]);
            $file = $data['file'];
            $material->fill(['type' => $file->getMimeType(), 'path' => $file->store('materials', ProtectedFiles::DISK)]);
        }

        $material->title = $data['title'];
        $material->save();

        return back()->with('success', __('Materyal güncellendi.'));
    }

    public function destroyMaterial(Material $material)
    {
        $this->authorize('update', $material->session->course);
        $material->delete();

        return back()->with('success', __('Materyal silindi.'));
    }
}
