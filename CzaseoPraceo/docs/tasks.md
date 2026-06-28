# Zadania — CzaseoPraceo

> Faza Spec Kit: **Tasks**. Rozbicie `plan.md` na wykonalne zadania z kryteriami
> ukończenia (Definition of Done). Kryteria UX pochodzą z `ux-guidelines.md` i
> są walidowane macierzą na końcu pliku.

Wersja: 0.1 · Data: 2026-06-27

Legenda DoD: każde zadanie ma kryteria **F** (funkcjonalne) i — gdzie dotyczy —
**UX** (z `ux-guidelines.md`).

---

## E0 — Fundament
- [ ] **T-0.1 Schemat bazy** (`schema.sql`): 10 tabel z `plan.md` (`tenants`,
  `locations`, `panel_users`, `panel_user_locations`, `employees`,
  `employee_locations`, `rfid_cards`, `punches`, `punch_corrections`, `kiosks`).
  **F:** klucze obce, indeksy po `tenant_id`/`location_id`, unikalność numeru
  karty w obrębie firmy; import przez phpMyAdmin bez błędów.
- [ ] **T-0.2 Konfiguracja** poza repo (`config.local.php`), połączenie PDO,
  tryb wyjątków, `utf8mb4`.
- [ ] **T-0.3 Router PHP** + struktura `/api`, `/admin`, `/kiosk`.
- [ ] **T-0.4 Warstwa dostępu do danych** wymuszająca **centralnie** filtr
  `tenant_id` oraz zakres zakładu (`location_id`) dla kiosku i kierownika.
  **F:** brak możliwości pobrania danych spoza zakresu (test negatywny).

