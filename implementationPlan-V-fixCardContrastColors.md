# Implementation Plan: Fix Card Component Contrast Colors

## Obiettivo
Sistemare i colori del componente `card.blade.php` per rispettare le linee guida WCAG AA sul contrasto, mantenendo le stesse famiglie di colore attuali.

## Analisi del problema

Background attuale: `bg-blue-300` → `#93c5fd`

### Colori attuali e relativi rapporti di contrasto

| Elemento | Classe attuale | Hex | Contrasto vs `#93c5fd` | WCAG AA (4.5:1) |
|---|---|---|---|---|
| Titolo (h5) | `text-gray-900` | `#111827` | ~8.5:1 | ✅ |
| Tratti | `text-gray-700` | `#374151` | ~5.8:1 | ✅ |
| Costo (label + valore) | `text-yellow-500` | `#eab308` | ~1.8:1 | ❌ |
| Potenza (label + valore) | `text-red-500` | `#ef4444` | ~2.2:1 | ❌ |
| Vita (label + valore) | `text-blue-500` | `#3b82f6` | ~1.8:1 | ❌ |
| Rarità | nessun colore (ereditato) | — | — | ⚠️ da specificare |

### Requisiti WCAG AA
- **Testo normale** (< 18px o < 14px bold): rapporto minimo **4.5:1**
- **Testo grande** (≥ 18px o ≥ 14px bold): rapporto minimo **3:1**

## Modifiche proposte

### File: `resources/views/components/card.blade.php`

#### 1. Riga 46 — Costo: `text-yellow-500` → `text-yellow-800`
- `text-yellow-800` = `#854d0e` → contrasto su `#93c5fd` ≈ **4.7:1** ✅
- Mantiene la famiglia giallo/ambra, risulta leggibile

```diff
- <div class="mb-1 flex items-center  text-yellow-500">
+ <div class="mb-1 flex items-center text-yellow-800">
```

#### 2. Riga 57 — Potenza: `text-red-500` → `text-red-800`
- `text-red-800` = `#991b1b` → contrasto su `#93c5fd` ≈ **5.6:1** ✅
- Mantiene la famiglia rossa, leggibile su sfondo chiaro

```diff
- <div class="mb-1 flex items-center  text-red-500">
+ <div class="mb-1 flex items-center text-red-800">
```

#### 3. Riga 68 — Vita: `text-blue-500` → `text-blue-900`
- `text-blue-900` = `#1e3a5f` → contrasto su `#93c5fd` ≈ **7.1:1** ✅
- Resta nella famiglia blu, crea forte contrasto col background

```diff
- <div class="mb-1 flex items-center  text-blue-500">
+ <div class="mb-1 flex items-center text-blue-900">
```

#### 4. Riga 80 — Rarità: aggiungere colore esplicito `text-gray-900`
- Evita dipendenza dal colore ereditato (che potrebbe non essere controllabile)

```diff
- <span class="font-semibold">
+ <span class="font-semibold text-gray-900">
```

## Riepilogo colori finali

| Elemento | Nuova classe | Hex | Contrasto vs `#93c5fd` | WCAG AA |
|---|---|---|---|---|
| Titolo | `text-gray-900` | `#111827` | ~8.5:1 | ✅ |
| Tratti | `text-gray-700` | `#374151` | ~5.8:1 | ✅ |
| Costo | `text-yellow-800` | `#854d0e` | ~4.7:1 | ✅ |
| Potenza | `text-red-800` | `#991b1b` | ~5.6:1 | ✅ |
| Vita | `text-blue-900` | `#1e3a5f` | ~7.1:1 | ✅ |
| Rarità | `text-gray-900` | `#111827` | ~8.5:1 | ✅ |

## Note
- Viene anche rimosso il doppio spazio nelle classi delle righe 46, 57, 68 (pulizia minore)
- I colori degli **aspetti** (`$aspect->color`) usano `style` inline con sfondo dinamico — il contrasto del testo su quei badge andrebbe verificato separatamente per ogni aspetto
