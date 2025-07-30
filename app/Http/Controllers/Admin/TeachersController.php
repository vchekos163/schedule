<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class TeachersController extends Controller
{
    public function index()
    {
        return view('admin.teachers.index');
    }

    public function edit($user_id)
    {
        $user = User::findOrFail($user_id);

        return view('admin.teachers.edit', compact('user'));
    }
}
