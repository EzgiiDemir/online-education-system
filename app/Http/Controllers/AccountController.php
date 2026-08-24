<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $enrollments = $user->enrollments()->with('course', 'certificate')->latest()->paginate(10)
            ->through(fn ($enrollment) => ['enrollment' => $enrollment, 'stats' => $enrollment->completionStats()]);
        $courses = $user->isInstructor() ? $user->courses()->withCount('enrollments')->latest()->get() : collect();

        return view('lms.account', compact('user', 'enrollments', 'courses'));
    }

    public function updateNotificationPreferences(Request $r)
    {
        $data = $r->validate([
            'preferences' => 'array',
            'preferences.*' => 'string|in:'.implode(',', array_keys(User::OPTIONAL_NOTIFICATION_TYPES)),
        ]);
        $enabled = $data['preferences'] ?? [];

        $preferences = collect(User::OPTIONAL_NOTIFICATION_TYPES)->keys()
            ->mapWithKeys(fn ($type) => [$type => in_array($type, $enabled, true)])
            ->all();

        Auth::user()->update(['notification_preferences' => $preferences]);

        return back()->with('success', 'Bildirim tercihleri güncellendi.');
    }

    /**
     * Anonymizes rather than deletes: enrollments.user_id and several other
     * foreign keys cascadeOnDelete, so a real $user->delete() would take
     * every enrollment, certificate, quiz attempt and assignment submission
     * this account was ever party to down with it -- silently erasing
     * exactly the completion evidence this app exists to keep. Scrubbing
     * the PII in place and marking the row deactivated keeps that history
     * intact and attached to the (now anonymous) account, while making the
     * account itself unusable and freeing the email for reuse (a fresh
     * signup, local or via Entra SSO's firstOrCreate-by-email, gets a
     * brand-new row rather than reviving this one).
     */
    public function destroy(Request $r)
    {
        $user = Auth::user();
        $r->validate(['password' => 'required|string']);
        abort_unless(Hash::check($r->string('password'), $user->password), 422, 'Şifre hatalı.');
        abort_if($user->role === 'admin' && User::where('role', 'admin')->count() <= 1, 422, 'Son yönetici hesabı silinemez.');
        abort_if(Course::where('instructor_id', $user->id)->exists(), 422, 'Eğitmen olduğunuz kurslar bulunuyor. Hesabınızı silmeden önce lütfen bir yöneticiden bu kursları devretmesini isteyin.');

        $userId = $user->id;
        Auth::logout();
        $user->update([
            'name' => 'Silinmiş Kullanıcı',
            'email' => "deleted-{$userId}-".Str::random(12).'@deleted.local',
            'password' => Hash::make(Str::random(40)),
            'avatar_path' => null,
            'bio' => null,
            'department' => null,
            'job_title' => null,
            'manager_id' => null,
            'deactivated_at' => now(),
        ]);
        $user->forceFill(['remember_token' => null])->save();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('lms.home')->with('success', 'Hesabınız silindi.');
    }
}
