package com.example.webscraping;

import java.io.FileWriter;
import java.io.IOException;
import java.util.ArrayList;
import java.util.List;

public class Write {
    public static List<String> data = new ArrayList<String>();

    public static void writeData(String path) {
        System.out.println("inizio a scrivere i log nel file " + path);
        try(FileWriter writer = new FileWriter(path)){
            for (String s : data) {
                writer.write(s);
                writer.write(System.lineSeparator());
            }
        }catch (IOException ex){
            ex.printStackTrace();
        }finally {
            System.out.println("scrittura dei log completata");
        }
    }
}
