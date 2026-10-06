<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Gestione utenti</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-6">
        <x-flash-message />
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-2 py-2 border-b dark:border-gray-700">Nome</th>
                        <th class="px-2 py-2 border-b dark:border-gray-700">Email</th>
                        <th class="px-2 py-2 border-b dark:border-gray-700">Ruoli</th>
                        <th class="px-2 py-2 border-b dark:border-gray-700"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-2 py-2 border-b dark:border-gray-700">{{ $user->name }}</td>
                            <td class="px-2 py-2 border-b dark:border-gray-700">{{ $user->email }}</td>
                            <td class="px-2 py-2 border-b dark:border-gray-700">{{ $user->roles->pluck('name')->join(', ') }}</td>
                            <td class="px-2 py-2 border-b dark:border-gray-700"><a href="{{ route('admin.users.edit', $user) }}">Modifica
                                    Permessi</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</x-app-layout>
