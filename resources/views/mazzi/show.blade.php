@extends('layouts.app')
@section('content')
    @if ($stato == 0)
        <div class="header text-center mb-4">
            <h1>
                {{ $nome }} <button class="btn btn-primary"> + </button>
            </h1>
            <h6>
                @if (count($mazzo) == 1)
                    in questo mazzo è presente {{ count($mazzo) }} carta
                @else
                    in questo mazzo sono presenti {{ count($mazzo) }} carte
                @endif
            </h6>
        </div>
        <div class="container">
            <div class="row">
                <!-- Colonna sinistra -->
                <div class="col-md-6">
                    <div class="main-deck">
                        <h3 class="mb-3">Mazzo</h3>
                        <div class="contenuto">
                            @foreach ($mazzo as $carta)
                                <span class="{{ $carta->espansione }}-{{ $carta->numero }} d-flex mt-4">
                                    {{ $carta->copie }}
                                        <button type="button" class="btn btn-success rounded-0 rounded-start-1 border-end-0 py-1 px-2 lh-1">+</button>
                                        <button type="button" class="btn btn-danger rounded-0 rounded-end-1 border-start-0 py-1 px-2 lh-1">-</button>
                                    {{ $carta->snippet }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <!-- Colonna destra -->
                <div class="col-md-6">
                    <div class="aggiunte mb-4">
                        <h3 class="mb-3">Carte aggiunte</h3>
                        <div class="contenuto">

                        </div>
                    </div>
                    <div class="rimosse">
                        <h3 class="mb-3">Carte rimosse</h3>
                    </div>
                    <div class="contenuto">

                    </div>
                    <div class="form">
                        <form method="POST" action="{{ route('mazzo.save', ['user' => $user, 'mazzo' => $deck]) }}">
                            @csrf
                            <button type="submit" class="btn btn-success">Save</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
@section('scripts')
    <script>
        const mazzo;
        const aggiunte;
        const rimosse;

        function refresh(){

        }

        function aggiungiCarta(carta) {
            // Aggiungi la carta alla lista delle carte aggiunte
            aggiunte.push(carta);
            mazzo.push(carta);
            refresh();
        }

        function rimuoviCarta(carta) {
            // Aggiungi la carta alla lista delle carte rimosse
            rimosse.push(carta);
            mazzo.pop(carta);
            refresh();
        }
    </script>
@endsection