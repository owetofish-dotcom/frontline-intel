# Specyfikacja — CzaseoPraceo

> Faza Spec Kit: **Specify** (co i dlaczego — bez decyzji technologicznych).
> Decyzje „jak" trafią do `plan.md`.

Wersja: 0.1 (szkic) · Data: 2026-06-27

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
- **FR-5 Zarządzanie pracownikami.** Admin firmy dodaje/edytuje/dezaktywuje
  pracowników i przypisuje/odpina numery kart RFID (numer karty unikalny w
  obrębie firmy).
- **FR-6 Korekty.** Admin dodaje/edytuje wpisy ręcznie; oryginalne odbicia
  pozostają niezmienne, korekta zapisuje autora, czas i powód.
- **FR-7 Raporty.** Admin generuje raport godzin za okres i wybranych
  pracowników (suma godzin, lista odbić), z **eksportem CSV i PDF**.
- **FR-8 Stawka godzinowa (opcjonalna).** Admin może ustawić stawkę; raport
  wtedy liczy też kwotę.
- **FR-9 Hierarchia kont.** Super-admin zarządza firmami i adminami; admin
  firmy zarządza tylko swoimi danymi. Admin loguje się e‑mailem i hasłem.
- **FR-10 Multi-tenant.** Wszystkie dane są przypisane do firmy; brak dostępu
  poza własną firmą.
- **FR-11 Rejestracja kiosku.** Admin rejestruje/autoryzuje urządzenie-kiosk
  dla swojej firmy.

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
Karta RFID (numer ↔ pracownik) · Odbicie (czas, typ wejście/wyjście, źródło) ·
Korekta wpisu · Kiosk (urządzenie) · Stawka godzinowa.

## 7. Poza zakresem MVP
Przerwy (start/koniec), projekty i klienci, harmonogramy/grafiki, integracje
kadrowo-płacowe, geolokalizacja, aplikacja pracownika na prywatny telefon,
logowanie pracownika hasłem.

## 8. Kwestie otwarte `[DO USTALENIA]`
- **OQ-1** Format numeru karty RFID (długość, system — np. 10-cyfrowy
  dziesiętny vs HEX) — zależny od modelu czytnika.
- **OQ-2** Strefa czasowa i obsługa zmiany czasu — czas firmy czy urządzenia?
- **OQ-3** Zaokrąglanie godzin w raporcie (do minuty? do 5/15 min?).
- **OQ-4** Co przy podwójnym odbiciu w krótkim czasie (zabezpieczenie przed
  przypadkowym dublem)?
- **OQ-5** Rozliczanie pracy przechodzącej przez północ / zmiany nocne.
- **OQ-6** Czy admin firmy może mieć wielu pracowników na jednej karcie / kartę
  zapasową?

---

## Historia zmian
- 0.1 (2026-06-27) — pierwszy szkic na podstawie wywiadu Spec Kit.
