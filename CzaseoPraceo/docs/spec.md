# Specyfikacja — CzaseoPraceo

> Faza Spec Kit: **Specify** (co i dlaczego — bez decyzji technologicznych).
> Decyzje „jak" trafią do `plan.md`.

Wersja: 0.2 (szkic) · Data: 2026-06-27

---

## 1. Cel
CzaseoPraceo to wielodostępowy (SaaS) system **rejestracji czasu pracy (RCP)**.
Pracownik odbija kartę RFID o czytnik podpięty do tabletu-kiosku; system
rozpoznaje go po numerze karty i zapisuje wejście lub wyjście. Firma rozlicza
czas pracy na podstawie raportów.

### Problem, który rozwiązujemy
Ręczne/papierowe ewidencjonowanie obecności jest pracochłonne i podatne na
błędy. CzaseoPraceo daje bezobsługowe odbicie kartą i automatyczne raporty
godzin gotowe do rozliczeń.

## 2. Role (aktorzy)
| Rola | Gdzie | Co robi |
|------|-------|---------|
| **Super-admin SaaS** | panel web | zarządza firmami-najemcami, zakłada konta admina firmy, nadzoruje system |
| **Admin firmy** | panel web | zarządza pracownikami i kartami, koryguje wpisy, generuje raporty, ustawia stawki, konfiguruje kioski |
| **Pracownik** | kiosk (tablet) | odbija kartę, potwierdza wejście/wyjście |

## 3. Historyjki użytkownika

### Pracownik
- Jako pracownik przykładam kartę do czytnika, **widzę swoje imię i nazwisko**
  oraz aktualny stan (w pracy / poza pracą), i jednym kliknięciem potwierdzam
  **wejście** lub **wyjście**.
- Po odbiciu widzę na ekranie **listę osób obecnych** w pracy, pokazaną w formie
  skróconej (3 litery imienia + 3 litery nazwiska) — bez ujawniania pełnych
  danych.
- Jako pracownik chcę, by odbicie zadziałało **nawet gdy tablet nie ma
  internetu**, a system zapamiętał je i zsynchronizował później.
- Jako pracownik, gdy przyłożę nieznaną/nieprzypisaną kartę, **widzę czytelny
  komunikat**, że karta nie jest rozpoznana.

### Admin firmy
- Jako admin dodaję pracownika i **przypisuję mu numer karty RFID**.
- Jako admin **przeglądam i koryguję** wpisy czasu (np. ktoś zapomniał odbić),
  a korekta zostaje oznaczona jako ręczna z powodem.
- Jako admin **generuję raport** godzin za wybrany okres i pracowników oraz
  **eksportuję go do CSV i PDF**.
- Jako admin (opcjonalnie) ustawiam **stawkę godzinową**, by raport pokazał
  także koszt/wynagrodzenie.
- Jako admin **rejestruję tablet-kiosk** mojej firmy.

### Super-admin SaaS
- Jako super-admin **zakładam nową firmę (najemcę)** i konto jej admina.
- Jako super-admin **blokuję/odblokowuję** firmę i widzę stan systemu.

## 4. Wymagania funkcjonalne (FR)
- **FR-1 Odbicie RFID.** Kiosk przyjmuje numer karty z czytnika HID
  (wpisuje numer + Enter), wyszukuje pracownika w obrębie firmy i rejestruje
  zdarzenie z dokładnym czasem.
- **FR-2 Wejście/wyjście.** System sam ustala, czy odbicie to wejście, czy
  wyjście, na podstawie ostatniego stanu pracownika; pracownik potwierdza.
- **FR-3 Nieznana karta.** Odbicie nieprzypisanej karty nie tworzy wpisu i
  pokazuje komunikat o braku rozpoznania.
- **FR-4 Tryb offline.** Kiosk zapisuje odbicia lokalnie bez sieci i
  synchronizuje je po odzyskaniu łącza; zsynchronizowane odbicia nie dublują się.
- **FR-4a Ochrona przed dublem.** Drugie odbicie tej samej karty w ciągu
  **15 minut** jest ignorowane (czas progu nie jest na razie konfigurowalny).
- **FR-4b Lista obecnych na kiosku.** Po odbiciu kiosk pokazuje listę osób
  aktualnie w pracy w formie skróconej: **3 litery imienia + 3 litery nazwiska**.
- **FR-5 Zarządzanie pracownikami.** Admin firmy dodaje/edytuje/dezaktywuje
  pracowników i przypisuje/odpina numery kart RFID (numer karty unikalny w
  obrębie firmy).
- **FR-6 Korekty.** Admin dodaje/edytuje wpisy ręcznie; oryginalne odbicia
  pozostają niezmienne, korekta zapisuje autora, czas i powód.
- **FR-7 Raporty.** Admin generuje raport godzin za okres i wybranych
  pracowników (suma godzin, lista odbić), z **eksportem CSV i PDF**.
