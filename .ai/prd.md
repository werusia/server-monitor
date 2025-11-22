# Dokument wymagań produktu (PRD) - Server Monitor

## 1. Przegląd produktu

Server Monitor to aplikacja MVP służąca do monitorowania pojedynczego serwera Linux (Ubuntu/Debian) w czasie rzeczywistym. Aplikacja zbiera metryki systemowe przez połączenie SSH i prezentuje je w formie interaktywnego dashboardu z wykresami.

Aplikacja składa się z dwóch głównych komponentów:
- Backend (Symfony 7.3): Command uruchamiany przez cron do zbierania metryk z serwera przez SSH i zapisywania ich do bazy danych MySQL
- Frontend: Dashboard webowy z wykresami prezentującymi metryki w różnych zakresach czasowych

Kluczowe cechy MVP:
- Monitorowanie jednego serwera Linux
- Zbieranie metryk: CPU, RAM, Disk, I/O, Network
- Retencja danych: 90 dni z automatycznym czyszczeniem
- Dashboard z wykresami Chart.js
- Responsywny design (mobile i desktop)
- Autoryzacja hasłem

## 2. Problem użytkownika

Administratorzy serwerów potrzebują prostego i niezawodnego narzędzia do monitorowania stanu serwera Linux bez konieczności instalowania agentów na monitorowanym serwerze. Obecne rozwiązania są często zbyt skomplikowane dla prostych przypadków użycia lub wymagają dodatkowej infrastruktury.

Główne problemy, które rozwiązuje aplikacja:
- Brak potrzeby instalacji agentów na monitorowanym serwerze (wykorzystanie SSH)
- Prosty dostęp do metryk przez przeglądarkę
- Automatyczne zbieranie danych bez konieczności ręcznego sprawdzania
- Historyczne dane do analizy trendów
- Lekka aplikacja bez zbędnych zależności

## 3. Wymagania funkcjonalne

### 3.1. Zbieranie metryk

Aplikacja musi zbierać następujące metryki z serwera Linux przez SSH:
- CPU usage: procentowe wykorzystanie procesora (z `/proc/loadavg`)
- RAM usage: wykorzystanie pamięci w GB (z `/proc/meminfo`)
- Disk usage: wykorzystanie dysku w GB
- I/O: read/write bytes (z `/proc/diskstats`)
- Network: bytes sent/received (z `/proc/net/dev`)

Zbieranie metryk odbywa się automatycznie co minutę przez cron command.

### 3.2. Połączenie SSH

Aplikacja łączy się z serwerem używając:
- Base64 encoded private key przechowywany w `.env`
- Username z `.env`
- Address (IP lub hostname) z `.env`
- Port SSH (domyślnie 22, konfigurowalny przez `SSH_PORT` w `.env`)

### 3.3. Obsługa błędów SSH

W przypadku problemów z połączeniem SSH:
- Timeout: 30 sekund
- Retry z exponential backoff: maksymalnie 2-3 próby
- Logowanie błędów do plików (poziomy Error i Warning)
- Monolog z rotacją dzienną w lokalizacji `var/log/`

### 3.4. Przechowywanie danych

- Baza danych: MySQL 8.0+
- Tabela: `server_metrics` z kolumnami: `id`, `server_id`, `timestamp`, `cpu_usage`, `ram_usage`, `disk_usage`, `io_read_bytes`, `io_write_bytes`, `network_sent_bytes`, `network_received_bytes`
- Indeksy na kolumnach `timestamp` i `server_id` dla wydajności zapytań
- Retencja: 90 dni z automatycznym usuwaniem starszych rekordów przez cron (raz dziennie)

### 3.5. Dashboard

- Autoryzacja: pojedyncze hasło z `.env`
- Wykresy: Chart.js dla wszystkich kategorii metryk
- Layout: jedna strona z sekcjami wertykalnymi dla każdej kategorii metryk
- Zakresy czasowe: 1h, 6h, 24h (domyślnie), 7d, 30d
- Agregacja: dla zakresów >7 dni automatyczna agregacja co 10 minut w SQL
- Jednostki wyświetlania:
  - CPU: %
  - RAM: GB (z przełączaniem na MB)
  - Disk: GB
  - Network: MB/s lub GB/h
