<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;

class SubjectsController extends Controller
{
    public function index()
    {
        return view('admin.subjects.index');
    }

    public function create()
    {
        return view('admin.subjects.edit');
    }
    public function edit($subject_id)
    {
        $subject = Subject::findOrFail($subject_id);

        return view('admin.subjects.edit', compact('subject'));
    }
}
