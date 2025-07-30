<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class UsersController extends Controller
{
    public function index()
    {
        return view('admin.users.index');
    }

    public function create()
    {
        return view('admin.users.edit');
    }

    public function edit($user_id)
    {
        $user = User::findOrFail($user_id);

        return view('admin.users.edit', compact('user'));
    }

    public function assignRole($id, $role): void
    {
        $user = User::findOrFail($id);

        $user->assignRole($role);

        session()->flash('message', "User assigned role: $role.");
    }
}
