<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifica {{ $user->name }}</h2>
    </x-slot>

    <div class="max-w-2xl mx-auto py-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            <h2 class="font-medium mt-4">Ruoli</h2>
            @foreach ($roles as $role)
                <label class="block">
                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked($user->hasRole($role->name))
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
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

            <div class="mt-4">
                <x-primary-button class="mr-2">Salva</x-primary-button>
                <a href="{{ route('admin.users.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition ease-in-out duration-150"></a>
            </div>
        </form>
    </div>
</x-app-layout>
