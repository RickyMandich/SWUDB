function filtra() {
    let filtro = document.getElementById("filtroTratto").value;
    output(filtro)
    let tratti = document.getElementsByClassName("tratto");
    for(let i=0;i<tratti.length;i++){
        tratti[i].style.display = "inline-flex";
        output(tratti[i].innerHTML);
        if(!tratti[i].innerHTML.includes(filtro)){
            tratti[i].style.display = "none";
        }/**/
    }
    /*
    tratti[0].toString()
    output(tratti[0].innerHTML);
    console.log(tratti[0]);
    */
}

addEventListener("DOMContentLoaded", (event) => {
    let tratti = Array.from(document.getElementsByClassName("tratto"));
    let maxHeight = Math.max(...tratti.map(el => el.offsetHeight));

    tratti.forEach(tratto => {
        tratto.style.height = `${maxHeight}px`;
    });
});

function output(out){
    out = out + "\n";
    console.log(out);
}

document.addEventListener("DOMContentLoaded", function() {
    let input = document.getElementById("filtroTratto");
    if (input) {
        input.addEventListener("keyup", function(event) {
            if (event.key === "Enter") {
                event.preventDefault();
                let filtroButton = document.getElementById("launchFiltro");
                if (filtroButton) {
                    filtroButton.click();
                } else {
                    console.error("Pulsante di filtro non trovato");
                }
            }
        });
    } else {
        console.error("Elemento di input del filtro non trovato");
    }
});