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
    @endif
@endsection
@section('script')
    <script>
        const carte = [];
        const mazzo = @json($mazzo);
        const aggiunte = [];
        const rimosse = [];
        
        let cards = @json($carte);
        cards.forEach((carta) => {
            carte[`${carta.espansione}-${carta.numero}`] = carta;
        });

        function refresh() {
            let contenuto = "";
            mazzo.forEach((carta, index) => {
                // Serializza l'oggetto carta in JSON e lo passa come stringa
                const cartaJSON = JSON.stringify(carta);
                contenuto += `<span class="${carta.espansione}-${carta.numero} d-flex mt-4">
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
            console.log(`aumento ${id}`);
            // Trova la carta nel mazzo
            let index = mazzo.findIndex(c => 
                c.espansione === carte[id].espansione && 
                c.numero === carte[id].numero
            );

            console.log(carte[id]);
            
            if (index !== -1) {
                console.log("carta già presente, aumento le copie di uno");
                // Aumenta il numero di copie
                mazzo[index].copie++;
            }else{
                console.log("carta non presente nel mazzo, la aggiungo");
                mazzo[id] = carte[id];
                mazzo[id].copie = 1;
            }
            // Registra l'operazione
            index = aggiunte.findIndex(c => 
                c.espansione === carte[id].espansione && 
                c.numero === carte[id].numero
            );
            
            if (index !== -1) {
                console.log("carta già presente nelle aggiunte, aumento le copie di uno");
                // Aumenta il numero di copie
                aggiunte[index].copie++;
            }else{
                console.log("carta non presente nelle aggiunte, la aggiungo");
                aggiunte[id] = carte[id];
                aggiunte[id].copie = 1;
            }
            
            refresh();
        }

        function diminuisciCopia(id) {
            console.log(`diminuisco ${id}`);
            // Trova la carta nel mazzo
            const index = mazzo.findIndex(c => 
                c.espansione === carte[id].espansione && 
                c.numero === carte[id].numero
            );
            
            if (index !== -1 && mazzo[index].copie > 0) {
                // Diminuisci il numero di copie
                mazzo[index].copie--;
                // Registra l'operazione
                rimosse[id] = carte[id];
                rimosse[id].copie = 1;
                
                // Se il numero di copie è 0, possiamo rimuovere la carta dal mazzo
                if (mazzo[index].copie === 0) {
                    mazzo.splice(index, 1);
                }
            }
            
            refresh();
        }

        refresh();
    </script>
@endsection