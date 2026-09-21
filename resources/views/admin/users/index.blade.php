<x-app-layout>
    <div class="max-w-4xl mx-auto py-6">
        <h1 class="text-xl font-semibold mb-4">Gestione utenti</h1>
        @if (session('status'))
            <div class="mb-4 text-green-600">{{ session('status') }}</div>
        @endif
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Ruoli</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                        <td><a href="{{ route('admin.users.edit', $user) }}">Modifica</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $users->links() }}
    </div>
</x-app-layout>