- Responsywność: pełne wsparcie dla mobile i desktop (Bootstrap lub Tailwind)
- Obsługa błędów: czytelne komunikaty "Brak danych", ostatnia znana wartość z timestampem przy błędach SSH
- Refresh: odświeżanie na żądanie użytkownika (brak auto-refresh w MVP)

### 3.6. Konfiguracja

- `.env.example` z dokumentacją wszystkich wymaganych zmiennych
- `.env.local` dla lokalnych konfiguracji (gitignored)
- Wszystkie credentials i konfiguracje SSH w `.env`

## 4. Granice produktu

### 4.1. Zakres MVP

- Obsługa tylko jednego serwera Linux (Ubuntu/Debian)
- Tylko metryki sieciowe: bytes sent/received (brak pakietów, błędów, etc.)
- Brak alertów i powiadomień
- Brak eksportu danych
- Brak logowania aktywności użytkowników
- Brak cache (wszystkie zapytania bezpośrednio do bazy)
- Brak auto-refresh w dashboardzie

### 4.2. Ograniczenia techniczne

- Wymaga dostępu SSH do monitorowanego serwera
- Wymaga MySQL 8.0+ jako bazy danych
- Działa tylko z serwerami Linux z dostępem do `/proc` filesystem
- Framework: Symfony 7.3 z minimalnymi bundles (console, doctrine/orm, security, twig)

### 4.3. Wykluczone z MVP

- Obsługa wielu serwerów jednocześnie
- Zaawansowane metryki sieciowe (pakietów, błędów, etc.)
- System alertów i powiadomień
- Eksport danych do plików
- API REST dla zewnętrznych integracji
- Różne role użytkowników i uprawnienia
- Cache warstwa dla wydajności
- Auto-refresh dashboardu
- Obsługa innych systemów operacyjnych niż Linux

## 5. Historyjki użytkowników

### US-001: Konfiguracja połączenia SSH

**Tytuł**: Jako administrator, chcę skonfigurować połączenie SSH do monitorowanego serwera, aby aplikacja mogła zbierać metryki.

**Opis**: Administrator musi mieć możliwość skonfigurowania wszystkich parametrów połączenia SSH w pliku `.env`, w tym klucza prywatnego (base64 encoded), username, address i portu.

**Kryteria akceptacji**:
- Plik `.env.example` zawiera wszystkie wymagane zmienne z dokumentacją
- Aplikacja odczytuje konfigurację SSH z `.env` (klucz, username, address, port)
- Port SSH jest konfigurowalny przez `SSH_PORT` (domyślnie 22)
- Base64 encoded private key jest poprawnie dekodowany przed użyciem
- Aplikacja waliduje obecność wszystkich wymaganych zmiennych przy starcie

### US-002: Automatyczne zbieranie metryk CPU

**Tytuł**: Jako system, chcę automatycznie zbierać metryki CPU co minutę, aby mieć aktualne dane o wykorzystaniu procesora.

**Opis**: Cron command uruchamiany co minutę łączy się z serwerem przez SSH i odczytuje metryki CPU z `/proc/loadavg`, następnie zapisuje je do bazy danych.

**Kryteria akceptacji**:
- Command uruchamiany przez cron co minutę
- Połączenie SSH nawiązywane poprawnie z użyciem credentials z `.env`
- Metryka CPU odczytywana z `/proc/loadavg`
- Wartość zapisywana jako procent w kolumnie `cpu_usage`
- Rekord zawiera poprawny `timestamp` i `server_id`

### US-003: Automatyczne zbieranie metryk RAM

**Tytuł**: Jako system, chcę automatycznie zbierać metryki RAM co minutę, aby monitorować wykorzystanie pamięci.

