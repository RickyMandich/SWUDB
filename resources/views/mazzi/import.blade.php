@extends('layouts.app')

@section('title', 'Importa Mazzo')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-file-import me-2"></i>Importa Mazzo
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Importa un mazzo da file (TXT o JSON) o da URL. I formati supportati sono quelli ufficiali di Star Wars: Unlimited.
                    </p>

                    <!-- Tabs per scegliere il metodo di importazione -->
                    <ul class="nav nav-tabs" id="importTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="file-tab" data-bs-toggle="tab" data-bs-target="#file-import" type="button" role="tab">
                                <i class="fas fa-file-upload me-1"></i>Da File
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="url-tab" data-bs-toggle="tab" data-bs-target="#url-import" type="button" role="tab">
                                <i class="fas fa-link me-1"></i>Da URL
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3" id="importTabsContent">
                        <!-- Importazione da File -->
                        <div class="tab-pane fade show active" id="file-import" role="tabpanel">
                            <form id="fileImportForm" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label for="deck_name_file" class="form-label">Nome del Mazzo</label>
                                    <input type="text" class="form-control" id="deck_name_file" name="deck_name" required maxlength="500">
                                    <div class="form-text">Il nome del mazzo nel tuo account</div>
                                </div>

                                <div class="mb-3">
                                    <label for="file" class="form-label">File del Mazzo</label>
                                    <input type="file" class="form-control" id="file" name="file" accept=".txt,.json" required>
                                    <div class="form-text">Formati supportati: TXT, JSON (max 2MB)</div>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="public_file" name="public">
                                    <label class="form-check-label" for="public_file">
                                        Rendi il mazzo pubblico
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-1"></i>Importa da File
                                </button>
                            </form>
                        </div>

                        <!-- Importazione da URL -->
                        <div class="tab-pane fade" id="url-import" role="tabpanel">
                            <form id="urlImportForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="deck_name_url" class="form-label">Nome del Mazzo</label>
                                    <input type="text" class="form-control" id="deck_name_url" name="deck_name" required maxlength="500">
                                    <div class="form-text">Il nome del mazzo nel tuo account</div>
                                </div>

                                <div class="mb-3">
                                    <label for="url" class="form-label">URL del File o Mazzo</label>
                                    <input type="url" class="form-control" id="url" name="url" required>
                                    <div class="form-text">
                                        URL diretto al file TXT/JSON del mazzo o link SWUDB<br>
                                        <small class="text-muted">Esempio SWUDB: https://swudb.com/deck/HBzjsPUBBGYTt</small>
                                    </div>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="public_url" name="public">
                                    <label class="form-check-label" for="public_url">
                                        Rendi il mazzo pubblico
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-download me-1"></i>Importa da URL
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Area per messaggi -->
                    <div id="importMessages" class="mt-3"></div>

                    <!-- Esempi di formato -->
                    <div class="mt-4">
                        <h6>Esempi di Formato:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Formato TXT:</h6>
                                <pre class="bg-dark-subtle p-2 small"><code>Leaders
1 | Darth Revan | Scourge of the Old Republic

Base
1 | Temple of Destruction

Deck
3 | Daring Raid
2 | Force Throw</code></pre>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Formato JSON:</h6>
                                <pre class="bg-dark-subtle p-2 small"><code>{
  "metadata": {
    "name": "Deck Name",
    "author": "Author"
  },
  "leader": {"id": "LOF_17", "count": 1},
  "base": {"id": "LOF_25", "count": 1},
  "deck": [
    {"id": "SOR_134", "count": 3}
  ]
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileForm = document.getElementById('fileImportForm');
    const urlForm = document.getElementById('urlImportForm');
    const messagesDiv = document.getElementById('importMessages');

    // Gestione form importazione da file
    fileForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        importDeck('{{ route("mazzi.import.file") }}', formData, 'file');
    });

    // Gestione form importazione da URL
    urlForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        // Route originale ripristinata
        importDeck('{{ route("mazzi.import.url") }}', formData, 'url');
    });

    function importDeck(url, formData, type) {
        // Mostra loading
        showMessage('info', '<i class="fas fa-spinner fa-spin me-1"></i>Importazione in corso...');
        
        // Disabilita il form
        const submitBtn = document.querySelector(`#${type}ImportForm button[type="submit"]`);
        submitBtn.disabled = true;

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            console.log('Response ok:', response.ok);

            // Clona la response per poterla leggere due volte
            return response.clone().text().then(text => {
                console.log('Raw response text:', text);

                // Prova a parsare come JSON
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON data:', data);
                    return { success: true, data: data, rawText: text };
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.log('Response is not valid JSON, content preview:', text.substring(0, 500));
                    return { success: false, error: 'Invalid JSON response', rawText: text };
                }
            });
        })
        .then(result => {
            if (result.success && result.data.success) {
                showMessage('success', `<i class="fas fa-check me-1"></i>${result.data.message}`);

                // Redirect al mazzo dopo 2 secondi
                setTimeout(() => {
                    window.location.href = result.data.deck_url;
                }, 2000);
            } else if (result.success && result.data.error) {
                console.log('Server returned error:', result.data.error);
                if (result.data.debug) {
                    console.log('Debug info:', result.data.debug);
                }
                showMessage('danger', `<i class="fas fa-exclamation-triangle me-1"></i>${result.data.error}`);
            } else {
                console.log('Response parsing failed:', result);
                showMessage('danger', `<i class="fas fa-exclamation-triangle me-1"></i>Errore: risposta non valida dal server`);

                // Mostra un'anteprima della risposta per debug
                if (result.rawText) {
                    console.log('Full response for debugging:', result.rawText);
                    const preview = result.rawText.substring(0, 200);
                    showMessage('warning', `Debug: ${preview}...`);
                }
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showMessage('danger', '<i class="fas fa-exclamation-triangle me-1"></i>Errore di rete durante l\'importazione');
        })
        .finally(() => {
            // Riabilita il form
            submitBtn.disabled = false;
        });
    }

    function showMessage(type, message) {
        messagesDiv.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }
});
</script>
@endpush
