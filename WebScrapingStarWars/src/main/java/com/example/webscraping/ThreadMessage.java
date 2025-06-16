package com.example.webscraping;

import java.util.ArrayList;
import java.util.List;

public class ThreadMessage extends java.lang.Thread{
    String lastMessage;
    List<String> messageBuffer;
    List<Boolean> sendMessageBuffer;
    boolean messageBufferBusy;
    boolean running;
    int ignoredMessage;

    public ThreadMessage() {
        this.messageBuffer = new ArrayList<String>();
        this.sendMessageBuffer = new ArrayList<Boolean>();
        this.running = true;
        this.messageBufferBusy = false;
        this.ignoredMessage = 0;
    }

    @Override
    public void run() {
        while (running || !messageBuffer.isEmpty()){
            if(sendMessageBuffer.size() != messageBuffer.size()) {
                Scan.alert("mi aspetto che sendMessageBuffer (" + sendMessageBuffer.size() + ") and messageBuffer (" + messageBuffer.size() + ") abbiano la stessa size()");
                throw new RuntimeException("mi aspetto che sendMessageBuffer (" + sendMessageBuffer.size() + ") and messageBuffer (" + messageBuffer.size() + ") abbiano la stessa size()");
            }
            if(!messageBuffer.isEmpty()){
                if(sendMessageBuffer.removeFirst() || sendMessageBuffer.size()<2 || ignoredMessage == 10) {
                    ignoredMessage = 0;
                    if (lastMessage != null) Scan.editMessage(lastMessage, messageBuffer.removeFirst());
                    else lastMessage = Scan.alert(messageBuffer.removeFirst(), true);
                }else{
                    ignoredMessage++;
                    System.out.println("messaggio non rilevante ignorato\t("+messageBuffer.removeFirst()+")"+messageBuffer.size()+" mancanti");
                }
            }else{
                try{
                    Thread.sleep(1000);
                }catch (InterruptedException e){
                    Scan.alert("Errore: " + e.getMessage(), true);
                }
            }
        }
    }

    public void finish(){
        running = false;
    }

    public void addMessage(String message, boolean sendMessage){
        while(messageBufferBusy){
            try{
                Thread.sleep(10);
            }catch (InterruptedException ignore){}
        }
        messageBufferBusy = true;
        messageBuffer.add(message);
        sendMessageBuffer.add(sendMessage);
        messageBufferBusy = false;
    }

    public void addMessage(String message){
        addMessage(message, false);
    }

    public void stats(){
        boolean missingString = true;
        boolean missingBoolean = true;
        int i=0;
        while(missingString && missingBoolean){
            missingString = i<messageBuffer.size();
            missingBoolean = i<sendMessageBuffer.size();
            if(missingString) System.out.print(messageBuffer.get(i));
            if(missingBoolean) System.out.println("\t"+sendMessageBuffer.get(i));
            i++;
        }
    }
}
