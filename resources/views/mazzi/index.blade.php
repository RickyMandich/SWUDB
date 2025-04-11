@extends('layouts.app')
@section('content')
    <div class="container content">
        <div class="table-container">
            @if(count($result) != 0)
                <table class="table table-hover table-striped table-bordered">
                    <thead class="table-dark sticky-top">
                        <tr class="table-secondary">
                            @foreach ($result[array_key_first($result)][0] as $key=> $value)
                                <th class="z-1" scope="col">{{ $key }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 0;?>
                        @foreach ($result as $name=>$deck)
                            <tr onclick="toggleDeck({{ $i }})" id="d{{ $i }}" class="mazzo table-info">
                                <td class="text-center" colspan="{{ count($deck[0]) }}">
                                    {{ $name }}
                                </td>
                            </tr>
                            @foreach ($deck as $card)
                                <tr id="c{{ $i }}" class="carta">
                                    @foreach ($card as $key => $value)
                                        <td <?php if($key == "snippet"){echo 'scope="row"';} ?>>{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach 
                            <?php $i++;?>
                        @endforeach
                    </tbody>
                </table>
            @else
                non hai nessun mazzo (i mazzi vuoti non vengono considerati)
            @endif
        </div>
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