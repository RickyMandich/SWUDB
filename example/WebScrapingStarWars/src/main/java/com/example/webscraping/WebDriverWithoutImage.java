package com.example.webscraping;

import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;

import java.util.HashMap;
import java.util.Map;

public class WebDriverWithoutImage extends ChromeDriver {
    public WebDriverWithoutImage(){
        super(getOptions());
    }

    private static ChromeOptions getOptions(){
        ChromeOptions options = new ChromeOptions();
        // Crea una mappa per le preferenze
        Map<String, Object> prefs = new HashMap<>();
        // 2 = Disabilita le immagini
        // 1 = Carica le immagini solo dalla cache
        // 0 = Carica tutte le immagini
        prefs.put("profile.managed_default_content_settings.images", 2);
        options.setExperimentalOption("prefs", prefs);
        return options;
    }
}
