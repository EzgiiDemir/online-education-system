<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Material;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Full results page. Guests and learners only ever see published
     * courses and their content -- draft courses (and the activities/
     * materials/sessions that belong to them) never surface in search,
     * matching the same visibility rule the catalog and course-show page
     * already use.
     */
    public function index(Request $r)
    {
        $query = trim((string) $r->query('q', ''));
        $type = $r->query('type', 'all');

        $results = $query !== '' ? $this->search($query, $type) : $this->emptyResults();

        return view('lms.search', compact('query', 'type', 'results'));
    }

    /**
     * Lightweight JSON endpoint for the nav search box's instant-results
     * dropdown. Capped small per group -- it's a preview, not the full list.
     */
    public function quick(Request $r)
    {
        $query = trim((string) $r->query('q', ''));
        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json(['courses' => [], 'activities' => [], 'materials' => [], 'sessions' => [], 'instructors' => []]);
        }

        $results = $this->search($query, 'all', limit: 4);

        return response()->json([
            'courses' => $results['courses']->map(fn ($c) => ['title' => $c->title, 'url' => route('courses.show', $c)])->values(),
            'activities' => $results['activities']->map(fn ($a) => ['title' => $a->title, 'url' => route('activities.view', $a)])->values(),
            'materials' => $results['materials']->map(fn ($m) => ['title' => $m->title, 'url' => route('materials.view', $m)])->values(),
            'sessions' => $results['sessions']->map(fn ($s) => ['title' => $s->title, 'url' => route('courses.show', $s->course)])->values(),
            'instructors' => $results['instructors']->map(fn ($u) => ['title' => $u->name, 'url' => null])->values(),
        ]);
    }

    private function search(string $query, string $type, int $limit = 20): array
    {
        $like = '%'.$query.'%';

        $courses = in_array($type, ['all', 'courses'], true)
            ? Course::where('status', 'published')->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))->with('instructor')->limit($limit)->get()
            : collect();

        $activities = in_array($type, ['all', 'activities'], true)
            ? Activity::where('title', 'like', $like)->whereHas('section.course', fn ($q) => $q->where('status', 'published'))->with('section.course')->limit($limit)->get()
            : collect();

        $materials = in_array($type, ['all', 'materials'], true)
            ? Material::where('title', 'like', $like)->whereHas('session.course', fn ($q) => $q->where('status', 'published'))->with('session.course')->limit($limit)->get()
            : collect();

        $sessions = in_array($type, ['all', 'sessions'], true)
            ? CourseSession::where('title', 'like', $like)->whereHas('course', fn ($q) => $q->where('status', 'published'))->with('course')->limit($limit)->get()
            : collect();

        $instructors = in_array($type, ['all', 'instructors'], true)
            ? User::whereIn('role', ['instructor', 'admin'])->where('name', 'like', $like)->limit($limit)->get()
            : collect();

        return compact('courses', 'activities', 'materials', 'sessions', 'instructors');
    }

    private function emptyResults(): array
    {
        return ['courses' => collect(), 'activities' => collect(), 'materials' => collect(), 'sessions' => collect(), 'instructors' => collect()];
    }
}
