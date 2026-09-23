@php /** @var \Illuminate\Support\Collection<App\Models\Card> $cards */ @endphp
<x-mail::message>
# Nuove carte uscite su {{ config('app.name') }}

Sono uscite nuove carte su {{ config('app.name') }}.
Visualizzale cliccando il pulsante qui sotto:

<x-mail::button :url="{{ route('cards.new-release', ['release_date' => $cards->first()->release_date]) }}">
    Scopri le nuove carte
</x-mail::button>

Le carte inserite sono:
@foreach ($cards as $card)
- [{{ $card->name }} ({{ $card->expansion }}-{{ $card->number }})]({{ route('card.show', ['expansion' => $card->expansion, 'number' => $card->number]) }})
@endforeach
</x-mail::message>