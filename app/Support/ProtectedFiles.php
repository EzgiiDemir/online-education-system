<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Course content and learner uploads -- materials (and their earlier
 * versions), activity files, assignment submissions and live-class
 * recordings -- live on the private 'local' disk and are only ever handed
 * out through FileController, which asks allows() first.
 *
 * They used to sit on the 'public' disk behind the public/storage symlink,
 * so the web server served them to anyone holding the URL, signed in or
 * not: a learner's submitted assignment, or a course's recordings, stayed
 * reachable after the enrollment ended and could be forwarded freely. The
 * pages that linked to them were access-checked; the files never were.
 *
 * Each rule mirrors the page that shows the file, so a file is visible to
 * exactly the people who can already open that page:
 *   material / activity file  -> course owner or admin, or a current enrollee
 *   material version (history) -> course owner or admin
 *   assignment submission     -> the learner who submitted it, course owner or admin
 *   recording                 -> course owner or admin
 */
class ProtectedFiles
{
    public const DISK = 'local';

    /** MIME form of LmsController::SAFE_UPLOAD_MIMES, for Filament's FileUpload fields. */
    public const ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip-compressed',
        'video/mp4', 'video/webm', 'video/quicktime',
        'audio/mpeg', 'audio/wav', 'audio/x-wav',
        'image/jpeg', 'image/png', 'image/gif',
        'text/plain', 'text/csv',
    ];

    public static function url(?string $path): ?string
    {
        return $path ? route('files.show', ['path' => $path]) : null;
    }

    public static function allows(User $user, string $path): bool
    {
        $manages = fn (Course $course) => Gate::forUser($user)->allows('view', $course);
        $learns = fn (Course $course) => $user->currentEnrollmentFor($course->id) !== null;

        foreach (Material::where('path', $path)->with('session.course')->get() as $material) {
            if ($manages($material->session->course) || $learns($material->session->course)) {
                return true;
            }
        }

        foreach (Activity::where('path', $path)->with('section.course')->get() as $activity) {
            if ($manages($activity->section->course) || $learns($activity->section->course)) {
                return true;
            }
        }

        foreach (MaterialVersion::where('path', $path)->with('material.session.course')->get() as $version) {
            if ($version->material && $manages($version->material->session->course)) {
                return true;
            }
        }

        foreach (AssignmentSubmission::where('file_path', $path)->with('enrollment', 'activity.section.course')->get() as $submission) {
            if ($submission->enrollment?->user_id === $user->id || $manages($submission->activity->section->course)) {
                return true;
            }
        }

        foreach (CourseSession::where('recording_path', $path)->with('course')->get() as $session) {
            if ($manages($session->course)) {
                return true;
            }
        }

        return false;
    }
}
