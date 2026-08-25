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

#[Fillable(['name', 'email', 'external_id', 'password', 'department', 'job_title', 'avatar_path', 'bio', 'role', 'manager_id', 'organization_unit_id', 'deactivated_at', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, Auditable;

    /**
     * Shared by every admin-facing delete/deactivate control (Filament's
     * Users table row action, bulk action, and the edit-page delete/toggle)
     * so the rule can't drift between them. courses.instructor_id has no
     * cascade (a hard delete would just throw a raw FK violation), while
     * enrollments.user_id does cascadeOnDelete -- deleting a learner with
     * history would silently destroy the exact completion/certificate
     * evidence AccountController::destroy()'s self-service anonymize-not-
     * delete path exists to protect. Deactivating is always the safe
     * alternative (never destroys the row), so it only re-checks the
     * "would lock everyone out" cases, not the enrollment-history one.
     */
    public function blockingRemovalReason(bool $isDelete): ?string
    {
        $actionLabel = $isDelete ? 'silinemez' : 'devre dışı bırakılamaz';

        if ($this->role === 'admin' && static::where('role', 'admin')->count() <= 1) {
            return 'Son yönetici hesabı '.$actionLabel.'.';
        }
        if (Course::where('instructor_id', $this->id)->exists()) {
            return 'Bu kullanıcının verdiği kurslar var, '.$actionLabel.'. Önce kursları başka bir eğitmene devredin.';
        }
        if ($isDelete && $this->enrollments()->exists()) {
            return 'Bu kullanıcının eğitim kayıtları/sertifikaları var, silinemez. Silme işlemi bu geçmişi kalıcı olarak yok eder. Bunun yerine kullanıcıyı devre dışı bırakın.';
        }

        return null;
    }

    public function isInstructor(): bool
    {
        return $this->role === 'admin' || $this->hasRole('instructor');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    /**
     * The set of workspaces this user can switch into. users.role stays the
     * single "primary" role that every existing role === 'x' check in the
     * app reads (unchanged, so single-role behaviour never regresses) --
     * this pivot is purely additive, for people who hold more than one role
     * at once (see AGENTS/Faz 91 plan: workspace switcher).
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $key): bool
    {
        return $this->role === $key || $this->roles->contains('key', $key);
    }

    public function hasAnyRole(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->hasRole($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every workspace key this user can switch into, primary role first.
     */
    public function workspaces(): array
    {
        $keys = $this->roles->pluck('key')->all();
        if (! in_array($this->role, $keys, true)) {
            $keys[] = $this->role;
        }
        usort($keys, fn ($a, $b) => ($a === $this->role ? 0 : 1) <=> ($b === $this->role ? 0 : 1));

        return $keys;
    }

    /**
     * Where a fresh login (local or Entra) lands -- the one place this
     * decision is made, so LmsController::login() and
     * EntraSsoController::callback() can't silently drift apart.
     */
    public function defaultWorkspaceRoute(): string
    {
        return match (true) {
            $this->role === 'admin' => '/admin',
            $this->role === 'manager' => route('manager.dashboard'),
            $this->isInstructor() => route('instructor.dashboard'),
            default => route('lms.home'),
        };
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The current cycle of this user's relationship with a course -- see
     * Enrollment::currentFor(). Every controller action that records
     * progress against "the" enrollment for a course must resolve it
     * through here (or currentEnrollmentForOrFail()), never a bare
     * ->where('course_id', ...)->first(), now that recertification means
     * more than one row can exist for the same course over time.
     */
    public function currentEnrollmentFor(int $courseId): ?Enrollment
    {
        return Enrollment::currentFor($this->id, $courseId);
    }

    public function currentEnrollmentForOrFail(int $courseId): Enrollment
    {
        return $this->currentEnrollmentFor($courseId) ?? abort(404);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function organizationUnit()
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    /**
     * Every user anywhere below this one in the manager_id chain -- direct
     * reports, their reports, and so on -- not just directReports().
     * Breadth-first and cycle-safe (a corrupted manager_id chain, e.g. from
     * a manual DB edit, can't hang this in an infinite loop) since a normal
     * "who reports up to whom" org chart is always a tree in practice.
     */
    public function allReports(): \Illuminate\Support\Collection
    {
        $collected = collect();
        $seenIds = collect([$this->id]);
        $frontierIds = collect([$this->id]);

        while ($frontierIds->isNotEmpty()) {
            $batch = User::whereIn('manager_id', $frontierIds)->get()->reject(fn (User $u) => $seenIds->contains($u->id));
            if ($batch->isEmpty()) {
                break;
            }
            $collected = $collected->merge($batch);
            $seenIds = $seenIds->merge($batch->pluck('id'));
            $frontierIds = $batch->pluck('id');
        }

        return $collected->values();
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
        return $panel->getId() === 'admin' && $this->hasRole('admin') && ! $this->deactivated_at;
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
