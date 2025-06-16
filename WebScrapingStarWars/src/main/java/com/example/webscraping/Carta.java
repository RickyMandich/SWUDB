package com.example.webscraping;

import com.google.gson.*;

import org.openqa.selenium.*;

import java.util.ArrayList;
import java.util.List;

public class Carta {
    public String cid;
    public String nome;
    public String titolo;
    public boolean unica;
    public String espansione;
    public int numero;
    public String aspettoPrimario;
    public String aspettoSecondario;
    public String tipo;
    public String[] tratti;
    public String descrizione;
    public String arena;
    public int costo;
    public int vita;
    public int potenza;
    public String rarita;
    public String artista;
    public String uscita;
    public String frontArt;
    public String backArt;

    public Carta(String cid,ThreadMessage tm) {
        String apiResult = apiCall(cid);
        JsonObject jsonObject = new Gson().fromJson(apiResult, JsonObject.class);

        // Accedi ai dati annidati
        JsonObject data = jsonObject.getAsJsonObject("data");
        JsonObject attributes = data.getAsJsonObject("attributes");

        // Estrai i dati base
        this.cid = cid;
        this.nome = attributes.get("title").isJsonNull() ? null : attributes.get("title").getAsString();
        this.titolo = attributes.get("subtitle").isJsonNull() ? "" : attributes.get("subtitle").getAsString();
        this.unica = attributes.get("unique").isJsonNull() ? false : attributes.get("unique").getAsBoolean();
        this.numero = attributes.get("cardNumber").isJsonNull() ? 0 : attributes.get("cardNumber").getAsInt();
        this.descrizione = attributes.get("textStyled").isJsonNull() ? null : attributes.get("textStyled").getAsString();
        this.costo = attributes.get("cost").isJsonNull() ? 0 : attributes.get("cost").getAsInt();
        this.vita = attributes.get("hp").isJsonNull() ? 0 : attributes.get("hp").getAsInt();
        if(!attributes.get("power").isJsonNull()) this.potenza = attributes.get("power").getAsInt();
        this.artista = attributes.get("artist").isJsonNull() ? null : attributes.get("artist").getAsString();

        // Estrai espansione (solo il codice)
        this.espansione = attributes.getAsJsonObject("expansion")
                                  .getAsJsonObject("data")
                                  .getAsJsonObject("attributes")
                                  .get("code").isJsonNull() ? null : attributes.getAsJsonObject("expansion")
                                  .getAsJsonObject("data")
                                  .getAsJsonObject("attributes")
                                  .get("code").getAsString();

        // Estrai arena
        JsonArray arenas = attributes.getAsJsonObject("arenas")
                                   .getAsJsonArray("data");
        if (arenas.size() > 0) {
            this.arena = arenas.get(0)
                              .getAsJsonObject()
                              .getAsJsonObject("attributes")
                              .get("name").isJsonNull() ? null : arenas.get(0)
                              .getAsJsonObject()
                              .getAsJsonObject("attributes")
                              .get("name").getAsString();
        }

        // Estrai aspetti
        JsonArray aspects = attributes.getAsJsonObject("aspects")
                                    .getAsJsonArray("data");
        if (!aspects.isEmpty()) {
            this.aspettoPrimario = traduciAspetto(aspects.get(0)
                                        .getAsJsonObject()
                                        .getAsJsonObject("attributes")
                                        .get("name").isJsonNull() ? "" : aspects.get(0)
                                        .getAsJsonObject()
                                        .getAsJsonObject("attributes")
                                        .get("name").getAsString());
        }
        if (aspects.size() > 1) {
            this.aspettoSecondario = traduciAspetto(aspects.get(1)
                                        .getAsJsonObject()
                                        .getAsJsonObject("attributes")
                                        .get("name").isJsonNull() ? "" : aspects.get(1)
                                        .getAsJsonObject()
                                        .getAsJsonObject("attributes")
                                        .get("name").getAsString());
        }
        JsonArray aspectDuplicates = attributes.getAsJsonObject("aspectDuplicates").getAsJsonArray("data");
        if(!aspectDuplicates.isEmpty()){
            this.aspettoSecondario = traduciAspetto(aspects.get(0)
                                        .getAsJsonObject()
                                        .getAsJsonObject("attributes")
                                        .get("name").getAsString());
        }

        // Estrai tipo
        this.tipo = attributes.getAsJsonObject("type")
                             .getAsJsonObject("data")
                             .getAsJsonObject("attributes")
                             .get("name").isJsonNull() ? "" : attributes.getAsJsonObject("type")
                             .getAsJsonObject("data")
                             .getAsJsonObject("attributes")
                             .get("name").getAsString();

        // Estrai tratti
        JsonArray traits = attributes.getAsJsonObject("traits")
                                   .getAsJsonArray("data");
        this.tratti = new String[traits.size()];
        for (int i = 0; i < traits.size(); i++) {
            this.tratti[i] = traits.get(i)
                                 .getAsJsonObject()
                                 .getAsJsonObject("attributes")
                                 .get("name").isJsonNull() ? null : traits.get(i)
                                 .getAsJsonObject()
                                 .getAsJsonObject("attributes")
                                 .get("name").getAsString();
        }

        // Estrai rarità
        this.rarita = attributes.getAsJsonObject("rarity")
                               .getAsJsonObject("data")
                               .getAsJsonObject("attributes")
                               .get("name").isJsonNull() ? null : attributes.getAsJsonObject("rarity")
                               .getAsJsonObject("data")
                               .getAsJsonObject("attributes")
                               .get("name").getAsString();

        //estrai link immagine
        this.frontArt = attributes.getAsJsonObject("artFront").getAsJsonObject("data").getAsJsonObject("attributes").get("url").getAsString();
        String header = nome + " " + titolo.toUpperCase() + "(" + espansione + "-" + numero + ")\t";
        Write.data.add(header + this.frontArt);


        if(this.tipo.equals("Leader")){
            this.backArt = attributes.getAsJsonObject("artBack").getAsJsonObject("data").getAsJsonObject("attributes").get("url").getAsString();
            this.descrizione = "<strong>-----NON SCHIERATO-----</strong><br>" + this.descrizione + "<strong>-----SCHIERATO-----</strong><br>" +(attributes.get("deployBoxStyled").isJsonNull() ? null : attributes.get("deployBoxStyled").getAsString());
            Write.data.add(header + this.backArt);
        }

        try{
            if(
                !aspettoPrimario.equals(aspettoSecondario)
                &&
                !new ArrayList<>(List.of(new String[]{"Bianco", "Nero"})).contains(aspettoSecondario)
                &&
                !aspettoSecondario.isEmpty()
            ){
                String temp = aspettoPrimario;
                aspettoPrimario = aspettoSecondario;
                aspettoSecondario = temp;
            }
        }catch (java.lang.NullPointerException ignore){}

        if(unica){
            this.nome = "⟡" + this.nome;
        }

        if(this.tipo.contains("Segnalin")){
            this.espansione = "T" + this.espansione;
        }

        this.uscita = attributes.get("publishedAt").isJsonNull() ? "" : attributes.get("publishedAt").getAsString();
        this.uscita = this.uscita.split("T")[0];
    }

