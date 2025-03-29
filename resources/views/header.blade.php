<div class="shadow-lg p-3 mb-5 bg-gradient rounded">
    <header>
        <h1>SWUDB</h1>
    </header>
    <nav class="d-flex justify-content-between">
        <div class="d-flex">
            <span class="ms-3"><a class="btn btn-outline-dark" href="/carte">Carte</a></span>
            <span class="ms-3"><a class="btn btn-outline-dark" href="/mazzi">Mazzi</a></span>
        </div>
        <div class="d-flex">
            @if (Auth::check())
                <span class="ms-3">account</span>
            @else
                <span class="ms-3"><a class="btn btn-outline-dark" href="/login">login</a></span>
                <span class="ms-3"><a class="btn btn-outline-dark" href="/signin">signin</a></span>
            @endif
        </div>
    </nav>
</div>