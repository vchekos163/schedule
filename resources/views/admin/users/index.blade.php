@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h2 class="text-2xl font-semibold mb-6">Пользователи</h2>

        <form method="POST" action="{{ url('/admin/users/massaction') }}" id="massActionForm">
            @csrf

            <div class="overflow-x-auto rounded-lg shadow-sm border">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-semibold text-gray-700">
                        <th class="px-4 py-3"><input type="checkbox" id="checkAll" /></th>
                        <th class="px-4 py-3">Полное имя</th>
                        <th class="px-4 py-3">Профиль</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Роли</th>
                        <th class="px-4 py-3">Последний вход</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($users as $user)
                        <tr>
                            <td class="px-4 py-2"><input type="checkbox" name="selected_users[]" value="{{ $user->id }}"></td>
                            <td class="px-4 py-2">{{ $user->name }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ url('/admin/users/' . $user->id) }}" class="text-blue-600 hover:underline">Открыть</a>
                            </td>
                            <td class="px-4 py-2">{{ $user->email }}</td>
                            <td class="px-4 py-2">
                                @foreach($user->getRoleNames() as $role)
                                    <span class="inline-block bg-gray-200 text-xs rounded px-2 py-1 mr-1">{{ $role }}</span>
                                @endforeach
                            </td>
                            <td class="px-4 py-2 text-gray-500">
                                {{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Action Controls -->
            <div class="mt-6 flex flex-col sm:flex-row sm:items-center gap-4">
                <select name="action" required id="actionSelector" class="w-full sm:w-auto border-gray-300 rounded p-2">
                    <option value="">— Выберите действие —</option>
                    <option value="delete">Удалить</option>
                    <option value="assign_role">Назначить роль</option>
                </select>

                <select name="role" id="roleSelector" class="w-full sm:w-auto border-gray-300 rounded p-2 hidden">
                    <option value="">— Роль —</option>
                    @foreach(\Spatie\Permission\Models\Role::all() as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                    Применить
                </button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('checkAll').addEventListener('click', function () {
            document.querySelectorAll('input[name="selected_users[]"]').forEach(cb => cb.checked = this.checked);
        });

        document.getElementById('actionSelector').addEventListener('change', function () {
            document.getElementById('roleSelector').classList.toggle('hidden', this.value !== 'assign_role');
        });
    </script>
@endsection