**Opis**: Command odczytuje informacje o pamięci z `/proc/meminfo` i zapisuje wykorzystanie RAM w GB do bazy danych.

**Kryteria akceptacji**:
- Metryka RAM odczytywana z `/proc/meminfo`
- Wartość zapisywana w GB w kolumnie `ram_usage`
- Poprawna konwersja z bajtów/kB na GB
- Rekord zawiera poprawny `timestamp` i `server_id`

### US-004: Automatyczne zbieranie metryk Disk

**Tytuł**: Jako system, chcę automatycznie zbierać metryki wykorzystania dysku co minutę, aby monitorować przestrzeń dyskową.

**Opis**: Command odczytuje wykorzystanie dysku i zapisuje wartość w GB do bazy danych.

**Kryteria akceptacji**:
- Metryka disk usage odczytywana poprawnie
- Wartość zapisywana w GB w kolumnie `disk_usage`
- Rekord zawiera poprawny `timestamp` i `server_id`

### US-005: Automatyczne zbieranie metryk I/O

**Tytuł**: Jako system, chcę automatycznie zbierać metryki I/O (read/write bytes) co minutę, aby monitorować aktywność dyskową.

**Opis**: Command odczytuje read/write bytes z `/proc/diskstats` i zapisuje je do bazy danych.

**Kryteria akceptacji**:
- Read bytes odczytywane z `/proc/diskstats`
- Write bytes odczytywane z `/proc/diskstats`
- Wartości zapisywane w kolumnach `io_read_bytes` i `io_write_bytes`
- Rekord zawiera poprawny `timestamp` i `server_id`

### US-006: Automatyczne zbieranie metryk Network

**Tytuł**: Jako system, chcę automatycznie zbierać metryki sieciowe (bytes sent/received) co minutę, aby monitorować ruch sieciowy.

**Opis**: Command odczytuje bytes sent/received z `/proc/net/dev` i zapisuje je do bazy danych.

**Kryteria akceptacji**:
- Bytes sent odczytywane z `/proc/net/dev`
- Bytes received odczytywane z `/proc/net/dev`
- Wartości zapisywane w kolumnach `network_sent_bytes` i `network_received_bytes`
- Rekord zawiera poprawny `timestamp` i `server_id`

### US-007: Obsługa timeoutów SSH

**Tytuł**: Jako system, chcę mieć timeout 30 sekund dla połączeń SSH, aby uniknąć zawieszenia aplikacji przy problemach sieciowych.

**Opis**: W przypadku braku odpowiedzi z serwera w ciągu 30 sekund, połączenie SSH jest przerywane i błąd jest logowany.

**Kryteria akceptacji**:
- Timeout ustawiony na 30 sekund dla połączeń SSH
- Po przekroczeniu timeoutu połączenie jest przerywane
- Błąd timeoutu jest logowany do pliku (poziom Error)
- Aplikacja kontynuuje działanie po timeoutcie

### US-008: Retry z exponential backoff przy błędach SSH

**Tytuł**: Jako system, chcę automatycznie ponawiać próby połączenia SSH z exponential backoff, aby obsłużyć przejściowe problemy sieciowe.

**Opis**: W przypadku błędu połączenia SSH, system wykonuje maksymalnie 2-3 próby z rosnącym opóźnieniem między próbami.

**Kryteria akceptacji**:
- W przypadku błędu SSH wykonywana jest automatyczna ponowna próba
- Maksymalnie 2-3 próby łącznie
- Opóźnienie między próbami rośnie wykładniczo (exponential backoff)
- Wszystkie próby są logowane (poziom Warning)
- Po wyczerpaniu prób błąd jest logowany jako Error

### US-009: Logowanie błędów SSH

**Tytuł**: Jako administrator, chcę mieć logi błędów SSH, aby diagnozować problemy z połączeniem.

**Opis**: Wszystkie błędy i ostrzeżenia związane z połączeniami SSH są logowane do plików z użyciem Monolog z rotacją dzienną.

