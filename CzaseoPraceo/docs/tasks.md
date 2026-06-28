# Zadania — CzaseoPraceo

> Faza Spec Kit: **Tasks**. Rozbicie `plan.md` na wykonalne zadania z kryteriami
> ukończenia (Definition of Done). Kryteria UX pochodzą z `ux-guidelines.md` i
> są walidowane macierzą na końcu pliku.

Wersja: 0.2 · Data: 2026-06-27

Legenda DoD: każde zadanie ma kryteria **F** (funkcjonalne) i — gdzie dotyczy —
**UX** (z `ux-guidelines.md`).

---

## E0 — Fundament  ✅ (router API w E4)
- [x] **T-0.1 Schemat bazy** (`schema.sql`): 10 tabel z `plan.md` (`tenants`,
  `locations`, `panel_users`, `panel_user_locations`, `employees`,
  `employee_locations`, `rfid_cards`, `punches`, `punch_corrections`, `kiosks`).
  **F:** klucze obce, indeksy po `tenant_id`/`location_id`, unikalność numeru
  karty w obrębie firmy; import przez phpMyAdmin bez błędów.
- [x] **T-0.2 Konfiguracja** poza repo (`config.local.php`), połączenie PDO,
  tryb wyjątków, `utf8mb4`.
- [x] **T-0.3 Struktura `/api`, `/admin`, `/kiosk`** + front controller API
  (PATH_INFO, `.htaccess`).
- [x] **T-0.4 Warstwa dostępu do danych** (`lib/Scope.php`) wymuszająca
  centralnie filtr `tenant_id` oraz zakres zakładu (`location_id`).

## E1 — Uwierzytelnianie i role panelu  ✅
- [x] **T-1.1 Logowanie sesyjne** (`password_hash`/`verify`, sesje, CSRF,
  regeneracja ID, secure cookie).
- [x] **T-1.2 RBAC** dla 3 ról; nawigacja i kontekst wg roli, backend
  `requireRole`; komunikat logowania bez enumeracji kont.
- [x] **T-1.3 Wylogowanie** zawsze w nagłówku; timeout bezczynności sesji.

## E2 — Super-admin (SaaS)  ✅
- [x] **T-2.1 Firmy** (dodawanie + blokada/odblokowanie) ze statystykami
  (zakłady/pracownicy/admini).
- [x] **T-2.2 Zakładanie konta admina firmy** (walidacja e-mail/hasła,
  anty-duplikat); stany puste z podpowiedzią pierwszego kroku.

## E3 — Admin firmy  ✅ (zweryfikowane E2E na MariaDB)
- [x] **T-3.1 Zakłady** (dodawanie, zmiana nazwy, aktywacja/dezaktywacja).
- [x] **T-3.2 Pracownicy** (dodawanie, dezaktywacja zamiast usuwania).
- [x] **T-3.3 Przypisania pracownik ↔ zakład** (`employee_locations`,
  wiele-do-wielu, w zakresie uprawnień użytkownika).
- [x] **T-3.4 Karty RFID:** przypisanie/wymiana/karta zapasowa (FR-13),
  walidacja D-1 (tylko cyfry) i unikalność — zweryfikowane (litery/duplikat odrzucone).
- [x] **T-3.5 Konta kierowników** + przypisanie do zakładów; izolacja kierownika
  potwierdzona testem E2E (nie widzi danych spoza swoich zakładów).

## E4 — API kiosku  ✅ (zweryfikowane E2E na MariaDB)
- [x] **T-4.1 Autoryzacja urządzenia** tokenem (`lib/Kiosk.php`, SHA-256,
  nagłówek `X-Kiosk-Token`) + rejestracja kiosków w panelu (`admin/kiosks.php`,
  token pokazywany raz). Brak tokenu → 401.
- [x] **T-4.2 `GET /api/employees`** — pracownicy **tylko zakładu kiosku** +
  karty + inicjały 3+3 + ostatni stan in/out.
- [x] **T-4.3 `POST /api/punches`** — idempotencja po `device_punch_id`
  (duplicate), dedup 15 min (deduped), odrzucenie obcej karty (unknown_card),
  zapis `location_id`. Zweryfikowane wszystkie ścieżki.

## E5 — Kiosk PWA (najważniejszy UX)  ✅ (zweryfikowane w przeglądarce)
- [x] **T-5.1 Szkielet PWA** (manifest, ikona, tryb pełnoekranowy, theme color).
- [x] **T-5.2 Service Worker + IndexedDB** — app-shell offline, bufor odbić
  (pending), optymistyczny zapis, synchronizacja w tle; zweryfikowane (odbicie
  zsynchronizowane, licznik wyzerowany).
