package com.example.webscraping;

public class ThreadParse extends java.lang.Thread{
    private Elenchi elenco;

    public ThreadParse(Elenchi elenco) {
        this.elenco = elenco;
    }
    @Override
    public void run() {
        String url;
        while ((url = elenco.getLink(this)) != null){
            try{
                Scan.alert(url);
                Carta carta = new Carta(url, elenco.tm);
                System.out.println(carta);
                elenco.tm.addMessage("cid:\t" + carta.cid + "\ncarta: " + carta.espansione.toUpperCase() + " - " + carta.numero + " " + carta.nome + " " + carta.titolo.toUpperCase());
                elenco.tm.addMessage(elenco.progresso());
                elenco.add(carta);
            }catch (org.openqa.selenium.NoSuchElementException e){
                elenco.tm.addMessage("errore: " + url);
            }
        }
        this.interrupt();
    }
}