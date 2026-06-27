# Plan techniczny — CzaseoPraceo

> Faza Spec Kit: **Plan** (jak budujemy). Zgodny z `constitution.md` i `spec.md`.
> Dobrany pod realny stack i hosting użytkownika: **home.pl (PHP 8 + MySQL,
> deploy przez FTP, zarządzanie bazą przez phpMyAdmin)**.

Wersja: 0.1 (szkic) · Data: 2026-06-27

---

## 1. Ograniczenia środowiska (home.pl)
- Hosting współdzielony LAMP: **PHP 8**, **MySQL**, **FTP**, **phpMyAdmin**.
- Brak Node.js / brak długo żyjących procesów / brak WebSocketów serwerowych.
- Deploy = wgranie plików przez **FTP**; zależności PHP wgrywane jako `vendor/`
  (bez konieczności Composera na serwerze).
- **HTTPS/SSL dostępny** (wymagany dla Service Workera PWA i RODO).
- Cron na home.pl (jeśli w pakiecie) — opcjonalnie do zadań porządkowych.

## 2. Stack
| Warstwa | Technologia | Uzasadnienie |
|---------|-------------|--------------|
| Backend API | **PHP 8** (lekki router, bez ciężkiego frameworka) | zgodny z hostingiem, prosto na FTP |
| Baza danych | **MySQL** (phpMyAdmin) | dostępna w home.pl |
| Panel admina | PHP (render serwerowy) + **HTML/CSS/JS** | znany stack, brak budowania |
| Kiosk | **PWA**: HTML/CSS/JS + Service Worker + IndexedDB | offline-first bez natywnej apki |
| Eksport PDF | biblioteka pure-PHP (**FPDF** lub **mPDF**) wgrana jako `vendor/` | działa na shared hostingu |
| Eksport CSV | natywny PHP (`fputcsv`) | bez zależności |
| Czytnik RFID | wejście typu klawiatura → `keydown` w JS | zgodne z posiadanym czytnikiem (same cyfry + Enter) |

> Świadomie **bez frameworka SPA** (React/Vue) i bez kroku budowania — pliki
> wgrywane wprost przez FTP. Jeśli panel rozrośnie się w przyszłości, można
> dołożyć lekki JS, ale nie jest to potrzebne na MVP.

## 3. Architektura (3 komponenty na jednym hostingu)
```
home.pl (HTTPS)
├── /api        ← PHP: endpointy JSON (odbicia, sync, dane)
├── /admin      ← PHP+HTML: panel super-admina i admina firmy (logowanie sesyjne)
├── /kiosk      ← PWA: ekran tabletu (offline-first, IndexedDB, Service Worker)
└── MySQL       ← jedna baza, izolacja najemców przez tenant_id
```
- **Kiosk (PWA)**: nasłuchuje odbicia karty (JS `keydown` zbiera cyfry do Enter),
  rozpoznaje pracownika z lokalnej kopii listy firmy, zapisuje odbicie do
  **IndexedDB**, pokazuje potwierdzenie + listę obecnych (inicjały 3+3). W tle
  synchronizuje bufor z `/api` gdy jest sieć.
- **Panel (PHP)**: logowanie e‑mail+hasło (sesje PHP, hasła `password_hash`),
  CRUD pracowników/kart, korekty wpisów, raporty, ustawienia firmy.
- **API (PHP)**: przyjmuje zsynchronizowane odbicia (idempotentnie — po
  identyfikatorze odbicia z urządzenia), serwuje listę pracowników firmy do
  kiosku, zwraca dane do raportów.

## 4. Kluczowa decyzja: kiosk = PWA (nie natywna apka)
**Dlaczego:** stack użytkownika to PHP/JS na home.pl — natywny Android/iOS
wymagałby innego języka, sklepu i utrzymania. PWA daje offline (Service Worker +
IndexedDB), instalację „na ekran główny" tabletu i tryb pełnoekranowy, pozostając
w HTML/CSS/JS.
**Kompromis:** PWA nie ma tak głębokiego dostępu do sprzętu jak apka natywna,
ale czytnik RFID działa jako klawiatura (nie potrzebuje API sprzętowego), więc
to nam nie przeszkadza. Wymóg: serwowanie po **HTTPS**.

