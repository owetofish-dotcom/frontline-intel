# Konstytucja projektu — CzaseoPraceo

> Zasady nadrzędne projektu. Każda decyzja w fazach Plan / Tasks / Implement
> musi być z nimi zgodna. Zmiana zasady wymaga świadomej decyzji i wpisu w
> historii zmian na końcu pliku.

Wersja: 0.1 (szkic) · Data: 2026-06-27

---

## I. Prostota po stronie pracownika (nienaruszalna)
Interakcja pracownika z kioskiem musi sprowadzać się do: **przyłożenie karty
→ jedno potwierdzenie**. Żadnych logowań, haseł, menu ani konfiguracji po
stronie pracownika. Jeśli funkcja komplikuje ten przepływ — nie trafia do
kiosku.

## II. Niezawodność i tryb offline-first kiosku
Kiosk musi rejestrować odbicia **bez dostępu do internetu** i synchronizować
je, gdy łącze wróci. Utrata sieci nie może spowodować utraty ani zafałszowania
odbicia. Lokalny bufor jest źródłem prawdy do czasu potwierdzonej synchronizacji.

## III. Wiarygodność i audytowalność rejestru czasu
Zapis czasu pracy to dane rozliczeniowe. Odbicia są **niezmienne** — korekty
admina nie nadpisują oryginału, lecz tworzą wpis korygujący z autorem, datą i
powodem. Każda godzina w raporcie musi dać się prześledzić do źródła.

## IV. Izolacja najemców (multi-tenant)
Dane jednej firmy są całkowicie odseparowane od danych innych firm. Żadne
zapytanie ani raport nie może przekroczyć granicy najemcy. Brak izolacji =
błąd krytyczny.

## V. Ochrona danych osobowych (RODO)
System przetwarza dane pracowników (imię, nazwisko, numer karty, czas pracy).
Minimalizujemy zbierane dane, szyfrujemy w tranzycie, ograniczamy dostęp wg
ról i przewidujemy usuwanie/eksport danych. Numer karty RFID traktujemy jak
dane wrażliwe identyfikujące osobę.

## VI. Prostota technologiczna
Wybieramy najprostszy stack realizujący zasady I–V. Bez przedwczesnej
optymalizacji i bez komponentów „na zapas". Każda zależność musi zarabiać na
swoje utrzymanie.

---

## Zakres MVP (świadome granice)
**W zakresie:** odbicia wejście/wyjście przez RFID, kiosk offline-first,
panel admina (web), zarządzanie pracownikami i kartami, ręczne korekty wpisów,
raporty CSV/PDF z listą godzin, opcjonalna stawka godzinowa, hierarchia
super-admin → admin firmy → pracownik.

**Poza zakresem (na razie):** przerwy, projekty/klienci, integracje z systemami
kadrowo-płacowymi, grafiki/harmonogramy, geolokalizacja, aplikacja dla
pracownika na jego prywatny telefon.

---

## Historia zmian
- 0.1 (2026-06-27) — pierwszy szkic na podstawie wywiadu Spec Kit.