**Kryteria akceptacji**:
- Błędy SSH logowane na poziomie Error
- Ostrzeżenia (retry) logowane na poziomie Warning
- Logi zapisywane w lokalizacji `var/log/`
- Monolog z rotacją dzienną (nowy plik każdego dnia)
- Logi zawierają timestamp, typ błędu i szczegóły

### US-010: Automatyczne usuwanie starych rekordów

**Tytuł**: Jako system, chcę automatycznie usuwać rekordy starsze niż 90 dni, aby kontrolować rozmiar bazy danych.

**Opis**: Cron command uruchamiany raz dziennie usuwa wszystkie rekordy z tabeli `server_metrics` starsze niż 90 dni.

**Kryteria akceptacji**:
- Command uruchamiany raz dziennie przez cron
- Usuwane są wszystkie rekordy starsze niż 90 dni (na podstawie kolumny `timestamp`)
- Operacja jest bezpieczna i nie blokuje innych operacji
- Po usunięciu brak błędów w logach

### US-011: Autoryzacja hasłem w dashboardzie

**Tytuł**: Jako użytkownik, chcę logować się do dashboardu hasłem, aby zabezpieczyć dostęp do metryk.

**Opis**: Dashboard wymaga podania hasła przechowywanego w `.env` przed wyświetleniem danych.

**Kryteria akceptacji**:
- Dashboard wyświetla formularz logowania
- Hasło odczytywane z `.env`
- Nieprawidłowe hasło wyświetla komunikat błędu
- Prawidłowe hasło umożliwia dostęp do dashboardu
- Sesja użytkownika jest utrzymywana po zalogowaniu

### US-012: Wyświetlanie wykresu CPU w dashboardzie

**Tytuł**: Jako użytkownik, chcę widzieć wykres wykorzystania CPU w dashboardzie, aby monitorować obciążenie procesora.

**Opis**: Dashboard wyświetla wykres Chart.js z historią wykorzystania CPU w wybranym zakresie czasowym.

**Kryteria akceptacji**:
- Wykres CPU wyświetlany w sekcji dashboardu
- Wykorzystanie Chart.js do renderowania wykresu
- Wartości wyświetlane w procentach (%)
- Wykres pokazuje dane z wybranego zakresu czasowego
- Wykres jest responsywny i działa na mobile i desktop

### US-013: Wyświetlanie wykresu RAM w dashboardzie

**Tytuł**: Jako użytkownik, chcę widzieć wykres wykorzystania RAM w dashboardzie, aby monitorować zużycie pamięci.

**Opis**: Dashboard wyświetla wykres Chart.js z historią wykorzystania RAM w wybranym zakresie czasowym z możliwością przełączania jednostek (GB/MB).

**Kryteria akceptacji**:
- Wykres RAM wyświetlany w sekcji dashboardu
- Wykorzystanie Chart.js do renderowania wykresu
- Wartości wyświetlane domyślnie w GB
- Możliwość przełączania jednostek na MB
- Wykres pokazuje dane z wybranego zakresu czasowego
- Wykres jest responsywny i działa na mobile i desktop

### US-014: Wyświetlanie wykresu Disk w dashboardzie

**Tytuł**: Jako użytkownik, chcę widzieć wykres wykorzystania dysku w dashboardzie, aby monitorować przestrzeń dyskową.

**Opis**: Dashboard wyświetla wykres Chart.js z historią wykorzystania dysku w wybranym zakresie czasowym.

**Kryteria akceptacji**:
- Wykres Disk wyświetlany w sekcji dashboardu
- Wykorzystanie Chart.js do renderowania wykresu
- Wartości wyświetlane w GB
- Wykres pokazuje dane z wybranego zakresu czasowego
- Wykres jest responsywny i działa na mobile i desktop

### US-015: Wyświetlanie wykresów I/O w dashboardzie

**Tytuł**: Jako użytkownik, chcę widzieć wykresy I/O (read/write bytes) w dashboardzie, aby monitorować aktywność dyskową.

