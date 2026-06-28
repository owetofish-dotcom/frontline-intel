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
1. Załóż bazę MySQL w panelu home.pl.
2. Zaimportuj `db/schema.sql` przez **phpMyAdmin**.
3. Skopiuj `config.local.example.php` → `config.local.php` i uzupełnij dane bazy.
4. Wgraj pliki przez **FTP** (zadbaj o HTTPS na domenie — wymagane dla PWA).
5. Utwórz super-admina:  `php db/seed.php twoj@email.pl "Haslo"` (potem usuń `seed.php`).

## Dokumentacja
Pełny kontekst projektu w `docs/` — zacznij od `docs/spec.md` i `docs/tasks.md`.

## Status
W trakcie implementacji wg `docs/tasks.md`. Ukończone: **E0 — fundament**
(schemat bazy, konfiguracja, PDO, warstwa zakresu, uwierzytelnianie, ochrona plików).
