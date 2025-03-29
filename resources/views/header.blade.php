<header>
    <h1>SWUDB</h1>
</header>
<nav class="d-flex justify-content-between">
    <div class="d-flex">
        Carte
        mazzi
    </div>
    <div class="d-flex">
        @if (Auth::check())
            <span class="ms-3">account</span>
        @else
            <span class="ms-3">login</span>
            <span class="ms-3">signin</span>
        @endif
    </div>
</nav>