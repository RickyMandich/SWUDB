@extends('layouts.app')
@section('content')
    <div class="container content">
        @if(count($decks) != 0)
            <ul>
                @foreach ($decks as $deck)
                    <li>
                        {{ $deck->nome }}
                    </li>
                @endforeach
            </ul>
        @else
            non hai nessun mazzo (i mazzi vuoti non vengono considerati)
        @endif
    </div>
@endsection
@section('script')
<script>
    function toggleDeck(id){
        let hide = document.querySelector("#c"+id).style.display != "none";
        let cards = document.querySelectorAll(".carta");
        cards.forEach((card) => {
            card.style.display = "none";
        });
        if(!hide){
            cards = document.querySelectorAll("#c"+id);
            cards.forEach((card) => {
                card.style.display = "table-row";
            });
        }
    }
    toggleDeck(0);
</script>
@endsection