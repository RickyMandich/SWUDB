document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('.deck-header');

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

    const tr = Array.from(document.getElementsByTagName("tr"));
    let mostra = false;
    tr.forEach((row, index) => {
        row.addEventListener('click', function() {
            tr.forEach(riga => {
                let header = riga.className === "deck-header";
                console.log(riga.className);
                console.log(header);
                if(header){
                    mostra = riga.getElementsByTagName("input")[0].checked;
                }else{
                    if(mostra) {
                        riga.style.display = "table-row";
                        console.log("mostro la carta")
                    }
                    else {
                        riga.style.display = "none";
                    }
                }
            });
        });
        let header = row.className === "deck-header";
        console.log(row.className);
        console.log(header);
        if(header){
            mostra = row.getElementsByTagName("input")[0].checked;
        }else{
            if(mostra) row.style.display = "table-row";
            else row.style.display = "none";
        }
    });
});