<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{$carta["snippet"]}}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>{{ $carta->nome }} 
                    @if(strlen($carta->titolo) > 0)
                    <small class="text-muted">{{ strtoupper($carta->titolo) }}</small>
                    @endif
                </h2>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <table class="table table-striped">
                            <tbody>
                                <tr>
                                    <th>Espansione</th>
                                    <td>{{ $carta->espansione }}</td>
                                </tr>
                                <tr>
                                    <th>Numero</th>
                                    <td>{{ $carta->numero }}</td>
                                </tr>
                                <tr>
                                    <th>Aspetto Primario</th>
                                    <td>{{ $carta->aspettoPrimario }}</td>
                                </tr>
                                @if($carta->aspettoSecondario)
                                <tr>
                                    <th>Aspetto Secondario</th>
                                    <td>{{ $carta->aspettoSecondario }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <th>Tipo</th>
                                    <td>{{ $carta->tipo }}</td>
                                </tr>
                                <tr>
                                    <th>Rarità</th>
                                    <td>{{ $carta->rarita }}</td>
                                </tr>
                                @if($carta->costo !== null)
                                <tr>
                                    <th>Costo</th>
                                    <td>{{ $carta->costo }}</td>
                                </tr>
                                @endif
                                @if($carta->vita !== null)
                                <tr>
                                    <th>Vita</th>
                                    <td>{{ $carta->vita }}</td>
                                </tr>
                                @endif
                                @if($carta->potenza !== null)
                                <tr>
                                    <th>Potenza</th>
                                    <td>{{ $carta->potenza }}</td>
                                </tr>
                                @endif
                                @if($carta->tratti)
                                <tr>
                                    <th>Tratti</th>
                                    <td>{{ $carta->tratti }}</td>
                                </tr>
                                @endif
                                @if($carta->arena)
                                <tr>
                                    <th>Arena</th>
                                    <td>{{ $carta->arena }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <th>Unica</th>
                                    <td>{{ $carta->unica ? 'Sì' : 'No' }}</td>
                                </tr>
                                <tr>
                                    <th>Artista</th>
                                    <td>{{ $carta->artista }}</td>
                                </tr>
                                <tr>
                                    <th>Data di uscita</th>
                                    <td>{{ $carta->uscita->format('d/m/Y') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>immagine</h5>
                                @if ($carta->tipo == "Leader")
                                    <a onclick="toggleFrontCard(this)" class="btn btn-secondary">
                                        gira la carta
                                    </a>
                                @endif
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <img src="https://swudb.com/images/cards/{{ $carta->espansione }}/<?=str_pad($carta->numero, 3, '0', STR_PAD_LEFT)?>.png" alt="errere nel caricamento dell'immagine">
                                </p>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Descrizione</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">{!! nl2br(e($carta->descrizione)) !!}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="d-flex justify-content-between">
                    <a href="/carte" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alla lista
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
<style>
    img{
        max-width: 100%;
        max-height: 100%;
    }
</style>
<script>
    function toggleFrontCard(button) {
        event.preventDefault();
        let card = button.closest('.card');
        let img = card.querySelector('img');
        img.src = toggleLink(img.src);
    }

    function toggleLink(link){
        if(link.includes('portrait')) {
            console.log("true");
            console.log("il link attuale è: " + link);
            link = link.replace('-portrait', '');
            console.log("il nuovo link è: " + link);
        } else {
            console.log("false");
            console.log("il link attuale è: " + link);
            link = link.replace('.png', '-portrait.png');
            console.log("il nuovo link è: " + link);
        }
        return link;
    }
</script>
</html>