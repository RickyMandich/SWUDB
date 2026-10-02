<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestione utenti</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-6">
        <x-flash-message />
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-2 py-2 border-b">Nome</th>
                        <th class="px-2 py-2 border-b">Email</th>
                        <th class="px-2 py-2 border-b">Ruoli</th>
                        <th class="px-2 py-2 border-b"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-2 py-2 border-b">{{ $user->name }}</td>
                            <td class="px-2 py-2 border-b">{{ $user->email }}</td>
                            <td class="px-2 py-2 border-b">{{ $user->roles->pluck('name')->join(', ') }}</td>
                            <td class="px-2 py-2 border-b"><a href="{{ route('admin.users.edit', $user) }}">Modifica
                                    Permessi</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</x-app-layout>
