package com.example.webscraping;

import com.google.gson.*;
import org.apache.commons.net.ftp.FTPClient;
import org.apache.http.HttpEntity;
import org.apache.http.client.methods.CloseableHttpResponse;
import org.apache.http.client.methods.HttpGet;
import org.apache.http.impl.client.CloseableHttpClient;
import org.apache.http.impl.client.HttpClients;
import org.apache.http.util.EntityUtils;

import java.net.*;
import java.net.http.*;

import java.awt.*;
import java.io.*;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.*;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class Scan {
    public static long tempo;

    public static boolean hasCid(String url) {
        String cid = "";
        Pattern pattern = Pattern.compile("cid=([0-9]+)");
        Matcher matcher = pattern.matcher(url);
        if (matcher.find()) {
            cid = matcher.group(1);
        }
        try{
            return !cid.isEmpty();
        } catch (NumberFormatException e) {
            System.out.println("url:\t" + url);
            System.out.println("cid:\t" + cid);
            throw e;
        }
    }

    public static String extractCid(String url) {
        String cid = "";
        Pattern pattern = Pattern.compile("cid=([0-9]+)");
        Matcher matcher = pattern.matcher(url);
        if (matcher.find()) {
            cid = matcher.group(1);
        }
        try{
            return cid;
        } catch (NumberFormatException e) {
            System.out.println("url:\t" + url);
            System.out.println("cid:\t" + cid);
            throw e;
        }
    }

    public static String[] add(String[] oldArray, String newElement) {
        String[] newArray = new String[oldArray.length + 1];
        System.arraycopy(oldArray, 0, newArray, 0, oldArray.length);
        newArray[oldArray.length] = newElement;
        return newArray;
    }

    public static Carta[] add(Carta[] array, Carta line){
        Carta[] newArray = new Carta[array.length + 1];
        System.arraycopy(array, 0, newArray, 0, array.length);
        newArray[array.length] = line;
        return newArray;
    }

    private static Carta[] getJsonCollezione(){
        downloadWithFtp("collezione.json");
        try{
            BufferedReader reader = new BufferedReader(new FileReader("collezione.json"));
            String line;
            if((line=reader.readLine()) != null){
                return new Gson().fromJson(line, Carta[].class);
            }else{
                throw new IOException("il file 'collezione.json' è vuoto");
            }
        }catch (IOException | JsonSyntaxException e){
            return new Carta[0];
        }
    }

    public static boolean contains(List<Carta> collezione, String cid){
        for(Carta c:collezione){
            if(c.cid.equals(cid)){
                return true;
            }
        }
        return false;
    }

    public static void main(String[] args) {
        ThreadMessage tm = new ThreadMessage();
        tm.start();
        tm.addMessage("inizio la scansione");
        long tempo;
        tempo = System.nanoTime() / 1000000000;
        scan(tm, true);
        long tempoTrascorso;
        tempoTrascorso = System.nanoTime() / 1000000000;
        tempoTrascorso = tempoTrascorso - tempo;
        System.out.println("tempo trascorso:\t" + formattaSecondi(tempoTrascorso));
        Toolkit.getDefaultToolkit().beep();
        Write.writeData("log.txt");
        tm.finish();
    }

    public static void scan(ThreadMessage tm, boolean first){
        String[] cid = new String[0];
            int page = 1;
            boolean pageFinished = false;
            while (!pageFinished) {
                String url = "https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[variantOf][id][$null]=true&pagination[page]=" + page + "&pagination[pageSize]=10";
                JsonObject jsonObject = new Gson().fromJson(apiCall(url), JsonObject.class);
                //recupero tutti i cid
                JsonArray cards = jsonObject.getAsJsonArray("data");
                for (JsonElement card : cards) {
                    JsonObject attributes = card.getAsJsonObject().getAsJsonObject("attributes");
                    //System.out.println(attributes);
                    JsonElement cardId = attributes.get("cardUid");
                    System.out.println(cardId.getAsString());
                    cid = add(cid, cardId.getAsString());
                }

                // controllo se ho finito le pagine
                JsonObject pagination = jsonObject.getAsJsonObject("meta").getAsJsonObject("pagination");
                try{
                    pageFinished = pagination.get("page").getAsString().equals(pagination.get("pageCount").getAsString());
                }catch (java.lang.ClassCastException e){
                    pageFinished = true;
                    tm.addMessage("errore, ultimo url per il recupero carte:\t" + url);
                }
                page++;
            }
            tm.addMessage("ho finito di recuperare tutti i cid");
            List<String> carte = new ArrayList<>();
            List<Carta> collezione = new ArrayList<>(List.of(getJsonCollezione()));
            int i=0;
            for(String c : cid){
                if(!contains(collezione, c)) {
                    carte.add(String.valueOf(c));
                    tm.addMessage(i++ + ")\t" + (i<100?"\t":"") + c);
                }
            }
            if (first)tm.addMessage("ho trovato " + carte.size() + " nuove carte da aggiungere");
            boolean mancanti;
            if(carte.isEmpty()){
                System.out.println("non ci sono nuove carte");
                mancanti = false;
            }else{
                mancanti = true;
            }
            if(mancanti) {
                Elenchi elenco = new Elenchi(carte, collezione, tm);
                ThreadParse[] thread = new ThreadParse[20];
                for(int h=0;h<thread.length;h++){
                    thread[h] = new ThreadParse(elenco);
                    thread[h].start();
                }
                boolean finito = false;
                while (!finito) {
                    finito = true;
                    try {
                        java.lang.Thread.sleep(1000);
                    } catch (InterruptedException ex) {
                        ex.printStackTrace();
                    }
                    for (ThreadParse t : thread) {
                        if (t.isAlive()) {
                            finito = false;
                            break;
                        }
                    }
                }
                collezione = elenco.getResult();
            }
            String json = json(collezione);
            try(FileWriter writer = new FileWriter("collezione.json")){
                writer.write(json);
            }catch (IOException ex){
                System.out.println("non ho trovato \"collezione.json\", inserisci tu il nome del file");
                scrivi(json);
            }
            uploadWithFtp("collezione.json");
            if(mancanti) scan(tm, false);
            else tm.addMessage("ho finito la scansione", true);
    }

    public static String alert(String message, boolean telegram){
        if(message == null){
            message = "null";
        }
        final String BOT_TOKEN = "7717265706:AAH5chf4Ae3vsFSt7158K-RFWdh9BudnnQc";
        final String CHAT_ID = "5533337157";
        String messageId = null;
        try {
            String urlEncodedMessage = URLEncoder.encode(message, StandardCharsets.UTF_8);
            String url = String.format(
                    "https://api.telegram.org/bot%s/sendMessage?chat_id=%s&text=%s",
                    BOT_TOKEN, CHAT_ID, urlEncodedMessage
            );

            try (CloseableHttpClient client = HttpClients.createDefault()) {
                HttpGet request = new HttpGet(url);
                if(telegram) {
                    try (CloseableHttpResponse response = client.execute(request)) {
                        // Leggere la risposta
                        HttpEntity entity = response.getEntity();
                        if (entity != null) {
                            String result = EntityUtils.toString(entity);

                            JsonObject jsonObject = JsonParser.parseString(result).getAsJsonObject();
                            if (jsonObject.has("ok") && jsonObject.get("ok").getAsBoolean() &&
                                jsonObject.has("result") && jsonObject.getAsJsonObject("result").has("message_id")) {
                                messageId = jsonObject.getAsJsonObject("result").get("message_id").getAsString();
                            }
                        }
                    }
                }
                System.out.println("Telegram:\t" + message);
            }
        } catch (IOException e) {
            System.err.println("Error sending Telegram notification: " + e.getMessage());
            e.printStackTrace();
        }
        return messageId;
    }

    public static String apiCall(String url) {
        HttpResponse<String> response;
        try {
            try(HttpClient client = HttpClient.newHttpClient()) {

                HttpRequest request = HttpRequest.newBuilder()
                        .uri(URI.create(url))
                        .header("Accept", "application/json")
                        .GET()
                        .build();

                response = client.send(request, HttpResponse.BodyHandlers.ofString());


                if (response.statusCode() != 200) {
                    throw new RuntimeException("Errore API: " + response.statusCode());
                }
            }
            return response.body();

        } catch (Exception e) {
            System.out.println("current link: " + url);
            throw new RuntimeException("Errore nella chiamata API", e);
        }
    }

    public static String alert(String message){
        return alert(message, false);
    }

    public static void deleteMessage(String messageId){
        final String BOT_TOKEN = "7717265706:AAH5chf4Ae3vsFSt7158K-RFWdh9BudnnQc";
        final String CHAT_ID = "5533337157";

        try {
            // Costruire l'URL per la richiesta deleteMessage
            String url = String.format(
                    "https://api.telegram.org/bot%s/deleteMessage?chat_id=%s&message_id=%s",
                    BOT_TOKEN, CHAT_ID, messageId
            );

            try (CloseableHttpClient client = HttpClients.createDefault()) {
                HttpGet request = new HttpGet(url);

                // Eseguire la richiesta
                try (CloseableHttpResponse response = client.execute(request)) {
                    HttpEntity entity = response.getEntity();
                    if (entity != null) {
                        String result = EntityUtils.toString(entity);

                        JsonObject jsonObject = JsonParser.parseString(result).getAsJsonObject();
                        if (jsonObject.has("ok") && jsonObject.get("ok").getAsBoolean()) {
                            System.out.println("Message deleted successfully: " + messageId);
                        } else {
                            System.err.println("Failed to delete message: " + result);
                        }
                    }
                }
            }
        } catch (Exception e) {
            System.err.println("Error deleting Telegram message: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public static String editMessage(String messageId, String newMessage) {
        if (newMessage == null) {
            newMessage = "null";
        }

        final String BOT_TOKEN = "7717265706:AAH5chf4Ae3vsFSt7158K-RFWdh9BudnnQc";
        final String CHAT_ID = "5533337157";

        try {
            // Codificare il nuovo messaggio
            String urlEncodedMessage = URLEncoder.encode(newMessage, StandardCharsets.UTF_8);

            // Costruire l'URL per la richiesta editMessageText
            String url = String.format(
                    "https://api.telegram.org/bot%s/editMessageText?chat_id=%s&message_id=%s&text=%s",
                    BOT_TOKEN, CHAT_ID, messageId, urlEncodedMessage
            );

            try (CloseableHttpClient client = HttpClients.createDefault()) {
                HttpGet request = new HttpGet(url);

                // Eseguire la richiesta
                try (CloseableHttpResponse response = client.execute(request)) {
                    HttpEntity entity = response.getEntity();
                    if (entity != null) {
                        String result = EntityUtils.toString(entity);

                        // Verificare la risposta
                        JsonObject jsonObject = JsonParser.parseString(result).getAsJsonObject();
                        if (jsonObject.has("ok") && jsonObject.get("ok").getAsBoolean()) {
                            System.out.println("Message edited successfully: " + messageId);
                        } else {
                            System.err.println("Failed to edit message: " + result);
                        }
                    }
                }
            }
        } catch (Exception e) {
            System.err.println("Error editing Telegram message: " + e.getMessage());
            e.printStackTrace();
        }
        return messageId;
    }

    public static String formattaSecondi(long secondi){
        long minuti = 0;
        long ore = 0;
        while(secondi>=60){
            secondi -= 60;
            minuti++;
        }
        while(minuti>=60){
            minuti -= 60;
            ore++;
        }
        return String.format("%02d", ore) + ":" + String.format("%02d", minuti) + ":" + String.format("%02d", secondi);
    }

    public static void downloadWithFtp(String filePath) {
        String server = "ftp.swudb.altervista.org";
        int port = 21;
        String user = "swudb";
        String pass = "Minecraft35?";

        FTPClient ftpClient = new FTPClient();
        try {
            ftpClient.connect(server, port);
            ftpClient.login(user, pass);
            ftpClient.enterLocalPassiveMode();

            try (FileOutputStream outputStream = new FileOutputStream(filePath)) {
                boolean done = ftpClient.retrieveFile(filePath, outputStream);
                if (done) {
                    System.out.println("Il file è stato scaricato con successo.");
                } else {
                    System.out.println("Impossibile scaricare il file.");
                }
            }
        } catch (IOException ex) {
            ex.printStackTrace();
        } finally {
            try {
                if (ftpClient.isConnected()) {
                    ftpClient.logout();
                    ftpClient.disconnect();
                }
            } catch (IOException ex) {
                ex.printStackTrace();
            }
        }
    }

    public static void uploadWithFtp(String filePath) {
        String server = "ftp.swudb.altervista.org";
        int port = 21;
        String user = "swudb";
        String pass = "Minecraft35?";

        FTPClient ftpClient = new FTPClient();
        try {
            ftpClient.connect(server, port);
            ftpClient.login(user, pass);
            ftpClient.enterLocalPassiveMode();
            try (FileInputStream inputStream = new FileInputStream(filePath)) {
                boolean done = ftpClient.storeFile(filePath, inputStream);
                if (done) {
                    System.out.println("The file is uploaded successfully.");
                } else {
                    System.out.println("Could not upload the file.");
                }
            }
        } catch (IOException ex) {
        } finally {
            try {
                if (ftpClient.isConnected()) {
                    ftpClient.logout();
                    ftpClient.disconnect();
                }
            } catch (IOException ex) {
                ex.printStackTrace();
            }
        }
    }

    public static String json(List<Carta> collezione) {
        return new Gson().toJson(collezione);
    }

    public static String getString(){
        try {
            return new Scanner(System.in).nextLine();
        } catch (NoSuchElementException e) {
            return getString();
        }
    }

    public static String getString(String message){
        System.out.println(message);
        Toolkit.getDefaultToolkit().beep();
        return getString();
    }

    public static void scrivi(String json){
        try(FileWriter writer = new FileWriter(getString("inserisci il percorso del file in cui salvare il json"))){
            writer.write(json);
        }catch (IOException e){
            System.out.println("errore nella scrittura del file");
            scrivi(json);
        }
    }
}
