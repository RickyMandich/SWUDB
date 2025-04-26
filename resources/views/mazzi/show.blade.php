@extends('layouts.app')
@section('content')
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
                <div class="mazzo">
                    <h3 class="mb-3">Mazzo</h3>
                    <div class="contenuto">
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
                    <div class="contenuto">
                    </div>
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
@endsection
@section('script')
    <script>
        const carte = new Map();
        const mazzo = new Map();
        @json($mazzo).forEach((carta) => {
            mazzo.set(`${carta.espansione}-${carta.numero}`, carta);
        });
        const aggiunte = new Map();
        const rimosse = new Map();
        
        let cards = @json($carte);
        cards.forEach((carta) => {
            carte.set(`${carta.espansione}-${carta.numero}`, carta);
        });

        function refresh() {
            let contenuto = "";
            mazzo.forEach((carta) => {
                contenuto += `<span class="d-flex mt-4">
                    ${carta.copie}
                    <button type="button" onclick='aumentaCopia("${carta.espansione}-${carta.numero}")' class="btn btn-success rounded-0 rounded-start-1 border-end-0 py-1 px-2 lh-1">+</button>
                    <button type="button" onclick='diminuisciCopia("${carta.espansione}-${carta.numero}")' class="btn btn-danger rounded-0 rounded-end-1 border-start-0 py-1 px-2 lh-1">-</button>
                    ${carta.snippet}
                </span>`;
            });
            document.querySelector('.mazzo .contenuto').innerHTML = contenuto;
            
            // Aggiorna anche la sezione delle carte aggiunte
            let contenutoAggiunte = "";
            aggiunte.forEach(carta => {
                contenutoAggiunte += `<div>${carta.snippet}</div>`;
            });
            document.querySelector('.aggiunte .contenuto').innerHTML = contenutoAggiunte;
            
            // Aggiorna la sezione delle carte rimosse
            let contenutoRimosse = "";
            rimosse.forEach(carta => {
                contenutoRimosse += `<div>${carta.snippet}</div>`;
            });
            document.querySelector('.rimosse .contenuto').innerHTML = contenutoRimosse;
        }

        function aumentaCopia(id) {
            let aggiungi = true;
            if(mazzo.has(id)){
                if(mazzo.get(id).copie < carte.get(id).maxCopie) {
                    mazzo.get(id).copie++;
                }else{
                    alert("Hai raggiunto il numero massimo di copie di questa carta");
                    aggiungi = false;
                }
            }
            if(aggiungi){
                if(rimosse.has(id)){
                    if(rimosse.get(id).copie > 1) {
                        rimosse.get(id).copie--;
                    } else {
                        rimosse.delete(id);
                    }
                }else if(aggiunte.has(id)){
                    aggiunte.get(id).copie++;
                }else{
                    aggiunte.set(id, carte.get(id));
                    aggiunte.get(id).copie = 1;
                }
            }
            
            refresh();
        }

        function diminuisciCopia(id) {
            if(mazzo.has(id)){
                if(mazzo.get(id).copie > 1) {
                    mazzo.get(id).copie--;
                }else{
                    mazzo.delete(id);
                }
                if(aggiunte.has(id)){
                    if(aggiunte.get(id).copie > 1) {
                        aggiunte.get(id).copie--;
                    } else {
                        aggiunte.delete(id);
                    }
                }
                if(rimosse.has(id)){
                    rimosse.get(id).copie++;
                }else{
                    rimosse.set(id, carte.get(id));
                    rimosse.get(id).copie = 1;
                }
            }else{
                alert("Non puoi rimuovere una carta che non è nel mazzo");
            }
            
            refresh();
        }

        refresh();
    </script>
@endsection