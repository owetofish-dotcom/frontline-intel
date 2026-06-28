# Wytyczne UX / UI / HCD — CzaseoPraceo

> Zbiór zaleceń projektowych dla tego typu systemu (kiosk RCP + panel
> wielodostępowy), oparty na uznanych źródłach i odniesiony do naszych wymagań.
> Stanowi podstawę kryteriów akceptacji UX w `tasks.md`.

Wersja: 0.1 · Data: 2026-06-27

**Źródła odniesienia:** 10 heurystyk użyteczności Nielsena (NN/g) · zasady
projektowania zorientowanego na człowieka (HCD, ISO 9241-210) · WCAG 2.2 AA ·
badania NN/g nad kioskami i interfejsami dotykowymi · wytyczne rozmiaru celów
dotykowych (WCAG 2.5.5, Apple HIG, Material) · prawa Fittsa i Hicka.

---

## 1. Zasady nadrzędne (HCD)
1. **Projektuj pod kontekst użycia, nie pod ekran.** Kiosk stoi w hali — kurz,
   rękawice, pośpiech, słabe światło, użytkownik w ruchu. Panel — biuro,
   laptop, praca skupiona. To dwa różne światy UX.
2. **Pracownik nie jest „użytkownikiem aplikacji".** Ma odbić kartę w 2 sekundy
   i iść. Każda sekunda i każdy element interfejsu ponad to minimum to koszt.
3. **Pętla informacji zwrotnej zawsze zamknięta.** Każda akcja → natychmiastowe,
   jednoznaczne potwierdzenie (wizualne + dźwiękowe).
4. **Projektuj stany błędu i brzegowe jako pierwsze**, nie jako dodatek
   (nieznana karta, brak sieci, zła lokalizacja, podwójne odbicie).
5. **Włączaj realnych użytkowników wcześnie** — test kiosku na docelowym
   tablecie i czytniku przed rozbudową (R-4 z planu).

---

## 2. 10 heurystyk Nielsena — zastosowane do CzaseoPraceo
| # | Heurystyka | Co znaczy w tym systemie |
|---|-----------|--------------------------|
| 1 | **Widoczność stanu systemu** | Kiosk pokazuje stan: bezczynny/odczyt/sukces; wskaźnik online/offline i liczbę odbić do synchronizacji. Panel: gdzie jestem (firma › zakład), kto zalogowany. |
| 2 | **Zgodność z realnym światem** | Język polski, „Wejście/Wyjście" zamiast „check-in", zegar 24h, daty `dd.mm.rrrr`. |
| 3 | **Kontrola i wolność użytkownika** | Panel: anuluj/wstecz, potwierdzenie przy usuwaniu, możliwość cofnięcia korekty. Kiosk: świadomie bez „cofnij" — zamiast tego okno 15 min ignorujące dubel. |
| 4 | **Spójność i standardy** | Jeden system kolorów stanów (sukces/uwaga/błąd), te same wzorce w panelu i kiosku, znane ikony. |
| 5 | **Zapobieganie błędom** | Dedup 15 min; walidacja unikalności numeru karty; potwierdzenia akcji nieodwracalnych; sensowne wartości domyślne. |
| 6 | **Rozpoznawanie zamiast przypominania** | Pracownik widzi swoje imię i bieżący stan — nic nie pamięta. Admin: podpowiedzi, listy wyboru zamiast wpisywania ID. |
| 7 | **Elastyczność i efektywność** | Panel: filtry, skróty, zapamiętany ostatni okres raportu. Kiosk: jeden duży przycisk, zero zbędnych kroków. |
| 8 | **Estetyka i minimalizm** | Kiosk: jeden ekran, jedna akcja, brak dekoracji konkurującej o uwagę. Panel: tylko dane potrzebne w danym kroku (progresywne ujawnianie). |
| 9 | **Pomoc w rozpoznaniu i naprawie błędu** | Komunikaty po ludzku i z rozwiązaniem: „Karta nieznana — zgłoś się do kierownika", a nie „Error 404". |
| 10| **Pomoc i dokumentacja** | Krótkie podpowiedzi kontekstowe w panelu; kiosk samowyjaśniający (bez instrukcji). |