    public Carta(WebDriver driver) {
        System.out.println("----------------------inizio costruttore----------------------");
        cid = Scan.extractCid(driver.getCurrentUrl());
        System.out.println("cid:\t" + cid);
        nome = driver.findElements(By.cssSelector("h3.text-2xl.font-extrabold")).getFirst().getText();
        System.out.println("nome fatto");
        try {
            titolo = driver.findElements(By.cssSelector("p.text-neutral-400.italic")).getFirst().getText();
            System.out.println("titolo fatto");
            unica = true;
        } catch (org.openqa.selenium.NoSuchElementException e) {
            unica = false;
        }
        try{
            this.numero = Integer.parseInt(driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]/div[6]/div[2]/div[7]/span[2]")).getFirst().getText().split("/")[0]);
            System.out.println("numero fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("numero:\t" + driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]/div[6]/div[2]/div[7]/span[2]")).size());
        }
        try{
            String[] aspetti = driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]/div[6]/div[1]/div[1]/span[2]")).getFirst().getText().split(", ");
            if(aspetti.length > 0) aspettoPrimario = traduciAspetto(aspetti[0]);
            if(aspetti.length > 1) aspettoSecondario = traduciAspetto(aspetti[1]);
            System.out.println("aspetti fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("aspetto:\t" + driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]/div[6]/div[1]/div[1]/span[2]")).size());
        }
        try{
            tipo = driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]/div[6]/div[2]/div[1]/span[2]")).getFirst().getText();
            System.out.println("tipo fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("tipo:\t" + driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]/div[6]/div[2]/div[1]/span[2]")).size());
        }
        try{
            tratti = driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]/div[6]/div[2]/div[3]/span[2]")).getFirst().getText().split(", ");
            System.out.println("tratti fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("tratti:\t" + driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]/div[6]/div[2]/div[3]/span[2]")).size());
        }
        try{
            descrizione = driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]")).getFirst().getText();
            System.out.println("descrizione fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("descrizione:\t" + driver.findElements(By.xpath("/html/body/div[17]/div/div/div/div[2]/div/div[3]/div[1]")).size());
        }
        try{
            arena = driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1) > div:nth-of-type(2) > span:nth-of-type(2)")).getFirst().getText();
            System.out.println("arena fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("arena:\t" + driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1) > div:nth-of-type(2) > span:nth-of-type(2)")).size());
        }
        try{
            costo = Integer.parseInt(driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1) > div:nth-of-type(3) > span:nth-of-type(2)")).getFirst().getText());
            System.out.println("costo fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("costo:\t" + driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1) > div:nth-of-type(3) > span:nth-of-type(2)")).size());
        }
        try{
            vita = Integer.parseInt(driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1) > div:nth-of-type(5) > span:nth-of-type(2)")).getFirst().getText());
            System.out.println("vita fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("vita:\t" +  driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1) > div:nth-of-type(5) > span:nth-of-type(2)")).size());
        }
        try{
            potenza = Integer.parseInt(driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1) > div:nth-of-type(4) > span:nth-of-type(2)")).getFirst().getText());
            System.out.println("potenza fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("potenza:\t" + driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1) > div:nth-of-type(4) > span:nth-of-type(2)")).size());
        }
        try{
            rarita = driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(2) > div:nth-of-type(4) > span:nth-of-type(2)")).getFirst().getText();
            System.out.println("rarita fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("rarita:\t" + driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(2) > div:nth-of-type(4) > span:nth-of-type(2)")).size());
        }
        try{
            artista = driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(2) > div:nth-of-type(6) > span:nth-of-type(2)")).getFirst().getText();
            System.out.println("artista fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("artista:\t" + driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(2) > div:nth-of-type(6) > span:nth-of-type(2)")).size());
        }
        driver.get("https://starwarsunlimited.com/it/cards?cid="+ cid +"#collection");
        try{
            espansione = driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(2) > div > table > tbody > tr:nth-of-type(1) > th > span:nth-of-type(2) > span")).getFirst().getText();
            System.out.println("espansione fatto");
        }catch (java.util.NoSuchElementException e){
            System.out.println("espansione:\t" + driver.findElements(By.cssSelector("html > body > div:nth-of-type(21) > div > div > div > div:nth-of-type(2) > div > div:nth-of-type(3) > div:nth-of-type(2) > div > table > tbody > tr:nth-of-type(1) > th > span:nth-of-type(2) > span")).size());
        }
        System.out.println("----------------------fine costruttore----------------------");
    }

    public static String traduciAspetto(String aspetto){
        return switch (aspetto) {
            case "Vigilanza" -> "Blu";
            case "Malvagità" -> "Nero";
            case "Eroismo" -> "Bianco";
            case "Autorità" -> "Verde";
            case "Offensiva" -> "Rosso";
            case "Astuzia" -> "Giallo";
            default -> aspetto;
        };
    }

    public static String apiCall(String cid) {
        String url = "https://admin.starwarsunlimited.com/api/card/" + cid + "?locale=it";
        System.out.println(url);
        return Scan.apiCall(url);
    }

    public String toString() {
        String info = "";
        info += "unica:\t" + unica + "\n";
        if(nome != null){
            info +="nome:\t"+nome +"\n";
        }
        if(titolo != null){
            info += "titolo:\t" + titolo + "\n";
        }
        if(espansione != null){
            info += "espansione:\t" + espansione + "\n";
        }
        info += "numero:\t" + numero + "\n";
        if(aspettoPrimario != null){
            info += "aspettoPrimario:\t" + aspettoPrimario + "\n";
        }
        if(aspettoSecondario != null){
            info += "aspettoSecondario:\t" + aspettoSecondario + "\n";
        }
        if(tipo != null){
            info += "tipo:\t" + tipo + "\n";
        }
        if(tratti != null){
            info += "tratti:\n";
            boolean primo = true;
            for(String t:tratti) {
                if (primo) info = info.concat(t);
                else info = info.concat(" * " + t);
                primo = false;
            }
        }
        if(descrizione != null){
            info += "\ndescrizione:\n" + descrizione + "\n";
        }
        if(arena != null){
            info += "arena:\t" + arena + "\n";
        }
        info += "costo:\t" + costo + "\n";
        info += "vita:\t" + vita + "\n";
        info += "potenza:\t" + potenza + "\n";
        if(rarita != null){
            info += "rarita:\t" + rarita + "\n";
        }
        if(artista != null) {
            info += "artista:\t" + artista + "\n";
        }
        info += "url immagine:";
        if(tipo.equals("Leader")) {
            info += "\n\tfronte:\t" + frontArt + "\n";
            info += "\tretro:\t" + backArt + "\n";
        }else{
            info += "\tfronte:\t" + frontArt + "\n";
        }
        info += "\n";
        return info;
    }

    public static long getLong(){
        try{
            return new java.util.Scanner(System.in).nextLong();
        }catch (java.util.InputMismatchException e){
            System.out.println("Errore: inserisci un long");
            return getLong();
        }
    }

    public static void main(String[] args) {
        //insert another `*` on the first one to switch from the static to the dinamic input
        String cid = /**/String.valueOf(Scan.getString("inserisci il cid (Carta ID) della carta che vuoi cercare"));/*/"4179470615";/**/
        try {
            ThreadMessage tm = new ThreadMessage();
            tm.start();
            Carta carta = new Carta(cid, tm);
            System.out.println(carta);
            tm.finish();
        }catch (Error e){
            e.printStackTrace();
        }
        System.out.println("fine");
    }
}