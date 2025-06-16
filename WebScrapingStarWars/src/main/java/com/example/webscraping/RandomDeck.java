package com.example.webscraping;

public class RandomDeck {
    public static void main(String[] args) {
        System.out.println("inserisci il nome del primo giocatore");
        String g1 = new java.util.Scanner(System.in).nextLine();
        boolean fisico = getBoolean("vuoi giocare fisicamente? (true/false)");
        System.out.println("inserisci il nome del secondo giocatore");
        String g2 = new java.util.Scanner(System.in).nextLine();
        java.util.List<String> mazzi = new java.util.ArrayList<>();
        String line;
        if(true || getBoolean("vuoi importare tutti i mazzi memorizzati?")) {
            try {
                java.io.BufferedReader br = new java.io.BufferedReader(new java.io.FileReader("collezione"+(fisico?"Fisica":"")+".txt"));
                while ((line = br.readLine()) != null) {
                    mazzi.add(line);
                }
                br.close();
            } catch (java.io.FileNotFoundException e){
                System.out.println("nessun mazzo memorizzato, esecuzione pulita");
            } catch (java.io.IOException e) {
                e.printStackTrace();
            }
        }
        while(!(line = getString("inserisci il nome del nuovo mazzo")).isEmpty()){
            mazzi.add(line);
        }
        java.util.List<String>[] storico = new java.util.List[0];
        try {
            java.io.BufferedReader br = new java.io.BufferedReader(new java.io.FileReader("storico"+(fisico?"Fisico":"")+".txt"));
            line = br.readLine();
            try{
                storico = new java.util.List[Integer.parseInt(line)];
            }catch (NumberFormatException e){
                storico = new java.util.List[0];
            }
            for(int i = 0; i < storico.length; i++){
                storico[i] = new java.util.ArrayList<>();
            }
            int i = 0;
            while ((line = br.readLine()) != null) {
                if(line.startsWith(i+"")){
                    storico[i].add(line.replace(i+"", ""));
                }else{
                    i++;
                }
            }
            for(java.util.List<String> decks : storico){
                mazzi.addAll(decks);
            }
            br.close();
        } catch (java.io.FileNotFoundException e) {
            System.out.println("nessuno storico, esecuzione pulita");
        }catch (java.io.IOException e) {
            e.printStackTrace();
        }
        java.util.List<String> distinctMazzi = new java.util.ArrayList<>();
        for(String mazzo : mazzi){
            if(!distinctMazzi.contains(mazzo)){
                distinctMazzi.add(mazzo);
            }
        }
        try{
            java.io.FileWriter writer = new java.io.FileWriter("collezione"+(fisico?"Fisica":"")+".txt");
            for(String mazzo : distinctMazzi){
                writer.write(mazzo + "\n");
            }
            writer.close();
        }catch (java.io.IOException e){
            e.printStackTrace();
        }
        for(int j=0;j<storico.length;j++){
            System.out.println("storico " + j + ":");
            for(String deck : storico[j]){
                System.out.println("\t" + deck);
            }
        }
        System.out.println("distinct mazzi:");
        for(String mazzo : distinctMazzi){
            System.out.println("\t" + mazzo);
        }
        System.out.println("mazzi");
        for(String mazzo : mazzi){
            System.out.println("\t" + mazzo);
        }
        String m1 = mazzi.remove(new java.util.Random().nextInt(mazzi.size()));
        System.out.println("\n" + g1 + ":\t" + m1);
        int m2;
        do{
            m2 = new java.util.Random().nextInt(mazzi.size());
        }while (mazzi.get(m2).equals(m1));
        System.out.println(g2 + ":\t" + mazzi.remove(m2));
        System.out.println("ci sono " + storico.length + " storici");

        try{
            java.io.FileWriter writer = new java.io.FileWriter("storico"+(fisico?"Fisica":"")+".txt");
            int i = 0;
            int j = 0;
            if(storico.length >= 25){
                writer.write(storico.length + "\n");
                i=1;
            }else{
                writer.write((storico.length+1) + "\n");
            }
            for(;i<storico.length;i++){
                for(String deck : storico[i]){
                    writer.write(j + deck + "\n");
                }
                j++;
                writer.write("\n");
            }
            for(String deck : mazzi){
                writer.write(j + deck + "\n");
            }
            writer.close();
        }catch (java.io.IOException e){
            e.printStackTrace();
        }
    }

    public static boolean getBoolean(String message) {
        System.out.println(message);
        return getBoolean();
    }

    public static boolean getBoolean() {
        try{
            return new java.util.Scanner(System.in).nextBoolean();
        }catch (java.util.InputMismatchException e){
            System.out.println("inserisci un valore booleano");
            return getBoolean();
        }
    }

    public static String getString(String message) {
        System.out.println(message);
        return getString();
    }

    public static String getString() {
        try{
            return new java.util.Scanner(System.in).nextLine();
        }catch (java.util.InputMismatchException e){
            System.out.println("inserisci una stringa");
            return getString();
        }
    }
}