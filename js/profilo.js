document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('tbody .deck-header');

    headers.forEach((header, index) => {
        // Crea il radio button
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'deck';
        radio.id = `deck-${index}`;

        // Inserisci il radio nella prima cella dell'header
        const firstTd = header.querySelector('td:first-child');
        firstTd.insertBefore(radio, firstTd.firstChild);

        // Aggiungi event listener per gestire il click sull'intera riga
        header.addEventListener('click', function() {
            radio.checked = !radio.checked;
        });
    });

    const tr = Array.from(document.querySelectorAll("tbody tr"));
    let mostra = false;
    tr.forEach((row, index) => {
        row.addEventListener('click', function() {
            //let tr = 
            tr.forEach(riga => {
                let header = riga.className === "card-in-deck-row deck-header";
                if(header){
                    mostra = riga.getElementsByTagName("input")[0].checked;
                }else{
                    if(mostra) {
                        riga.style.display = "table-row";
                    }
                    else {
                        riga.style.display = "none";
                    }
                }
            });
        });
        let header = row.className === "card-in-deck-row deck-header";
        if(header){
            mostra = row.getElementsByTagName("input")[0].checked;
        }else{
            if(mostra) row.style.display = "table-row";
            else row.style.display = "none";
        }
    });
    let cards = Array.from(document.getElementsByClassName("deck-card"));
    cards.forEach(card =>{
        card.style.display = "none";
    });
});

function checkSelection() {
    let value = document.getElementById("into").value;
    const inputDiv = document.getElementById('newDeckName');
    if (value === 'nuovo mazzo') {
        inputDiv.style.display = 'inline-block';
        // Se c'era un valore precedente nell'input, lo ripristiniamo
        const savedValue = document.getElementById('newDeckName').value;
        if (savedValue) {
            document.getElementById('deckSelect').value = savedValue;
        }
    } else {
        inputDiv.style.display = 'none';
    }
}

function updateSelectValue() {
    let value = document.getElementById("newDeckName").value;
    // Aggiorna il valore della select con il contenuto dell'input
    document.getElementById('into').value = value;
    document.getElementById('show').value = value;
}

// Controlla lo stato iniziale al caricamento della pagina
window.onload = checkSelection()