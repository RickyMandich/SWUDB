@extends('layouts.app')

@section('title', 'Gestione Logs - Admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-file-alt me-2"></i>Gestione Logs</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        @foreach($breadcrumbs as $index => $breadcrumb)
                            @if($loop->last)
                                <li class="breadcrumb-item active" aria-current="page">
                                    <i class="fas fa-{{ $breadcrumb['name'] === 'logs' ? 'folder-open' : ($isFile ?? false ? 'file-alt' : 'folder') }} me-1"></i>
                                    {{ $breadcrumb['name'] }}
                                </li>
                            @else
                                <li class="breadcrumb-item">
                                    <a href="{{ route('admin.logs', ['path' => $breadcrumb['path']]) }}" class="text-decoration-none">
                                        <i class="fas fa-{{ $breadcrumb['name'] === 'logs' ? 'home' : 'folder' }} me-1"></i>
                                        {{ $breadcrumb['name'] }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            </div>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($isFile ?? false)
                <!-- File Content Display -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>{{ basename($currentPath) }}
                        </h5>
                        <div class="text-muted small">
                            @if($fileContent['size'] ?? false)
                                {{ App\Http\Controllers\LogsController::formatFileSize($fileContent['size']) }}
                                @if(isset($fileContent['lines']))
                                    • {{ number_format($fileContent['lines']) }} righe
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if($fileContent['error'] ?? false)
                            <div class="alert alert-warning m-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>{{ $fileContent['error'] }}
                            </div>
                        @else
                            <div class="position-relative">
                                <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" 
                                        onclick="copyToClipboard()" style="z-index: 10;">
                                    <i class="fas fa-copy me-1"></i>Copia
                                </button>
                                <pre class="bg-dark text-light p-3 m-0" style="max-height: 70vh; overflow-y: auto; font-size: 0.85rem; line-height: 1.4;"><code id="logContent">{{ $fileContent['content'] ?? '' }}</code></pre>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <!-- Directory Listing -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-folder-open me-2"></i>Contenuto Directory
                            <span class="text-muted ms-2">({{ count($items) }} elementi)</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        @if(empty($items))
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <p>Directory vuota</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th><i class="fas fa-file me-1"></i>Nome</th>
                                            <th class="text-center"><i class="fas fa-info-circle me-1"></i>Tipo</th>
                                            <th class="text-end"><i class="fas fa-weight-hanging me-1"></i>Dimensione</th>
                                            <th class="text-end"><i class="fas fa-clock me-1"></i>Modificato</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($items as $item)
                                            <tr class="align-middle">
                                                <td>
                                                    <a href="{{ route('admin.logs', ['path' => $item['path']]) }}" 
                                                       class="text-decoration-none d-flex align-items-center">
                                                        @if($item['type'] === 'directory')
                                                            <i class="fas fa-folder text-warning me-2"></i>
                                                        @else
                                                            <i class="fas fa-file-alt text-info me-2"></i>
                                                        @endif
                                                        <span class="text-truncate">{{ $item['name'] }}</span>
                                                    </a>
                                                </td>
                                                <td class="text-center">
                                                    @if($item['type'] === 'directory')
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="fas fa-folder me-1"></i>Cartella
                                                        </span>
                                                    @else
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-file me-1"></i>File
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-end text-muted">
                                                    @if($item['size'] !== null)
                                                        {{ App\Http\Controllers\LogsController::formatFileSize($item['size']) }}
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-end text-muted">
                                                    <small>{{ date('d/m/Y H:i', $item['modified']) }}</small>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Back to Dashboard -->
            <div class="mt-4">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard() {
    const content = document.getElementById('logContent').textContent;
    navigator.clipboard.writeText(content).then(function() {
        // Show success feedback
        const button = event.target.closest('button');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check me-1"></i>Copiato!';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-success');
        
        setTimeout(function() {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(function(err) {
        console.error('Errore nella copia: ', err);
        alert('Errore nella copia del contenuto');
    });
}

// Auto-scroll to bottom for log files
document.addEventListener('DOMContentLoaded', function() {
    const logContainer = document.querySelector('pre');
    if (logContainer && window.location.search.includes('scroll=bottom')) {
        logContainer.scrollTop = logContainer.scrollHeight;
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+C to copy content (when viewing file)
    if (e.ctrlKey && e.key === 'c' && document.getElementById('logContent')) {
        e.preventDefault();
        copyToClipboard();
    }
    
    // Escape to go back
    if (e.key === 'Escape') {
        window.history.back();
    }
});
</script>

<style>
.breadcrumb-item + .breadcrumb-item::before {
    content: ">";
    color: #6c757d;
}

.table-hover tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

pre code {
    white-space: pre-wrap;
    word-wrap: break-word;
}

.text-truncate {
    max-width: 300px;
}

@media (max-width: 768px) {
    .text-truncate {
        max-width: 150px;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>
@endsection
