@extends('layouts.app')
@section('content')
    @if($find)
        <div class="container">
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2>{{ $carta->nome }} 
                        @if(strlen($carta->titolo) > 0)
                        <small class="text-muted text-uppercase">{{ $carta->titolo }}</small>
                        @endif
                    </h2>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <th scope="row">espansione</th>
                                        <td>{{ $carta->espansione }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">numero</th>
                                        <td>{{ $carta->numero }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">aspettoPrimario</th>
                                        <td>{{ $carta->aspettoPrimario }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">aspettoSecondario</th>
                                        <td>{{ $carta->aspettoSecondario }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">tipo</th>
                                        <td>{{ $carta->tipo }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">rarita</th>
                                        <td>{{ $carta->rarita }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">costo</th>
                                        <td>{{ $carta->costo }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">vita</th>
                                        <td>{{ $carta->vita }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">potenza</th>
                                        <td>{{ $carta->potenza }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">tratti</th>
                                        <td>{{ $carta->tratti }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">arena</th>
                                        <td>{{ $carta->arena }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">artista</th>
                                        <td>{{ $carta->artista }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <div class="card immagine">
                                <div class="card-header d-flex justify-content-between">
                                    @if ($carta->tipo == "Leader")
                                        <a onclick="toggleFrontCard(this)" class="d-flex mx-auto btn btn-secondary">
                                            gira la carta
                                        </a>
                                    @endif
                                </div>
                                <div class="card-body">
                                    <p class="card-text">
                                        <img src="{{ $carta->frontArt }}" alt="errere nel caricamento dell'immagine">
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h5>Descrizione</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">{!! nl2br($carta->descrizione) !!}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="/carta/{{ $carta->espansione }}/{{ $carta->numero-1 }}" class="btn btn-secondary align-top">
                            &larr;Back
                        </a>
                        <a href="/carte" class="btn btn-secondary align-middle">
                            Torna alla lista
                        </a>
                        <a href="/carta/{{ $carta->espansione }}/{{ $carta->numero+1 }}" class="btn btn-secondary align-bottom">
                            Next&rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <script>
            const front = "{{ $carta->frontArt }}";
            const back = "{{ $carta->backArt }}";
            function toggleFrontCard(button) {
                event.preventDefault();
                let card = button.closest('.card');
                let img = card.querySelector('img');
                img.src = toggleLink(img.src);
            }

            function toggleLink(link){
                if(back == link){
                    link = front;
                    console.log("ho impostato il fronte della carta");
                    console.log(link);
                } else {
                    link = back;
                    console.log("ho impostato il retro della carta");
                    console.log(link);
                }
                return link;
            }
        </script>
    @else
        carta non trovata
    @endif
    <style>
        .immagine img{
            max-width: 100%;
            max-height: 100%;
        }
        
        .immagine img{
            width: 100%;
        }

        h5{
            display: inline-block;
        }
    </style>
@endsection