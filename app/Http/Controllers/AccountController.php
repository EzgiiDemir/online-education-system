<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

    public function destroy(Request $r)
    {
        $user = Auth::user();
        $r->validate(['password' => 'required|string']);
        abort_unless(Hash::check($r->string('password'), $user->password), 422, 'Şifre hatalı.');
        abort_if($user->role === 'admin' && User::where('role', 'admin')->count() <= 1, 422, 'Son yönetici hesabı silinemez.');
        abort_if(Course::where('instructor_id', $user->id)->exists(), 422, 'Eğitmen olduğunuz kurslar bulunuyor. Hesabınızı silmeden önce lütfen bir yöneticiden bu kursları devretmesini isteyin.');

        Auth::logout();
        $user->delete();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('lms.home')->with('success', 'Hesabınız silindi.');
    }
}