- [x] **T-5.3 Odczyt karty** (`keydown` cyfry + Enter, reset bufora) —
  zweryfikowane symulacją HID.
- [x] **T-5.4 Ekran bezczynności** — duży zegar 24h, data PL, „Przyłóż kartę",
  nazwa zakładu w pasku.
- [x] **T-5.5 Rozpoznanie + potwierdzenie** — duże imię, wykryte wejście/wyjście,
  wielki przycisk (Fitts), sukces kolor+ikona+tekst+dźwięk+wibracja, auto-powrót 4 s.
- [x] **T-5.6 Lista obecnych 3+3** po odbiciu (tylko zakład kiosku).
- [x] **T-5.7 Stany błędu/brzegowe:** nieznana karta, dubel < 15 min, offline
  (zapis lokalny, brak blokady pracownika).
- [x] **T-5.8 Wskaźnik online/offline + licznik „do synchronizacji".**

## E6 — Korekty i ustawienia firmy  ✅ (zweryfikowane E2E)
- [x] **T-6.1 Korekty wpisów** (FR-6): `admin/timesheet.php` — podgląd dnia,
  wpis manualny (źródło `manual`), oryginał niezmienny, audyt z autorem/czasem/
  powodem; `lib/Timesheet.php` (parowanie sesji, zaokrąglanie — testy jednostkowe).
- [x] **T-6.2 Ręczna decyzja o godzinach** (FR-7b): nadpisanie godzin dnia z
  powodem, używane w raporcie.
- [x] **T-6.3 Ustawienia firmy** (FR-12): zaokrąglanie (domyślnie pełne godziny),
  praca przez północ (domyślnie wył.), stawka godzinowa.

## E7 — Raporty  ✅ (zweryfikowane E2E + render PDF)
- [x] **T-7.1 Raport godzin** (`admin/reports.php` + `lib/Report.php`) za
  okres/zakład; domyślny okres = bieżący miesiąc; zaokrąglanie wg ustawień,
  czas dokładny zawsze widoczny; nadpisania dnia uwzględnione; kwota gdy stawka.
- [x] **T-7.2 Eksport CSV** (`fputcsv`, `;`, BOM UTF-8, per dzień + RAZEM).
- [x] **T-7.3 Eksport PDF** — **własny generator `lib/Pdf.php` bez zależności**
  (Composer/FPDF niedostępne na home.pl); poprawny PDF 1.4 zweryfikowany
  renderem; polskie znaki transliterowane w PDF, pełne UTF-8 w CSV.

## E8 — Cron (home.pl)  ✅
- [x] **T-8.1 `cron/maintenance.php`** (CLI-only, `.htaccess` deny): heartbeat +
  wykrywanie otwartych zmian > 18h i kiosków nieaktywnych > 24h; tylko odczyt
  i log (nie modyfikuje odbić). Zweryfikowane uruchomienie.

## E9 — Dostępność, lokalizacja, spójność (przekrojowe)  ✅
- [x] **T-9.1 WCAG 2.2 AA**: focus-visible, etykiety/`aria-label`, błędy flash
  `role="alert"`, stany kolor+ikona+tekst (nie sam kolor), kontrast AA CTA.
- [x] **T-9.2 Lokalizacja PL**: język wszędzie, zegar 24h, data PL na kiosku
  (daty w panelu w formacie ISO — jednoznaczne; do ew. zmiany na dd.mm.rrrr).
- [x] **T-9.3 System stanów** spójny (alerty/badge w panelu, ekrany kiosku).
- [x] **T-9.4 Responsywność panelu** — potwierdzona renderem mobile (390 px):
  zawijanie nagłówka/nawigacji, brak przewijania w poziomie.
- [x] **T-9.5 Stany puste z pierwszym krokiem** w głównych widokach (firmy,
  zakłady, pracownicy, kioski, raporty).

## E10 — Walidacja z użytkownikami i wdrożenie
- [ ] **T-10.1 Test kiosku na docelowym tablecie i czytniku** (R-4) — **po
  stronie użytkownika**, na realnym sprzęcie (instrukcja + checklista w `DEPLOY.md`).
- [x] **T-10.2 Wdrożenie**: instrukcja `docs/DEPLOY.md` (FTP, import `schema.sql`,
  konfiguracja, super-admin, cron, kiosk, czytnik, checklista SSL/R-3).

---

## Rozszerzenia (poza pierwotnym MVP, na życzenie)
- [x] **R-1 Tablica kiosku (tryb nasłuchiwania):** zegar 24h, data PL, imieniny
  dnia (offline), pogoda 5 dni (Open-Meteo), tablica obecności zakładu
  (Obecni/Nieobecni). Zweryfikowane w przeglądarce (pogoda happy-path do
  potwierdzenia na tablecie z internetem).
