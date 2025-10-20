-- Comandi SQL per aggiungere e popolare il campo 'principale' nella tabella expansions
-- Eseguire questi comandi PRIMA di fare la migrazione per evitare perdita di dati

-- 1. Aggiungi la colonna 'principale' se non esiste già
ALTER TABLE expansions 
ADD COLUMN IF NOT EXISTS principale VARCHAR(10) DEFAULT '0' 
COMMENT 'ID espansione principale del gruppo, 0 se è principale';

-- 2. Aggiungi l'indice per performance
ALTER TABLE expansions 
ADD INDEX IF NOT EXISTS idx_principale (principale);

-- 3. Imposta tutti i valori esistenti a '0' (tutte le espansioni sono considerate principali per ora)
UPDATE expansions SET principale = '0' WHERE principale IS NULL OR principale = '';

-- 4. Imposta tutte le espansioni come "non confermate" per permettere la revisione
UPDATE expansions SET confermato = FALSE;

-- 5. Esempi di aggiornamento per gruppi di espansioni (da personalizzare in base ai dati reali)
-- Sostituire questi esempi con i dati reali del database:

-- Esempio: Se JTL è l'espansione principale e JTLW, TJTL sono varianti
-- UPDATE expansions SET principale = 'JTL' WHERE espansione IN ('JTLW', 'TJTL');
-- UPDATE expansions SET principale = '0' WHERE espansione = 'JTL';

-- Esempio: Se SOR è l'espansione principale e SORW è una variante  
-- UPDATE expansions SET principale = 'SOR' WHERE espansione = 'SORW';
-- UPDATE expansions SET principale = '0' WHERE espansione = 'SOR';

-- 6. Query per verificare la struttura dopo l'aggiornamento
-- SELECT espansione, uscita, principale, confermato FROM expansions ORDER BY uscita;

-- 7. Query per verificare i gruppi di espansioni
-- SELECT 
--     CASE WHEN principale = '0' THEN espansione ELSE principale END as gruppo_principale,
--     espansione,
--     uscita,
--     confermato
-- FROM expansions 
-- ORDER BY gruppo_principale, espansione;

-- NOTA IMPORTANTE:
-- Prima di eseguire gli UPDATE degli esempi (punti 4), verificare quali sono 
-- le espansioni realmente presenti nel database con:
-- SELECT espansione, uscita, confermato FROM expansions ORDER BY uscita;
-- 
-- Poi identificare manualmente i gruppi di espansioni e aggiornare di conseguenza.
-- Ad esempio, se si hanno espansioni come:
-- - SOR (Spark of Rebellion) - principale
-- - SORW (Spark of Rebellion Walmart) - variante di SOR
-- - TWI (Twilight of the Republic) - principale  
-- - TWIW (Twilight of the Republic Walmart) - variante di TWI
--
-- I comandi sarebbero:
-- UPDATE expansions SET principale = 'SOR' WHERE espansione = 'SORW';
-- UPDATE expansions SET principale = 'TWI' WHERE espansione = 'TWIW';
-- (le principali rimangono con principale = '0')
