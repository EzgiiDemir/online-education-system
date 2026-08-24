<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Notifications\LiveSessionScheduled;
use App\Notifications\WaitlistSpotAvailable;
use Illuminate\Database\Eloquent\Model;

class CourseRun extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['enrollment_opens_at' => 'datetime', 'enrollment_closes_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function waitlistEntries()
    {
        return $this->hasMany(WaitlistEntry::class)->oldest();
    }

    public function hasOpenSeats(): bool
    {
        return $this->max_seats === null || $this->enrollments()->count() < $this->max_seats;
    }

    /**
     * Called whenever a seat on this run might have freed up (an enrollment
     * for it was deleted). Converts the longest-waiting entry into a real
     * Enrollment and notifies them -- a no-op if the waitlist is empty or
     * the run is (still, or again) full, e.g. because it was already
     * promoted by a concurrent call for a different freed seat.
     */
    public function promoteNextFromWaitlist(): ?Enrollment
    {
        if (! $this->hasOpenSeats()) {
            return null;
        }

        $next = $this->waitlistEntries()->first();
        if (! $next) {
            return null;
        }

        $enrollment = Enrollment::create(['user_id' => $next->user_id, 'course_id' => $this->course_id, 'course_run_id' => $this->id]);
        $next->delete();
        $next->user->notify(new WaitlistSpotAvailable($enrollment));
        foreach ($this->course->sessions()->where('meeting_status', '!=', 'not_applicable')->get() as $session) {
            $next->user->notify(new LiveSessionScheduled($session));
        }

        return $enrollment;
    }
}
