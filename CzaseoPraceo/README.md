# CzaseoPraceo

System rejestracji czasu pracy (RCP) typu SaaS: pracownik odbija kartę **RFID**
o czytnik podpięty do tabletu-kiosku, a firma rozlicza czas na podstawie
raportów. Wiele firm (multi-tenant), wiele zakładów w firmie, role panelu
(super-admin / admin / kierownik zakładu).

## Stack
PHP 8 + MySQL · panel web renderowany serwerowo · kiosk jako **PWA**
(offline-first) · hosting **home.pl** (FTP + phpMyAdmin). Bez frameworka SPA i
bez kroku budowania — pliki wgrywane wprost przez FTP.

## Struktura
```
CzaseoPraceo/
├── docs/      Dokumentacja Spec Kit (constitution, spec, plan, ux, tasks)
├── db/        schema.sql + seed.php (super-admin)
├── lib/       Rdzeń PHP (config, PDO, Auth, Scope, helpery) — niedostępny przez WWW
├── admin/     Panel (logowanie, zarządzanie) — w budowie
├── api/       Endpointy JSON dla kiosku — w budowie
└── kiosk/     PWA na tablet — w budowie
```

## Uruchomienie (home.pl)
Pełna instrukcja krok po kroku: **`docs/DEPLOY.md`**. W skrócie:
1. Załóż bazę MySQL i zaimportuj `db/schema.sql` przez **phpMyAdmin**.
2. Skopiuj `config.local.example.php` → `config.local.php` i uzupełnij dane bazy.
3. Wgraj pliki przez **FTP** (HTTPS na domenie — wymagane dla PWA).
4. Utwórz super-admina:  `php db/seed.php twoj@email.pl "Haslo"` (potem usuń `seed.php`).
5. Skonfiguruj kiosk: zarejestruj w panelu → wpisz token na tablecie → dodaj do ekranu głównego.

## Dokumentacja
Pełny kontekst w `docs/`: `spec.md`, `plan.md`, `ux-guidelines.md`, `tasks.md`,
`DEPLOY.md`, `constitution.md`.

## Status — gotowe (zweryfikowane E2E na MariaDB + render przeglądarki)
- **E0** fundament · **E1** logowanie/RBAC · **E2** super-admin (firmy/admini)
- **E3** admin firmy (zakłady, pracownicy, karty RFID, kierownicy)
- **E4** API kiosku (token, sync idempotentny, dedup) · **E5** kiosk PWA offline
- **E6** korekty + ustawienia · **E7** raporty CSV/PDF · **E8** cron · **E9** a11y/responsywność

Pozostaje **T-10.1** — test na docelowym tablecie i czytniku RFID (po Twojej stronie).
Szczegóły i checklista w `docs/DEPLOY.md`.
