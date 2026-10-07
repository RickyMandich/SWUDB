<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifica {{ $user->name }}</h2>
    </x-slot>

    <div class="max-w-2xl mx-auto py-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            <h2 class="font-medium mt-4">Ruoli</h2>
            @foreach ($roles as $role)
                <label class="block">
                    <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                        class="rounded-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-indigo-600 shadow-xs focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
                        @checked($user->hasRole($role->name))>
                    {{ $role->name }}
                </label>
            @endforeach
            <x-input-error :messages="$errors->get('roles')" class="mt-2" />

            <h2 class="font-medium mt-4">Permessi diretti</h2>
            @foreach ($permissions as $permission)
                <label class="block">
                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                        class="rounded-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-indigo-600 shadow-xs focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
                        @checked($user->hasDirectPermission($permission->name))>
                    {{ $permission->name }}
                </label>
            @endforeach
            <x-input-error :messages="$errors->get('permissions')" class="mt-2" />

            <div class="flex items-center gap-4 mt-4">
                <x-primary-button>Salva</x-primary-button>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Annulla</a>
            </div>
        </form>
    </div>
</x-app-layout>