**Opis**: Dashboard wyświetla wykresy Chart.js z historią read/write bytes w wybranym zakresie czasowym.

**Kryteria akceptacji**:
- Wykresy I/O wyświetlane w sekcji dashboardu (read i write jako osobne linie lub wykresy)
- Wykorzystanie Chart.js do renderowania wykresów
- Wartości wyświetlane w bajtach (z odpowiednimi jednostkami: KB, MB, GB)
- Wykresy pokazują dane z wybranego zakresu czasowego
- Wykresy są responsywne i działają na mobile i desktop

### US-016: Wyświetlanie wykresów Network w dashboardzie

**Tytuł**: Jako użytkownik, chcę widzieć wykresy sieciowe (bytes sent/received) w dashboardzie, aby monitorować ruch sieciowy.

**Opis**: Dashboard wyświetla wykresy Chart.js z historią bytes sent/received w wybranym zakresie czasowym z jednostkami MB/s lub GB/h.

**Kryteria akceptacji**:
- Wykresy Network wyświetlane w sekcji dashboardu (sent i received jako osobne linie lub wykresy)
- Wykorzystanie Chart.js do renderowania wykresów
- Wartości wyświetlane w MB/s lub GB/h
- Wykresy pokazują dane z wybranego zakresu czasowego
- Wykresy są responsywne i działają na mobile i desktop

### US-017: Wybór zakresu czasowego w dashboardzie

**Tytuł**: Jako użytkownik, chcę wybierać zakres czasowy danych w dashboardzie, aby analizować metryki w różnych okresach.

**Opis**: Dashboard oferuje opcje wyboru zakresu czasowego: 1h, 6h, 24h (domyślnie), 7d, 30d, które wpływają na dane wyświetlane we wszystkich wykresach.

**Kryteria akceptacji**:
- Dostępne opcje: 1h, 6h, 24h (domyślnie), 7d, 30d
- Wybór zakresu aktualizuje wszystkie wykresy
- Domyślnie wybrany zakres to 24h
- Zmiana zakresu wymaga odświeżenia danych (brak auto-refresh)
- Interfejs wyboru jest intuicyjny i dostępny

### US-018: Agregacja danych dla długich zakresów czasowych

**Tytuł**: Jako system, chcę agregować dane dla zakresów >7 dni, aby uniknąć renderowania tysięcy punktów na wykresach.

**Opis**: Dla zakresów czasowych 7d i 30d dane są agregowane co 10 minut w SQL przed wyświetleniem na wykresach.

**Kryteria akceptacji**:
- Dla zakresów 1h, 6h, 24h wyświetlane są wszystkie dane
- Dla zakresów 7d i 30d dane są agregowane co 10 minut
- Agregacja wykonana w SQL (średnia lub ostatnia wartość w przedziale)
- Liczba punktów na wykresie jest rozsądna (<1000)
- Wykresy pozostają czytelne i responsywne

### US-019: Responsywny design dashboardu

**Tytuł**: Jako użytkownik, chcę korzystać z dashboardu na urządzeniach mobile i desktop, aby mieć dostęp do metryk z dowolnego urządzenia.

**Opis**: Dashboard wykorzystuje Bootstrap lub Tailwind CSS do zapewnienia pełnej responsywności na różnych rozdzielczościach ekranu.

**Kryteria akceptacji**:
- Dashboard poprawnie wyświetla się na urządzeniach mobile (<768px)
- Dashboard poprawnie wyświetla się na tabletach (768px-1024px)
- Dashboard poprawnie wyświetla się na desktop (>1024px)
- Wykresy są czytelne i interaktywne na wszystkich urządzeniach
- Layout dostosowuje się do szerokości ekranu
- Wszystkie elementy są dostępne i użyteczne na mobile

### US-020: Obsługa braku danych w dashboardzie

**Tytuł**: Jako użytkownik, chcę widzieć czytelny komunikat gdy brakuje danych, aby zrozumieć stan systemu.

