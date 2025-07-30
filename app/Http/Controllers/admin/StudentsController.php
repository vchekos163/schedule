<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class StudentsController extends Controller
{
    public function index()
    {
        $students = User::with('subjects')->role('student')->get();

        return view('admin.students.index', compact('students'));
    }

    public function assignSubject($user_id)
    {
        $user = User::findOrFail($user_id);

        return view('admin.students.assign-subject', compact('user'));
    }
}
