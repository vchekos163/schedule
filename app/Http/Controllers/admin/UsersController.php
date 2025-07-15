<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function __construct()
    {
        // Protect all methods with auth and 'admin' role
        $this->middleware(['auth', 'role:admin']);
    }

    /**
     * Display all users
     */
    public function index()
    {
        $users = User::with('roles')->get();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show individual user profile
     */
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);

        return view('admin.users.show', compact('user'));
    }

    /**
     * Handle mass actions like delete or assign role
     */
    public function massAction(Request $request)
    {
        $request->validate([
            'selected_users' => 'required|array',
        ]);

        $action = $request->input('action');
        $users = User::whereIn('id', $request->selected_users)->get();

        switch ($action) {
            case 'delete':
                foreach ($users as $user) {
                    $user->delete();
                }
                return back()->with('status', 'Выбранные пользователи удалены.');

            case 'assign_role':
                $roleName = $request->input('role');
                if (!$roleName || !Role::where('name', $roleName)->exists()) {
                    return back()->withErrors(['role' => 'Некорректная роль.']);
                }

                foreach ($users as $user) {
                    $user->syncRoles([$roleName]);
                }
                return back()->with('status', 'Роль назначена выбранным пользователям.');

            default:
                return back()->withErrors(['action' => 'Неизвестное действие.']);
        }
    }
}