- **FR-7a Czas dokładny vs raportowy.** System zawsze przechowuje i pokazuje
  adminowi **dokładny czas odbić** (np. 16:51, 17:15). Na potrzeby raportu czas
  jest **zaokrąglany** wg ustawienia firmy (domyślnie: **do pełnych godzin**).
- **FR-7b Ręczna decyzja admina.** Gdy dzień jest niejednoznaczny (np. wyjście
  17:31 zamiast spodziewanych 17:00), admin w dashboardzie **ręcznie ustala
  liczbę godzin** zaliczonych w tym dniu; decyzja zapisuje się jak korekta (FR-6).
- **FR-8 Stawka godzinowa (opcjonalna).** Admin może ustawić stawkę; raport
  wtedy liczy też kwotę.
- **FR-9 Hierarchia kont.** Super-admin zarządza firmami i adminami; admin
  firmy zarządza tylko swoimi danymi. Admin loguje się e‑mailem i hasłem.
- **FR-10 Multi-tenant.** Wszystkie dane są przypisane do firmy; brak dostępu
  poza własną firmą.
- **FR-11 Rejestracja kiosku.** Admin rejestruje/autoryzuje urządzenie-kiosk
  dla swojej firmy.
- **FR-12 Ustawienia firmy.** Admin konfiguruje per-firma: zaokrąglanie godzin
  w raporcie (domyślnie pełne godziny), obsługę pracy przez północ (domyślnie
  **wyłączona** — doba rozliczeniowa nie przechodzi przez północ), opcjonalną
  stawkę godzinową.
- **FR-13 Wymiana/karty zapasowe.** Pracownik ma jedną aktywną kartę; admin może
  ją **wymienić** (dezaktywacja starej + przypisanie nowej, z zachowaniem
  historii) oraz opcjonalnie przypisać **kartę zapasową**. Numery kart są
  numeryczne i unikalne w obrębie firmy.

## 5. Wymagania niefunkcjonalne (NFR)
- **NFR-1** Odbicie (rozpoznanie karty → potwierdzenie na ekranie) < 1 s przy
  działaniu lokalnym.
- **NFR-2** Brak utraty odbić przy zaniku sieci/zasilania (trwały zapis lokalny).
- **NFR-3** Izolacja danych między najemcami (twarda granica zapytań).
- **NFR-4** Szyfrowanie transmisji (HTTPS/TLS); ochrona danych wg RODO.
- **NFR-5** Interfejs kiosku czytelny z odległości i obsługiwalny dotykiem
  jednym palcem; język polski.
- **NFR-6** Audytowalność: każdą godzinę w raporcie da się prześledzić do źródła.

## 6. Encje (wysoki poziom — szczegóły w fazie Plan)
Firma (najemca) · Użytkownik panelu (super-admin / admin) · Pracownik ·
Karta RFID (numer ↔ pracownik, status aktywna/zapasowa) · Odbicie (czas, typ
wejście/wyjście, źródło) · Korekta wpisu · Kiosk (urządzenie) ·
Ustawienia firmy (zaokrąglanie, praca przez północ, stawka godzinowa).

## 7. Poza zakresem MVP
Przerwy (start/koniec), projekty i klienci, harmonogramy/grafiki, integracje
kadrowo-płacowe, geolokalizacja, aplikacja pracownika na prywatny telefon,
logowanie pracownika hasłem.

## 8. Rozstrzygnięte decyzje
- **D-1 Format karty RFID:** numer **wyłącznie cyfrowy** (czytnik wpisuje same
  cyfry + Enter). Przechowywany jako ciąg cyfr, unikalny w obrębie firmy.
- **D-2 Strefa czasowa:** **czas urządzenia w miejscu pracy** (kiosku) jest
  źródłem czasu odbicia.
- **D-3 Zaokrąglanie:** ustawialne per firma; **domyślnie do pełnych godzin** w
  raporcie, przy stałym podglądzie czasu dokładnego (patrz FR-7a/FR-7b).
- **D-4 Podwójne odbicie:** drugie odbicie tej samej karty w ciągu **15 min**
  ignorowane (FR-4a).
- **D-5 Praca przez północ:** ustawialna per firma; **domyślnie wyłączona**
  (FR-12).
- **D-6 Karty:** jedna aktywna karta na pracownika, możliwa wymiana i opcjonalna
  karta zapasowa (FR-13).

---

## Historia zmian
- 0.2 (2026-06-27) — rozstrzygnięto kwestie otwarte (D-1…D-6); dodano listę
  obecnych na kiosku (FR-4b), ochronę przed dublem 15 min (FR-4a), czas
  dokładny vs raportowy i ręczną decyzję admina (FR-7a/b), ustawienia firmy
  (FR-12), wymianę/karty zapasowe (FR-13).
- 0.1 (2026-06-27) — pierwszy szkic na podstawie wywiadu Spec Kit.