---

## 3. Kiosk (tablet, pracownik) — wytyczne szczegółowe
**To najważniejszy ekran w całym systemie. Reguła: 1 karta → 1 potwierdzenie.**

### Układ i dotyk
- **Cele dotykowe min. 1 cm / ~60 px**, realnie dużo większe — główny przycisk
  „Potwierdź" zajmuje znaczną część ekranu (prawo Fittsa). Min. WCAG 2.5.5 = 44 px,
  ale w hali celujemy znacznie wyżej.
- **Duże odstępy** między elementami (anty-mistap, rękawice).
- **Minimum wyborów** (prawo Hicka) — idealnie jeden przycisk akcji na ekranie.
- **Brak przewijania, brak klawiatury, brak wpisywania.**
- Przyciski w zasięgu kciuka; akcja blisko dolnej/centralnej części ekranu.

### Czytelność
- **Duża typografia** czytelna z ~1–2 m (imię i nazwisko, godzina, status).
- **Wysoki kontrast** (WCAG AA: ≥ 4.5:1 tekst, ≥ 3:1 elementy/duży tekst).
- **Duży zegar** na ekranie bezczynności — pełni też funkcję „czy działa".

### Informacja zwrotna (krytyczne)
- Po odczycie karty: < 1 s (NFR-1) pokazać **kogo rozpoznano** + proponowaną
  akcję (Wejście/Wyjście wykryte z ostatniego stanu).
- Po potwierdzeniu: **wyraźny sukces** — kolor + ikona + tekst + (opcjonalnie)
  krótki dźwięk/wibracja. **Nie polegać wyłącznie na kolorze** (daltonizm) —
  zawsze kolor **i** ikona **i** tekst.
- **Auto-powrót do ekranu bezczynności** po ~3–5 s od potwierdzenia.
- Komunikat sukcesu zawiera czas: „Zarejestrowano **wejście 07:32**. Miłego dnia".

### Stany błędu / brzegowe (projektować od razu)
- **Nieznana / nieprzypisana karta** → „Karta nieznana. Zgłoś się do kierownika."
- **Karta spoza zakładu** (FR-16) → „Ta karta nie należy do tego zakładu."
- **Podwójne odbicie < 15 min** (FR-4a) → łagodny komunikat „Już odbito o 07:32",
  bez tworzenia wpisu.
- **Brak sieci** → kiosk działa normalnie; widoczny dyskretny wskaźnik
  „Offline — zapisano lokalnie, zsynchronizuję później". Nigdy nie blokować
  pracownika.

### Tryb pracy urządzenia
- **Ekran bezczynności (attract)** — zegar + logo zakładu + „Przyłóż kartę".
- Tryb **pełnoekranowy / kiosk** (PWA na ekran główny), wygaszanie ekranu
  rozważnie (zawsze szybki powrót po dotknięciu / odczycie).
- Odporność na przypadkowe gesty (brak nawigacji systemowej w zasięgu).

### Dostępność kiosku
- Kontrast AA, duże cele, brak zależności wyłącznie od koloru, prosty język,
  opcjonalny sygnał dźwiękowy potwierdzenia (hałas w hali → też wizualny).

---

## 4. Panel (admin / kierownik zakładu) — wytyczne
### Orientacja i kontekst
- **Zawsze widoczny kontekst zakresu:** „Firma X › Zakład Y" oraz rola/konto.
  Kierownik widzi wyłącznie swoje zakłady — UI nie pokazuje tego, czego nie
  wolno (zasada najmniejszego zaskoczenia + bezpieczeństwo).
- **Breadcrumbs / nagłówki** dla zagłębień (firma → zakład → pracownik).

### Tabele i dane
- Sensowne **domyślne sortowanie i filtry**; bieżący okres jako domyślny w
  raportach (heur. 7).