## 5. Multi-tenant (izolacja najemców)
- Model: **jedna baza MySQL**, kolumna `tenant_id` (firma) w każdej tabeli z
  danymi; **każde** zapytanie filtrowane po `tenant_id` zalogowanej firmy.
- Warstwa dostępu do danych wymusza `tenant_id` centralnie (jeden punkt, by nie
  pominąć filtra) — realizacja zasady IV konstytucji.
- Super-admin to konto bez `tenant_id`, z dostępem ponad firmami (zarządzanie
  najemcami), oddzielone od logiki admina firmy.

## 6. Szkic schematu bazy (do doprecyzowania w fazie Tasks)
- `tenants` — firmy (nazwa, status, ustawienia: zaokrąglanie, praca_przez_polnoc, stawka)
- `panel_users` — konta panelu (rola: super_admin/admin, tenant_id, email, hash hasła)
- `employees` — pracownicy (tenant_id, imię, nazwisko, status)
- `rfid_cards` — karty (tenant_id, employee_id, numer cyfrowy, status: aktywna/zapasowa)
- `punches` — odbicia (tenant_id, employee_id, typ wejście/wyjście, czas_urządzenia,
  device_punch_id do idempotencji, źródło, czas_synchronizacji)
- `punch_corrections` — korekty (punch_id lub wpis ręczny, autor, czas, powód, wartość)
- `kiosks` — urządzenia (tenant_id, nazwa, token autoryzacyjny)

## 7. Endpointy API (zarys)
- `POST /api/punches/sync` — przyjmij paczkę odbić z kiosku (idempotentnie po
  `device_punch_id`), zwróć potwierdzenia.
- `GET  /api/employees?since=…` — lista pracowników firmy do lokalnej kopii kiosku.
- `POST /api/kiosk/auth` — autoryzacja urządzenia tokenem.
- Panel korzysta z PHP renderowanego serwerowo (formularze), nie potrzebuje
  osobnego API na MVP.

## 8. Raporty
- **CSV**: `fputcsv` — lista odbić i suma godzin za okres/pracowników.
- **PDF**: FPDF/mPDF (wgrane jako `vendor/`) — czytelna lista godzin; gdy firma
  ustawi stawkę, kolumna kwoty.
- Zaokrąglanie wg ustawienia firmy (domyślnie pełne godziny); zawsze dostępny
  czas dokładny.

## 9. Bezpieczeństwo i RODO
- HTTPS wszędzie; hasła `password_hash`/`password_verify`.
- Token urządzenia-kiosku zamiast logowania pracownika.
- Filtr `tenant_id` w każdym zapytaniu (izolacja).
- Minimalizacja danych; możliwość eksportu/usunięcia danych pracownika.
- Numer karty traktowany jak dana identyfikująca osobę.

## 10. Deployment (home.pl)
- Struktura katalogów wgrywana przez **FTP**; baza zakładana w panelu home.pl,
  schemat importowany przez **phpMyAdmin** (plik `schema.sql`).
- Konfiguracja (dane bazy) w pliku poza repo (`config.local.php`), nie w gicie.
- `vendor/` (FPDF/mPDF) wgrane przez FTP.

## 11. Ryzyka i do potwierdzenia
- **R-1** Cron na home.pl — czy dostępny (porządki, retry sync)? Jeśli nie,
  sync wyłącznie inicjowany przez kiosk — i tak wystarczy.
- **R-2** Limit liczby baz/rozmiaru — model jednej bazy z `tenant_id` to omija.
- **R-3** Service Worker wymaga HTTPS — potwierdzić SSL na docelowej domenie.
- **R-4** Stabilność „klawiaturowego" czytnika w przeglądarce tabletu —
  przetestować na docelowym sprzęcie wcześnie.

---

## Historia zmian
- 0.1 (2026-06-27) — pierwszy szkic planu pod stack home.pl (PHP 8 / MySQL /
  FTP / PWA kiosk).