**Opis**: W przypadku braku danych dla wybranego zakresu czasowego, dashboard wyświetla czytelny komunikat "Brak danych" zamiast pustego wykresu.

**Kryteria akceptacji**:
- W przypadku braku danych wyświetlany jest komunikat "Brak danych"
- Komunikat jest czytelny i zrozumiały
- Komunikat wyświetlany dla każdej sekcji metryk osobno
- Dashboard pozostaje funkcjonalny mimo braku danych

### US-021: Wyświetlanie ostatniej znanej wartości przy błędach SSH

**Tytuł**: Jako użytkownik, chcę widzieć ostatnią znaną wartość metryki z timestampem gdy występuje błąd SSH, aby mieć informację o ostatnim stanie serwera.

**Opis**: W przypadku błędu połączenia SSH, dashboard wyświetla ostatnią znaną wartość metryki z informacją o timestampie ostatniego udanego odczytu.

**Kryteria akceptacji**:
- W przypadku błędu SSH wyświetlana jest ostatnia znana wartość
- Wyświetlany jest timestamp ostatniego udanego odczytu
- Informacja o błędzie jest czytelna (np. "Ostatnia wartość z [timestamp]")
- Użytkownik rozumie, że dane mogą być nieaktualne

### US-022: Odświeżanie dashboardu na żądanie

**Tytuł**: Jako użytkownik, chcę odświeżać dane w dashboardzie ręcznie, aby zobaczyć najnowsze metryki.

**Opis**: Dashboard oferuje przycisk "Odśwież" który ładuje najnowsze dane z bazy danych i aktualizuje wszystkie wykresy.

**Kryteria akceptacji**:
- Przycisk "Odśwież" dostępny w dashboardzie
- Kliknięcie przycisku ładuje najnowsze dane z bazy
- Wszystkie wykresy są aktualizowane po odświeżeniu
- Brak auto-refresh (tylko na żądanie użytkownika)
- Odświeżanie działa poprawnie dla wszystkich zakresów czasowych

### US-023: Layout dashboardu z sekcjami wertykalnymi

**Tytuł**: Jako użytkownik, chcę widzieć wszystkie kategorie metryk na jednej stronie w sekcjach wertykalnych, aby mieć pełny przegląd serwera.

**Opis**: Dashboard składa się z jednej strony z sekcjami dla każdej kategorii metryk (CPU, RAM, Disk, I/O, Network) ułożonymi wertykalnie.

**Kryteria akceptacji**:
- Wszystkie sekcje metryk na jednej stronie
- Sekcje ułożone wertykalnie (jedna pod drugą)
- Każda sekcja ma czytelny nagłówek
- Możliwość przewijania strony do wszystkich sekcji
- Layout jest czytelny i uporządkowany

### US-024: Tworzenie migracji bazy danych

**Tytuł**: Jako developer, chcę używać Doctrine Migrations do zarządzania schematem bazy danych, aby mieć kontrolę nad zmianami struktury.

**Opis**: Schemat bazy danych jest zarządzany przez Doctrine Migrations, umożliwiając wersjonowanie i łatwe wdrażanie zmian.

**Kryteria akceptacji**:
- Tabela `server_metrics` tworzona przez migrację
- Wszystkie kolumny zdefiniowane poprawnie: `id`, `server_id`, `timestamp`, `cpu_usage`, `ram_usage`, `disk_usage`, `io_read_bytes`, `io_write_bytes`, `network_sent_bytes`, `network_received_bytes`
- Indeksy na kolumnach `timestamp` i `server_id` tworzone przez migrację
- Migracje można wykonać i cofnąć
- Brak seederów (dane tylko z rzeczywistego zbierania)

### US-025: Konfiguracja środowiska przez .env.example

**Tytuł**: Jako developer, chcę mieć plik `.env.example` z dokumentacją, aby łatwo skonfigurować aplikację w nowym środowisku.