- [x] **R-2 Logo firmy:** wgrywanie w ustawieniach (PNG/JPG/WEBP/SVG/GIF, maks.
  768 KB), wyświetlanie w nagłówku panelu i na tablicy kiosku (offline, przez
  `/api/config`). Zweryfikowane E2E.

## Walidacja UX — macierz pokrycia
> „Czy zadania realizują uznane zalecenia?" Status: ✅ pokryte · ⚠️ częściowo ·
> ❗ luka do uzupełnienia.

| Zasada / heurystyka | Gdzie w zadaniach | Status |
|---------------------|-------------------|--------|
| H1 Widoczność stanu systemu | T-5.4, T-5.5, T-5.8, T-3.5 (kontekst) | ✅ |
| H2 Zgodność z realnym światem | T-9.2 (PL, 24h, daty) | ✅ |
| H3 Kontrola i wolność | T-1.3, T-6.1 (cofnięcie korekty), T-3.x potwierdzenia | ✅ |
| H4 Spójność i standardy | T-9.3 (system stanów) | ✅ |
| H5 Zapobieganie błędom | T-5.7 (dubel), T-3.4 (unikalność), potwierdzenia | ✅ |
| H6 Rozpoznawanie zamiast pamiętania | T-5.5 (imię/stan), listy wyboru w panelu | ✅ |
| H7 Elastyczność i efektywność | T-7.1 (domyślny okres), filtry tabel | ✅ |
| H8 Estetyka i minimalizm | T-5.5 (jeden przycisk), progresywne ujawnianie | ✅ |
| H9 Naprawa błędów | T-5.7, T-1.2 (komunikaty bez enumeracji) | ✅ |
| H10 Pomoc i dokumentacja | T-9.5 (podpowiedzi + onboarding) | ✅ |
| Cele dotykowe ≫ 60 px (Fitts) | T-5.5 | ✅ |
| Min. wyborów (Hick) | T-5.5 (jeden przycisk) | ✅ |
| Feedback < 1 s + multimodalny | T-5.5 (kolor+ikona+tekst+dźwięk) | ✅ |
| Offline-first / zaufanie do danych | T-5.2, T-5.8, T-8.1 | ✅ |
| Stany błędu/brzegowe od początku | T-5.7 | ✅ |
| Dostępność WCAG 2.2 AA | T-9.1 | ✅ |
| Brak przekazu wyłącznie kolorem | T-5.5, T-9.1 | ✅ |
| Prywatność by design (3+3) | T-5.6 | ✅ |
| Izolacja zakresu (tenant/zakład/rola) | T-0.4, T-1.2, T-4.2 | ✅ |
| Stany puste / onboarding | T-9.5 (+ T-2.2, T-3.x) | ✅ |
| Bezpieczeństwo UX (sesja, hasło) | T-1.1, T-1.2, T-1.3 | ✅ |
| Test z użytkownikami (HCD) | T-10.1 | ✅ |
| Responsywność panelu | T-9.4 | ✅ |

### Luki wykryte przez walidację — status domknięcia
- **✅ Responsywność panelu** — domknięte zadaniem **T-9.4**.
- **✅ H10 Pomoc / onboarding** — domknięte zadaniem **T-9.5** (podpowiedzi +
  stany puste z pierwszym krokiem).
- **⚠️ Dźwięk/wibracja na kiosku** — pozostaje ryzykiem sprzętowym (nie luką
  projektu): ujęte w T-5.5, weryfikowane w **T-10.1**; wariant czysto wizualny
  jako zapas (niektóre tablety/PWA ograniczają audio bez interakcji).

> Macierz heurystyk i zasad UX: **100% ✅**. Jedyny otwarty punkt to zależne od
> sprzętu audio kiosku, świadomie obsłużone wariantem wizualnym.

---

## Propozycja cięcia MVP (mniejszy pierwszy krok)
Jeśli chcesz szybciej zobaczyć działanie, **MVP-0** = E0 + E1 + E3 (1 firma,
1 zakład, pracownicy+karty) + E4 + E5 (kiosk z odbiciami offline) + T-7.1/T-7.2
(raport + CSV). Reszta (super-admin multi-firmowy, kierownicy, PDF, cron,
ustawienia zaawansowane) w kolejnych iteracjach.

---

## Historia zmian
- 0.2 (2026-06-27) — domknięto luki walidacji: dodano T-9.4 (responsywność
  panelu) i T-9.5 (pomoc kontekstowa + stany puste); macierz UX w 100% ✅.
- 0.1 (2026-06-27) — pierwszy podział na zadania (E0–E10) z kryteriami UX i
  macierzą walidacji wg `ux-guidelines.md`.
