# Wdrożenie CzaseoPraceo na home.pl

Instrukcja krok po kroku. Wymaga: konto home.pl z PHP 8, MySQL, FTP, **HTTPS/SSL**
na domenie (konieczne dla kiosku PWA).

## 1. Baza danych
1. W panelu home.pl utwórz bazę MySQL i użytkownika z pełnym dostępem do niej.
2. Wejdź w **phpMyAdmin** → wybierz bazę → zakładka **Import** → wgraj
   `db/schema.sql` → wykonaj. Powstanie 10 tabel.

## 2. Konfiguracja
1. Skopiuj `config.local.example.php` → `config.local.php`.
2. Uzupełnij dane bazy (`host`, `name`, `user`, `password`). Host i ewentualny
   `port` znajdziesz w panelu home.pl. (Obsługiwany jest też `socket`.)
3. Zostaw `force_https => true`.

## 3. Wgranie plików (FTP)
Wgraj całą zawartość katalogu `CzaseoPraceo/` na serwer, np. do katalogu domeny
(`/public_html/` lub podkatalogu). Po wgraniu adresy będą wyglądać tak:
```
https://twojadomena.pl/admin/    ← panel
https://twojadomena.pl/kiosk/     ← kiosk (na tablet)
https://twojadomena.pl/api/       ← API (używane przez kiosk)
```
> Katalogi `lib/`, `db/`, `cron/` są chronione plikami `.htaccess` (brak dostępu
> przez WWW). `config.local.php` nie jest serwowany.

## 4. Konto super-admina
Utwórz pierwsze konto (super-admin SaaS). Uruchom **z PHP CLI** (SSH lub
jednorazowe zadanie cron w panelu home.pl):
```
/usr/bin/php /sciezka/do/CzaseoPraceo/db/seed.php twoj@email.pl "MocneHaslo"
```
Po utworzeniu konta **usuń `db/seed.php`** z serwera (lub trzymaj poza katalogiem WWW).

Zaloguj się na `https://twojadomena.pl/admin/` → utwórz firmę i konto admina firmy.

## 5. Cron (opcjonalnie, zalecane)
W panelu home.pl dodaj zadanie cron (np. co godzinę):
```
/usr/bin/php /sciezka/do/CzaseoPraceo/cron/maintenance.php >> /sciezka/do/CzaseoPraceo/cron/maintenance.log 2>&1
```

## 6. Konfiguracja kiosku (tablet)
1. W panelu: **Kioski → Zarejestruj kiosk** dla danego zakładu. **Skopiuj token**
   (pokazywany jednorazowo).
2. Na tablecie otwórz `https://twojadomena.pl/kiosk/` (koniecznie **HTTPS**).
3. Wklej token → „Połącz kiosk".
4. Dodaj stronę **do ekranu głównego** (PWA) i uruchom z ikony — działa wtedy
   pełnoekranowo i offline.
5. Zalecane ustawienia tabletu: brak wygaszania ekranu / tryb kiosk, blokada
   wyjścia z aplikacji.

## 7. Czytnik RFID
- Ustaw czytnik w tryb **HID (klawiatura)** — po przyłożeniu karty „wpisuje"
  numer i wciska **Enter**.
- Czytnik podpięty do tabletu (USB/OTG lub Bluetooth).
- Numer karty = **same cyfry** (D-1).

## 8. Checklista po wdrożeniu
- [ ] HTTPS działa na domenie (kłódka) — **R-3**.
- [ ] Logowanie do panelu działa, kontekst „Firma › rola" widoczny.
- [ ] Dodano zakład, pracownika i przypisano kartę RFID.
- [ ] Kiosk połączony tokenem, pokazuje nazwę zakładu.
- [ ] **Test czytnika na docelowym tablecie** — odbicie rozpoznaje pracownika
  (R-4). Jeśli brak dźwięku potwierdzenia, działa wariant wizualny.
- [ ] Odbicie offline (wyłącz Wi‑Fi) zapisuje się i synchronizuje po powrocie sieci.
- [ ] Raport CSV/PDF generuje się poprawnie.

## Aktualizacje
Wgraj zmienione pliki przez FTP (nadpisując). Zmiany w schemacie bazy importuj
przez phpMyAdmin. `config.local.php` i `vendor/` (jeśli używane) pozostają na
serwerze nienaruszone.