**Opis**: Plik `.env.example` zawiera wszystkie wymagane zmienne środowiskowe z komentarzami wyjaśniającymi ich przeznaczenie.

**Kryteria akceptacji**:
- Plik `.env.example` zawiera wszystkie wymagane zmienne
- Każda zmienna ma komentarz wyjaśniający jej przeznaczenie
- Przykładowe wartości pokazują format danych
- Dokumentacja jest czytelna i kompletna
- `.env.local` jest w `.gitignore` dla lokalnych konfiguracji

## 6. Metryki sukcesu

### 6.1. Niezawodność zbierania danych

**Metryka**: Procent udanych połączeń SSH w ciągu 24 godzin
- Cel: >95% udanych połączeń
- Pomiar: Liczba udanych połączeń / całkowita liczba prób w ciągu 24h
- Monitoring: Logi aplikacji i metryki w bazie danych

**Metryka**: Czas odpowiedzi SSH
- Cel: <30 sekund dla 95% połączeń
- Pomiar: Czas od rozpoczęcia połączenia do otrzymania danych
- Monitoring: Logi z timestampami połączeń

### 6.2. Dokładność danych

**Metryka**: Kompletność danych
- Cel: 100% kompletnych rekordów (wszystkie metryki zbierane w każdym cyklu)
- Pomiar: Procent rekordów zawierających wszystkie wymagane metryki
- Monitoring: Sprawdzanie rekordów w bazie danych

**Metryka**: Poprawność formatów danych
- Cel: 0 błędów parsowania danych z `/proc`
- Pomiar: Liczba błędów parsowania w logach
- Monitoring: Logi aplikacji (poziom Error)

### 6.3. Wydajność dashboardu

**Metryka**: Czas ładowania dashboardu
- Cel: <2 sekundy od żądania do wyświetlenia
- Pomiar: Czas od żądania HTTP do renderowania wykresów
- Monitoring: Logi serwera webowego i pomiary w przeglądarce

**Metryka**: Płynność renderowania wykresów
- Cel: 60 FPS podczas interakcji z wykresami
- Pomiar: FPS podczas scrollowania i zoomowania wykresów
- Monitoring: Narzędzia deweloperskie przeglądarki

### 6.4. Retencja danych

**Metryka**: Automatyczne usuwanie rekordów starszych niż 90 dni
- Cel: 100% rekordów starszych niż 90 dni usuniętych codziennie
- Pomiar: Sprawdzenie czy najstarszy rekord w bazie nie przekracza 90 dni
- Monitoring: Logi cron command i sprawdzenie bazy danych

**Metryka**: Brak wycieków pamięci w bazie danych
- Cel: Stabilny rozmiar bazy danych (nie rośnie w nieskończoność)
- Pomiar: Rozmiar bazy danych i liczba rekordów
- Monitoring: Metryki bazy danych MySQL

### 6.5. Bezpieczeństwo

**Metryka**: Autoryzacja działa poprawnie
- Cel: 0 nieautoryzowanych dostępów do dashboardu
- Pomiar: Liczba prób nieautoryzowanego dostępu i udanych logowań
- Monitoring: Logi aplikacji (próby logowania)

**Metryka**: Bezpieczne przechowywanie credentials
- Cel: Brak credentials w kodzie źródłowym
- Pomiar: Sprawdzenie że wszystkie credentials są w `.env` (gitignored)
- Monitoring: Code review i sprawdzenie `.gitignore`

### 6.6. Użyteczność

**Metryka**: Dostępność dashboardu
- Cel: 99% uptime dashboardu
- Pomiar: Czas dostępności aplikacji webowej
- Monitoring: Zewnętrzne narzędzia monitoringu (opcjonalnie)

**Metryka**: Responsywność na różnych urządzeniach
- Cel: Pełna funkcjonalność na mobile, tablet i desktop
- Pomiar: Testy na różnych rozdzielczościach ekranu
- Monitoring: Manualne testy i feedback użytkowników