## E1 — Uwierzytelnianie i role panelu
- [ ] **T-1.1 Logowanie sesyjne** (`password_hash`/`verify`, sesje, CSRF).
- [ ] **T-1.2 RBAC** dla 3 ról (super_admin / admin / location_manager); UI
  ukrywa niedostępne akcje, backend egzekwuje zakres.
  **UX:** komunikaty logowania bez enumeracji kont; widoczne reguły hasła;
  obsługa wygaśnięcia sesji (ux §4 „Bezpieczeństwo UX").
- [ ] **T-1.3 Wylogowanie** zawsze dostępne; timeout sesji.

## E2 — Super-admin (SaaS)
- [ ] **T-2.1 CRUD firm** (najemców) + status (blokada/odblokowanie).
- [ ] **T-2.2 Zakładanie konta admina firmy.**
  **UX:** stany puste z podpowiedzią pierwszego kroku (ux §4).

## E3 — Admin firmy
- [ ] **T-3.1 Zakłady** (CRUD `locations`).
- [ ] **T-3.2 Pracownicy** (CRUD, dezaktywacja zamiast usuwania).
- [ ] **T-3.3 Przypisania pracownik ↔ zakład** (`employee_locations`).
- [ ] **T-3.4 Karty RFID:** przypisanie/wymiana/karta zapasowa (FR-13),
  unikalność numeru. **UX:** walidacja inline; potwierdzenie dezaktywacji.
- [ ] **T-3.5 Konta kierowników** + przypisanie do zakładów
  (`panel_user_locations`).
  **UX:** zawsze widoczny kontekst „Firma › Zakład" i rola (ux §4).

## E4 — API kiosku
- [ ] **T-4.1 Autoryzacja urządzenia** tokenem niosącym `tenant_id`+`location_id`.
- [ ] **T-4.2 `GET /api/employees`** — lista pracowników **tylko danego
  zakładu** (do lokalnej kopii kiosku).
- [ ] **T-4.3 `POST /api/punches/sync`** — przyjęcie paczki odbić,
  **idempotencja** po `device_punch_id`, zapis `location_id`.
  **F:** ponowna wysyłka tej samej paczki nie tworzy duplikatów.

## E5 — Kiosk PWA (najważniejszy UX)
- [ ] **T-5.1 Szkielet PWA** (manifest, instalacja na ekran, tryb
  pełnoekranowy/kiosk, HTTPS).
- [ ] **T-5.2 Service Worker + IndexedDB** — bufor odbić offline-first,
  optymistyczny zapis, synchronizacja w tle.
  **UX:** pracownik nigdy nie czeka na serwer (ux §6).
- [ ] **T-5.3 Odczyt karty** (nasłuch `keydown`, cyfry + Enter), rozpoznanie
  pracownika z lokalnej kopii.
- [ ] **T-5.4 Ekran bezczynności (attract)** — duży zegar 24h, logo zakładu,
  „Przyłóż kartę". **UX:** ux §3 „Tryb pracy urządzenia".
- [ ] **T-5.5 Ekran rozpoznania + potwierdzenia** — duże imię/nazwisko,
  wykryte Wejście/Wyjście, **jeden duży przycisk**, potwierdzenie < 1 s.
  **UX:** cele dotykowe ≫ 60 px; kontrast AA; sukces = kolor **+** ikona **+**
  tekst **+** dźwięk; auto-powrót po 3–5 s (ux §3).
- [ ] **T-5.6 Lista obecnych 3+3** po odbiciu, tylko dla zakładu (FR-4b).
  **UX:** minimalizacja danych (ux §7).
- [ ] **T-5.7 Stany błędu/brzegowe:** nieznana karta, karta spoza zakładu
  (FR-16), dubel < 15 min (FR-4a), offline. **UX:** komunikaty po ludzku z
  rozwiązaniem; nigdy nie blokować pracownika (ux §3, §8).
- [ ] **T-5.8 Wskaźnik online/offline + licznik „do synchronizacji"** (ux §1, §6).

## E6 — Korekty i ustawienia firmy
- [ ] **T-6.1 Korekty wpisów** (FR-6): oryginał niezmienny, korekta z autorem,
  czasem, **powodem**. **UX:** oryginał obok korekty (ux §4).
- [ ] **T-6.2 Ręczna decyzja o godzinach w dniu niejednoznacznym** (FR-7b).
- [ ] **T-6.3 Ustawienia firmy** (FR-12): zaokrąglanie (domyślnie pełne
  godziny), praca przez północ (domyślnie wył.), stawka godzinowa.

## E7 — Raporty
- [ ] **T-7.1 Raport godzin** za okres/pracowników/zakład; domyślny okres =
  bieżący (heur. 7). Zaokrąglanie wg ustawień, **czas dokładny zawsze dostępny**.
- [ ] **T-7.2 Eksport CSV** (`fputcsv`).
- [ ] **T-7.3 Eksport PDF** (FPDF/mPDF jako `vendor/`).
  **UX:** widoczne, co zawiera raport (okres, zakład, zaokrąglanie) (ux §4).

## E8 — Cron (home.pl)
- [ ] **T-8.1 Zadanie porządkowe/retry sync**; błędy synchronizacji widoczne
  tylko dla admina/kierownika, nie dla pracownika (ux §6).

## E9 — Dostępność, lokalizacja, spójność (przekrojowe)
- [ ] **T-9.1 Audyt WCAG 2.2 AA**: kontrast, cele dotykowe, focus, etykiety,
  ARIA dla błędów; brak przekazu wyłącznie kolorem (ux §5, §8).
- [ ] **T-9.2 Lokalizacja PL**: zegar 24h, daty `dd.mm.rrrr`, poprawne formy
  liczby godzin (ux §5).
- [ ] **T-9.3 System stanów** (sukces/uwaga/błąd) spójny w kiosku i panelu
  (heur. 4).

## E10 — Walidacja z użytkownikami i wdrożenie
- [ ] **T-10.1 Test kiosku na docelowym tablecie i czytniku** (R-4) —
  z realnymi użytkownikami, w warunkach hali (ux §1 HCD). Wcześnie, nie na końcu.
- [ ] **T-10.2 Wdrożenie**: FTP, import `schema.sql`, konfiguracja, weryfikacja
  SSL (R-3).

---

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
| H10 Pomoc i dokumentacja | podpowiedzi kontekstowe w panelu | ⚠️ |
| Cele dotykowe ≫ 60 px (Fitts) | T-5.5 | ✅ |
| Min. wyborów (Hick) | T-5.5 (jeden przycisk) | ✅ |
| Feedback < 1 s + multimodalny | T-5.5 (kolor+ikona+tekst+dźwięk) | ✅ |
| Offline-first / zaufanie do danych | T-5.2, T-5.8, T-8.1 | ✅ |
| Stany błędu/brzegowe od początku | T-5.7 | ✅ |
| Dostępność WCAG 2.2 AA | T-9.1 | ✅ |
| Brak przekazu wyłącznie kolorem | T-5.5, T-9.1 | ✅ |
| Prywatność by design (3+3) | T-5.6 | ✅ |
| Izolacja zakresu (tenant/zakład/rola) | T-0.4, T-1.2, T-4.2 | ✅ |
| Stany puste / onboarding | T-2.2, T-3.x | ⚠️ |
| Bezpieczeństwo UX (sesja, hasło) | T-1.1, T-1.2, T-1.3 | ✅ |
| Test z użytkownikami (HCD) | T-10.1 | ✅ |
| Responsywność panelu | — | ❗ |

### Luki wykryte przez walidację (do domknięcia)
- **❗ Responsywność panelu** — brak osobnego zadania. → **dodać T-9.4**:
  panel płynny na laptopie i tablecie kierownika.
- **⚠️ H10 Pomoc / onboarding** — rozproszone. → **dodać T-9.5**: spójne
  podpowiedzi kontekstowe i stany puste z pierwszym krokiem.
- **⚠️ Dźwięk/wibracja na kiosku** — ujęte w T-5.5, ale zależne od sprzętu;
  zweryfikować w **T-10.1** (niektóre tablety/PWA ograniczają audio bez
  interakcji — mieć wariant czysto wizualny jako zapas).

> Po akceptacji dopiszę T-9.4 i T-9.5, by macierz była w 100% na ✅.

---

## Propozycja cięcia MVP (mniejszy pierwszy krok)
Jeśli chcesz szybciej zobaczyć działanie, **MVP-0** = E0 + E1 + E3 (1 firma,
1 zakład, pracownicy+karty) + E4 + E5 (kiosk z odbiciami offline) + T-7.1/T-7.2
(raport + CSV). Reszta (super-admin multi-firmowy, kierownicy, PDF, cron,
ustawienia zaawansowane) w kolejnych iteracjach.

---

## Historia zmian
- 0.1 (2026-06-27) — pierwszy podział na zadania (E0–E10) z kryteriami UX i
  macierzą walidacji wg `ux-guidelines.md`.