- **Stany puste** z podpowiedzią pierwszego kroku („Brak pracowników — dodaj
  pierwszego").
- Skanowalność: wyrównanie liczb do prawej, czytelne nagłówki, paginacja.

### Formularze i akcje
- **Walidacja inline** z komunikatem przy polu, nie ogólnym alertem.
- **Potwierdzenia akcji nieodwracalnych** (usunięcie pracownika, dezaktywacja
  karty); preferuj **dezaktywację zamiast usuwania** (ślad audytowy).
- **Korekta wpisu (FR-6/FR-7b):** pokaż **oryginał obok korekty**, wymagaj
  **powodu**, oznacz wpis jako ręczny z autorem i czasem. Pełna przejrzystość.

### Raporty
- Eksport **CSV i PDF** widoczny i przewidywalny; pokaż, co zawiera (okres,
  zakład, zaokrąglanie). Zawsze dostępny **czas dokładny** obok zaokrąglonego.

### Bezpieczeństwo UX
- Jasne logowanie, czytelne reguły hasła, komunikat o wygaśnięciu sesji,
  wylogowanie zawsze dostępne. Brak ujawniania w komunikatach, czy istnieje
  dany e‑mail (anty-enumeracja).

### Responsywność
- Panel działa na laptopie i tablecie kierownika; układ płynny.

---

## 5. Dostępność (WCAG 2.2 AA) i lokalizacja
- Kontrast AA, rozmiar i odstęp celów dotykowych, obsługa klawiatury w panelu,
  etykiety pól (`<label>`), komunikaty błędów powiązane z polami (ARIA),
  focus widoczny.
- **Brak przekazu wyłącznie kolorem** (stany zawsze: kolor + ikona + tekst).
- **Język polski** jako domyślny; zegar **24h**, daty `dd.mm.rrrr`, poprawne
  formy (np. „1 godzina / 2 godziny / 5 godzin").
- Strefa czasu = czas urządzenia w miejscu pracy (D-2) — spójnie prezentowana.

---

## 6. UX trybu offline / synchronizacji (specyfika RCP)
- **Optymistyczny zapis:** odbicie zapisane lokalnie natychmiast, niezależnie od
  sieci; pracownik nigdy nie czeka na serwer.
- **Widoczny, ale dyskretny status:** online/offline + licznik „do
  zsynchronizowania: N".
- **Po odzyskaniu sieci** — cicha synchronizacja; ewentualny błąd sync nie
  dotyka pracownika, sygnalizowany tylko adminowi/kierownikowi.
- **Zaufanie do danych:** komunikaty potwierdzające, że odbicie jest bezpieczne
  („zapisano lokalnie").

---

## 7. Prywatność „by design" (RODO w warstwie UX)
- **Lista obecnych na kiosku skrócona do 3+3 liter** (FR-4b) — celowa
  minimalizacja danych na publicznym ekranie.
- Brak pełnych danych osobowych na ekranie widocznym dla osób trzecich.
- Numer karty traktowany jak dana identyfikująca — nie eksponować zbędnie.

---

## 8. Antywzorce do unikania
- ❌ Małe przyciski / gęsty układ na kiosku.
- ❌ Komunikaty techniczne („Error", kody) zamiast ludzkich.
- ❌ Potwierdzenie wyłącznie kolorem (problem dla daltonistów).
- ❌ Blokowanie pracownika przy braku sieci.
- ❌ Wymuszanie logowania/hasła po stronie pracownika.
- ❌ Kierownik widzący dane spoza swojego zakładu.
- ❌ Usuwanie danych bez śladu audytowego (zamiast dezaktywacji).
- ❌ Raport bez dostępu do czasu dokładnego (utrata audytowalności).

---

## Historia zmian
- 0.1 (2026-06-27) — pierwszy zestaw wytycznych UX/UI/HCD na bazie heurystyk
  Nielsena, HCD, WCAG 2.2 AA i dobrych praktyk dla kiosków RCP.
