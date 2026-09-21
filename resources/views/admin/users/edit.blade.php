<x-app-layout>
    <div class="max-w-2xl mx-auto py-6">
        <h1 class="text-xl font-semibold mb-4">Modifica {{ $user->name }}</h1>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            <h2 class="font-medium mt-4">Ruoli</h2>
            @foreach ($roles as $role)
                <label class="block">
                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked($user->hasRole($role->name))>
                    {{ $role->name }}
                </label>
            @endforeach

            <h2 class="font-medium mt-4">Permessi diretti</h2>
            @foreach ($permissions as $permission)
                <label class="block">
                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                        @checked($user->hasDirectPermission($permission->name))>
                    {{ $permission->name }}
                </label>
            @endforeach

            <button type="submit" class="mt-4">Salva</button>
        </form>
    </div>
</x-app-layout>