{{-- Tile compatto per una carta nei pannelli "Mazzo" / "Carte aggiunte" / "Carte rimosse" --}}
@php
    $showPlus = $showPlus ?? false;
    $showMinus = $showMinus ?? false;
    $rarita = $carta['rarita'] ?? '';
    $raritaClass = strtolower(str_replace(' ', '', $rarita));
@endphp
<div class="col-12 col-sm-6 col-lg-4 mb-3 deck-card-tile">
    <div class="innerCarta rounded-4 border-primary-subtle bg-secondary-subtle p-2 h-100 d-flex">
        @if(!empty($carta['frontArt']))
            <img src="{{ $carta['frontArt'] }}" alt="{{ $carta['snippet'] ?? '' }}" class="deck-card-tile-img rounded-3 me-2">
        @endif
        <div class="flex-grow-1">
            @if(isset($carta['espansione']) && isset($carta['numero']) && !empty($carta['espansione']) && $carta['numero'] > 0)
                <a href="{{ route('carta', ['espansione' => $carta['espansione'], 'numero' => $carta['numero']]) }}" target="_blank" class="text-decoration-none">
                    <div class="fw-bold small">{{ $carta['snippet'] ?? '' }}</div>
                </a>
            @else
                <div class="fw-bold small">{{ $carta['snippet'] ?? 'Carta non disponibile' }}</div>
            @endif
            @if(!empty($rarita))
                <div class="small {{ $raritaClass }}">{{ $rarita }}</div>
            @endif
            <div class="d-flex align-items-center mt-1">
                <span class="badge bg-primary me-2">{{ $carta['copie'] ?? 1 }}x</span>
                @if($showMinus)
                    <button type="button" wire:click="diminuisciCopia('{{ $id }}')" class="btn btn-danger btn-sm py-0 px-2 lh-1 me-1" title="Rimuovi una copia">
                        <i class="fas fa-minus"></i>
                    </button>
                @endif
                @if($showPlus)
                    <button type="button" wire:click="aumentaCopia('{{ $id }}')" class="btn btn-success btn-sm py-0 px-2 lh-1" title="Aggiungi una copia">
                        <i class="fas fa-plus"></i>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
