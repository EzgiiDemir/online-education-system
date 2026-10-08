<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Material;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    /**
     * whereLike(caseSensitive: false) keeps the exact pass case-insensitive
     * on every engine -- it compiles to ILIKE on Postgres, whose LIKE (unlike
     * SQLite's/MySQL's) does NOT fold case. The exact pass still goes
     * empty-handed on
     * a typo ("pazrlama" never matches "Pazarlama"). When the exact pass
     * finds nothing, this falls back to scoring every candidate's title by
     * Levenshtein distance against the query (and against each individual
     * word in the title, so a short query still matches inside a long
     * title) and keeps only the ones within a typo or two -- not a full
     * fuzzy-search engine, but enough that a near-miss spelling still
     * surfaces the right course instead of "no results".
     */
    private function search(string $query, string $type, int $limit = 20): array
    {
        $like = '%'.$query.'%';

        $courses = in_array($type, ['all', 'courses'], true)
            ? Course::where('status', 'published')->where(fn ($q) => $q->whereLike('title', $like, caseSensitive: false)->orWhereLike('description', $like, caseSensitive: false))->with('instructor')->limit($limit)->get()
            : collect();
        if ($courses->isEmpty() && in_array($type, ['all', 'courses'], true)) {
            $courses = $this->fuzzyMatch(Course::where('status', 'published')->with('instructor')->limit(300)->get(), $query, fn ($c) => $c->title, $limit);
        }

        $activities = in_array($type, ['all', 'activities'], true)
            ? Activity::whereLike('title', $like, caseSensitive: false)->whereHas('section.course', fn ($q) => $q->where('status', 'published'))->with('section.course')->limit($limit)->get()
            : collect();
        if ($activities->isEmpty() && in_array($type, ['all', 'activities'], true)) {
            $activities = $this->fuzzyMatch(Activity::whereHas('section.course', fn ($q) => $q->where('status', 'published'))->with('section.course')->limit(300)->get(), $query, fn ($a) => $a->title, $limit);
        }

        $materials = in_array($type, ['all', 'materials'], true)
            ? Material::whereLike('title', $like, caseSensitive: false)->whereHas('session.course', fn ($q) => $q->where('status', 'published'))->with('session.course')->limit($limit)->get()
            : collect();
        if ($materials->isEmpty() && in_array($type, ['all', 'materials'], true)) {
            $materials = $this->fuzzyMatch(Material::whereHas('session.course', fn ($q) => $q->where('status', 'published'))->with('session.course')->limit(300)->get(), $query, fn ($m) => $m->title, $limit);
        }

        $sessions = in_array($type, ['all', 'sessions'], true)
            ? CourseSession::whereLike('title', $like, caseSensitive: false)->whereHas('course', fn ($q) => $q->where('status', 'published'))->with('course')->limit($limit)->get()
            : collect();
        if ($sessions->isEmpty() && in_array($type, ['all', 'sessions'], true)) {
            $sessions = $this->fuzzyMatch(CourseSession::whereHas('course', fn ($q) => $q->where('status', 'published'))->with('course')->limit(300)->get(), $query, fn ($s) => $s->title, $limit);
        }

        $instructors = in_array($type, ['all', 'instructors'], true)
            ? User::whereIn('role', ['instructor', 'admin'])->whereLike('name', $like, caseSensitive: false)->limit($limit)->get()
            : collect();
        if ($instructors->isEmpty() && in_array($type, ['all', 'instructors'], true)) {
            $instructors = $this->fuzzyMatch(User::whereIn('role', ['instructor', 'admin'])->limit(300)->get(), $query, fn ($u) => $u->name, $limit);
        }

        return compact('courses', 'activities', 'materials', 'sessions', 'instructors');
    }

    private function fuzzyMatch(Collection $candidates, string $query, \Closure $titleOf, int $limit): Collection
    {
        $needleWords = array_values(array_filter(preg_split('/\s+/u', $this->foldTurkish(mb_strtolower(trim($query)))), fn ($w) => $w !== ''));
        if (empty($needleWords)) {
            return collect();
        }
        // ~1 allowed typo per 3 characters across the whole query, at least
        // 1 -- tight enough that unrelated short words don't start matching
        // everything.
        $threshold = max(1, (int) floor(array_sum(array_map('mb_strlen', $needleWords)) / 3));

        return $candidates
            ->map(function ($item) use ($titleOf, $needleWords) {
                $title = $this->foldTurkish(mb_strtolower((string) $titleOf($item)));
                $titleWords = array_values(array_filter(preg_split('/\s+/u', $title), fn ($w) => $w !== ''));

                // Each query word needs its own reasonably-close match
                // somewhere in the title -- comparing the whole (possibly
                // multi-word) query against one title word at a time would
                // inflate the distance on length alone.
                $total = 0;
                foreach ($needleWords as $nw) {
                    $best = $this->mbLevenshtein($nw, $title);
                    foreach ($titleWords as $tw) {
                        $best = min($best, $this->mbLevenshtein($nw, $tw));
                    }
                    $total += $best;
                }

                return ['item' => $item, 'distance' => $total];
            })
            ->filter(fn ($row) => $row['distance'] <= $threshold)
            ->sortBy('distance')
            ->take($limit)
            ->pluck('item')
            ->values();
    }

    /**
     * Folds the Turkish letters that differ from their nearest ASCII form
     * (ı/İ, ğ, ş, ö, ü, ç) so a query typed without a Turkish keyboard --
     * "saglik" for "sağlık" -- compares as an exact or near-exact match
     * instead of racking up 3+ points of Levenshtein distance on accents
     * alone, which a strict per-character diff would otherwise treat as
     * "almost a different word".
     */
    private function foldTurkish(string $s): string
    {
        return strtr($s, ['ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'ş' => 's', 'ö' => 'o', 'ü' => 'u', 'ç' => 'c']);
    }

    /**
     * PHP's built-in levenshtein() is byte-based, not character-based -- it
     * miscounts distance on Turkish letters (ç, ğ, ı, ö, ş, ü, İ), which are
     * multi-byte in UTF-8 and common in course titles. This is the same
     * classic DP algorithm, just walked over mb_str_split() characters.
     */
    private function mbLevenshtein(string $a, string $b): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $lenA = count($a);
        $lenB = count($b);
        if ($lenA === 0) {
            return $lenB;
        }
        if ($lenB === 0) {
            return $lenA;
        }

        $prev = range(0, $lenB);
        for ($i = 1; $i <= $lenA; $i++) {
            $curr = [$i];
            for ($j = 1; $j <= $lenB; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $curr[$j] = min($prev[$j] + 1, $curr[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $curr;
        }

        return $prev[$lenB];
    }

    private function emptyResults(): array
    {
        return ['courses' => collect(), 'activities' => collect(), 'materials' => collect(), 'sessions' => collect(), 'instructors' => collect()];
    }
}
