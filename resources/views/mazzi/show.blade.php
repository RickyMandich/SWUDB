@extends('layouts.app')
@section('title', "$nome di $user")
@section('content')
    <div class="header text-center mb-4">
        <h1>
            {{ $nome }} <button onclick="popUpCarte()" class="btn btn-primary"> + </button>
        </h1>
        <h4>
            <small class="text-muted">di {{ $user }}</small>
        </h4>
        <h6>
            @if ($size == 1)
                in questo mazzo è presente {{ $size }} carta
            @else
                in questo mazzo sono presenti {{ $size }} carte
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
                    <h3>Carte aggiunte</h3>
                    <div class="mb-4 contenuto">

                    </div>
                </div>
                <div class="rimosse">
                    <h3>Carte rimosse</h3>
                    <div class="mb-4 contenuto">
                    </div>
                </div>
                @if ($proprietario)
                    <div class="form">
                        <form method="POST" action="{{ route('mazzo.save', ['user' => $user, 'mazzo' => $deck]) }}">
                            @csrf
                            <button type="submit" class="btn btn-success">Save</button>
                            <span id="modifiche"></span>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script>
        const carte = new Map();
        const mazzo = new Map();

        @if($proprietario)
            const aggiunte = new Map();
            const rimosse = new Map();

            let cards = @json($carte);
            cards.forEach((carta) => {
                carte.set(`${carta.espansione}-${carta.numero}`, carta);
            });
        @endif
        @json($mazzo).forEach((carta) => {
            mazzo.set(`${carta.espansione}-${carta.numero}`, carta);
        });

        let popup;

        function refresh() {
            let contenuto = "";
            mazzo.forEach((carta) => {
                contenuto += `<span class="d-flex mt-4">
                    ${carta.copie}x
                    @if ($proprietario)
                        <button type="button" onclick='aumentaCopia("${carta.espansione}-${carta.numero}")' class="btn btn-success rounded-0 rounded-start-1 border-end-0 py-1 px-2 lh-1">+</button>
                        <button type="button" onclick='diminuisciCopia("${carta.espansione}-${carta.numero}")' class="btn btn-danger rounded-0 rounded-end-1 border-start-0 py-1 px-2 lh-1">-</button>
                    @endif
                    ${carta.snippet}
                </span>`;
            });
            document.querySelector('.mazzo .contenuto').innerHTML = contenuto;
            @if($proprietario)
                let form = "";

                // Aggiorna anche la sezione delle carte aggiunte
                let contenutoAggiunte = "";
                aggiunte.forEach(carta => {
                    contenutoAggiunte += `<span class="d-flex mt-4">
                        ${carta.copie}x
                        @if ($proprietario)
                            <button type="button" onclick='diminuisciCopia("${carta.espansione}-${carta.numero}")' class="btn btn-danger rounded-1 border-0 py-1 px-2 lh-1">-</button>
                        @endif
                        ${carta.snippet}
                    </span>`;
                    form += `<input type="hidden" name="carte[${carta.espansione}-${carta.numero}]" value="A-${carta.copie}">`;
                });
                document.querySelector('.aggiunte .contenuto').innerHTML = contenutoAggiunte;
                
                // Aggiorna la sezione delle carte rimosse
                let contenutoRimosse = "";
                rimosse.forEach(carta => {
                    contenutoRimosse += `<span class="d-flex mt-4">
                        ${carta.copie}x
                        @if ($proprietario)
                            <button type="button" onclick='aumentaCopia("${carta.espansione}-${carta.numero}")' class="btn btn-success rounded-1 border-0 py-1 px-2 lh-1">+</button>
                        @endif
                        ${carta.snippet}
                    </span>`;
                    form += `<input type="hidden" name="carte[${carta.espansione}-${carta.numero}]" value="R-${carta.copie}">`;
                });
                document.querySelector('.rimosse .contenuto').innerHTML = contenutoRimosse;
                document.querySelector('#modifiche').innerHTML = form;
            @endif
        }

        @if($proprietario)
            function aumentaCopia(id) {
                let aggiungi = true;
                if(mazzo.has(id)){
                    if(mazzo.get(id).copie < mazzo.get(id).maxCopie) {
                        mazzo.get(id).copie++;
                    }else{
                        alert("Hai raggiunto il numero massimo di copie di questa carta");
                        return false;
                    }
                }else{
                    mazzo.set(id, structuredClone(carte.get(id)));
                    mazzo.get(id).copie = 1;
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
                        aggiunte.set(id, structuredClone(carte.get(id)));
                        aggiunte.get(id).copie = 1;
                    }
                }
                
                refresh();
                return true;
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
                    }else if(rimosse.has(id)){
                        rimosse.get(id).copie++;
                    }else{
                        rimosse.set(id, structuredClone(carte.get(id)));
                        rimosse.get(id).copie = 1;
                    }
                }else{
                    alert("Non puoi rimuovere una carta che non è nel mazzo");
                    return false;
                }
                
                refresh();
                return true;
            }
        @endif

        function popUpCarte(){
            popup.show();
        }

        function showCopie() {
            let id = document.querySelector('#id').value;
            let carta = carte.get(id);
            let copie = "";
            for (let i = 1; i <= carta.maxCopie; i++) {
                copie += 
                `<input type="radio" class="btn-check" name="copie" id="copie-${i}" autocomplete="off"${(i==1 ? 'checked' : '')}>
                <label class="btn btn-outline-success" for="copie-${i}">${i}</label>`;
            }
            invio = `<input type="button" class="btn btn-success" value="aggiungi" onclick="confermaInserimento('${id}', this.closest('div.popup-carte').querySelector('input[type=radio]:checked').id.split('-')[1])">`;
            document.querySelector('#copie').innerHTML = copie;
            document.querySelector('#invio').innerHTML = invio;
        }

        function confermaInserimento(id, copie) {
            popup.hide();
            let continua = true;
            for(let i = 0; i < copie && continua; i++) {
                continua = aumentaCopia(id);
            }
        }

        window.addEventListener('load', function(event){
            popup = new Popup({
                title: 'Aggiungi carte al mazzo',
                content: 
                `<div class="popup-carte">
                    <select class="form-select form-select-lg mb-3" oninput="showCopie()" name="id" id="id">
                    <option value="" selected disabled>---Seleziona una carta---</option>
                        @foreach ($carte as $carta)
                            @if ($mazzo->has($carta->espansione . '-' . $carta->numero) && $mazzo->get($carta->espansione . '-' . $carta->numero)->copie >= $carta->maxCopie)
                            @else
                                <option value="{{ $carta->espansione }}-{{ $carta->numero }}">{{ $carta->snippet }}</option>
                            @endif
                        @endforeach
                    </select>
                    <div id="copie"></div>
                    <div id="invio"></div>
                </div>`,
                buttons: [
                    {
                        text: 'x',
                        className: 'btn btn-secondary',
                        onClick: function() {
                            popup.close();
                        }
                    }
                ]
            });

            async function a(popup) {
                await new Promise(resolve => setTimeout(resolve, 2000));
                popup.hide();
            }
            @if(session('warning'))
                let warning = new Popup({
                    hideTitle: true,
                    title: 'Warning',
                    content: `{{ session('warning') }}`,
                    buttons: [
                        {
                            text: 'x',
                            className: 'btn btn-secondary',
                            onClick: function() {
                                warning.close();
                            }
                        }
                    ]
                });
                warning.show();
                a(warning);
            @endif
            @if(session('success'))
                let success = new Popup({
                    hideTitle: true,
                    content: `{{ session('success') }}`,
                    buttons: [
                        {
                            text: 'x',
                            className: 'btn btn-secondary',
                            onClick: function() {
                                success.close();
                            }
                        }
                    ]
                });
                success.show();
                a(success);
            @endif
        });

        refresh();
    </script>
@endsection