<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'department', 'job_title', 'avatar_path', 'bio', 'role', 'manager_id', 'deactivated_at', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, Auditable;

    public function isInstructor(): bool
    {
        return in_array($this->role, ['instructor', 'admin'], true);
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /**
     * Skills earned through completed courses. Derived from
     * completed enrollments, not stored directly on the user.
     */
    public function earnedSkills()
    {
        $completedCourseIds = $this->enrollments()->where('status', 'completed')->pluck('course_id');

        return Skill::whereHas('courses', fn ($q) => $q->whereIn('courses.id', $completedCourseIds))
            ->with(['courses' => fn ($q) => $q->whereIn('courses.id', $completedCourseIds)])
            ->get();
    }

    public function routeNotificationForMail()
    {
        return $this->email;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->role === 'admin' && ! $this->deactivated_at;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * The notification types a user is allowed to opt out of, keyed by the
     * preference name checked in each Notification class's via(). Live
     * session invites aren't listed here -- they're tied to a session the
     * learner is already enrolled in (with a calendar attachment), not a
     * promotional/reminder ping, so they stay non-optional.
     */
    public const OPTIONAL_NOTIFICATION_TYPES = [
        'training_due_soon' => 'Eğitim son tarihi hatırlatmaları',
        'certificate_expiring_soon' => 'Sertifika süre sonu hatırlatmaları',
        'course_published' => 'Yeni eğitim yayınlandı bildirimleri',
    ];

    /**
     * Defaults to true (send it) whenever the key is missing -- covers both
     * a user who's never opened the preferences form at all, and a
     * hypothetical future notification type added after they last saved it.
     */
    public function wantsNotification(string $type): bool
    {
        return (bool) ($this->notification_preferences[$type] ?? true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }
}
