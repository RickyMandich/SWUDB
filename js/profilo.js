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
                let header = riga.className === "deck-header";
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
        let header = row.className === "deck-header";
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

function showMenuCollezione(imgElement) {
    // Mostro solo il menu relativo all'immagine cliccata
    // Risalgo al form parent e cerco il menu collezione al suo interno
    let parentForm = imgElement.closest('form');
    let menu = parentForm.querySelector('.menuCollezione');
    if (menu) {
        menu.style.display = 'block';
    }
}

function hideMenuCollezione(spanElement) {
    // Mostro solo il menu relativo all'immagine cliccata
    // Risalgo al form parent e cerco il menu collezione al suo interno
    let parentForm = spanElement.closest('form');
    let menu = parentForm.querySelector('.menuCollezione');
    if (menu) {
        menu.style.display = 'none';
    }
}