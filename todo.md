# TODO — UnlimitedDB

> Riferimento completo (comandi, codice, motivazioni): `implementationPlan-ricostruzioneUnlimitedDB.md`. Qui solo l'elenco sintetico per tenere traccia di cosa manca.

## Fase 3 — Autenticazione e permessi
- [x] 3.1 Verifica email nativa (Breeze, già pronto)
- [ ] 3.2 Pagina admin gestione utenti `/admin/utenti`

## Fase 3.5 — Configurazione email (Resend)
- [ ] 3.5.1 Verifica dominio `mandich.dev` su Resend (DNS su Cloudflare)
- [ ] 3.5.2 API Key Resend
- [ ] 3.5.3 `composer require resend/resend-php`
- [ ] 3.5.4 Variabili `.env` (locale + server)
- [ ] 3.5.5 Verifica invio di test

## Fase 4 — Catalogo carte e import
- [ ] 4.1 Bug: manca `use Schedule` in `routes/console.php`
- [ ] 4.2 Aggiungere FK `cards.expansion → expansions.expansion` (manca in migration)
- [x] 4.3 Aspetti e tratti (modelli + pivot già fatti)
- [x] 4.4 Relazioni nei modelli (già fatte)
- [ ] 4.5 Implementare `ImportCardsFromSwuApiJob` (verificare prima i nomi campo reali dell'API)
- [ ] 4.6 Download locale immagini carta (`CardImageDownloader`)
- [ ] 4.7 Test Pest per il job di import

## Fase 5 — Log errori scan
- [x] 5.1 Migration/model `SystemError` (già fatti)
- [ ] 5.2 Permesso `system.manage-errors`
- [ ] 5.3 Pagina admin `/admin/errori`

## Fase 6 — Admin espansioni
- [ ] 6.1 Permesso `expansions.manage`
- [ ] 6.2 Pagina admin `/admin/espansioni`

## Fase 7 — Gestione mazzi
- [ ] Correggere `Deck::cards()` (chiave pivot sbagliata, `card_id` invece di `cid`)
- [ ] Correggere `Deck::leader()`/`Deck::base()` (interrogano `Card` direttamente, sbagliato — via `deck_cards`/`cards.type`)
- [ ] 7.1 Enum `DeckFormat` + cast su `Deck`
- [ ] 7.3 Validator per formato (Premier/Eternal/TwinSuns)
- [ ] 7.4 Factory validator
- [ ] 7.5 `DeckPolicy`
- [ ] 7.6 Pagine mazzi (crea/edit/index/versioni)
- [ ] 7.7 Export/Import mazzi (verificare sintassi export ufficiale SWU)

## Fase 8 — Collezione
- [ ] 8.1 Modello/migration `collection_cards`
- [ ] 8.2 Pagina `/collezione`
- [ ] 8.3 "Carte mancanti per un mazzo" (`DeckGapCalculator`)

## Fase 9 — Bot Telegram
- [ ] 9.2 `TelegramService`
- [ ] 9.3 Webhook (+ esenzione CSRF su `bootstrap/app.php`, + registrazione webhook via curl)
- [ ] 9.6 `NotifyAdminJob`

## Fase 10 — UI/UX
- [ ] 10.1 `CardSearch` + pagina `/carte` (occhio al bug del campo nome non ripopolato)
- [ ] 10.2 Statistiche mazzo (curva costi, tipi, tratti, medie)
- [ ] 10.3 Viste pubbliche/autenticate
- [ ] 10.4 Pagina "Nuove uscite"

## Fase 11 — API pubblica
- [ ] 11.1 Sanctum
- [ ] 11.2 Endpoint pubblici `/api/cards`, `/api/decks` (attenzione: nome mazzo non univoco per utente, valutare vincolo unique)
- [ ] 11.4 API Resources (`CardResource`, `DeckResource`)

## Fase 12 — Deploy
- [x] Automatizzato: merge su branch `laravel` → pipeline fa il resto
- [ ] Verifica post-deploy: webhook Telegram, `failed_jobs` vuota, scan schedulato

## Backlog (non pianificato in dettaglio)
- Condivisione social dei mazzi
- Tag personalizzati per i mazzi (Aggro/Control/Budget/Meta)
- Modalità offline/PWA
- Wishlist carte desiderate
- Deck-building guidato per principianti
- Statistica probabilità ipergeometrica nelle statistiche mazzo
- Apertura digitale dei booster pack
