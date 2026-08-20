<?php
namespace App\Http\Controllers;
use App\Models\User; use App\Models\Course; use App\Models\Enrollment;
class AdminController extends Controller {
 public function dashboard(){ abort_unless(auth()->user()->role==='admin',403); return view('admin.dashboard',['users'=>User::latest()->get(),'courses'=>Course::with('instructor')->latest()->get(),'enrollments'=>Enrollment::with('user','course')->latest()->get()]); }
}
