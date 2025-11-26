# Plan Testów - Server Monitor

## 1. Wprowadzenie i Cele Testowania

### 1.1. Cel Dokumentu
Niniejszy dokument definiuje kompleksowy plan testów dla aplikacji Server Monitor - systemu monitorowania serwera Linux w czasie rzeczywistym poprzez połączenia SSH. Plan testów został opracowany w celu zapewnienia wysokiej jakości oprogramowania, niezawodności funkcjonalności oraz zgodności z wymaganiami biznesowymi.

### 1.2. Zakres Projektu
Server Monitor to aplikacja MVP służąca do monitorowania pojedynczego serwera Linux (Ubuntu/Debian) poprzez:
- Zbieranie metryk systemowych (CPU, RAM, Disk, I/O, Network) przez SSH
- Przechowywanie danych w bazie MySQL
- Prezentację danych w interaktywnym dashboardzie webowym z wykresami Chart.js
- Autoryzację użytkownika hasłem
- Automatyczne czyszczenie danych starszych niż 90 dni

### 1.3. Cele Testowania
- **Funkcjonalność**: Weryfikacja, że wszystkie funkcje działają zgodnie z wymaganiami
- **Niezawodność**: Zapewnienie stabilności systemu przy różnych warunkach pracy
- **Bezpieczeństwo**: Weryfikacja mechanizmów autoryzacji i ochrony danych
- **Wydajność**: Sprawdzenie wydajności zbierania i prezentacji danych
- **Użyteczność**: Weryfikacja interfejsu użytkownika i doświadczenia użytkownika
- **Kompatybilność**: Testowanie na różnych przeglądarkach i urządzeniach

### 1.4. Dokumenty Referencyjne
- PRD (Product Requirements Document)
- Dokumentacja techniczna projektu
- Stos technologiczny: Symfony 7.3, PHP 8.3, MySQL 8.0+, Chart.js, Bootstrap 5

---

## 2. Zakres Testów

### 2.1. Funkcjonalności w Zakresie Testów

#### 2.1.1. Backend (Symfony)
- **Zbieranie metryk przez SSH**
  - Połączenie SSH z serwerem
  - Zbieranie metryk CPU, RAM, Disk, I/O, Network
  - Obsługa błędów i retry logic
  - Logowanie operacji
  
- **Przechowywanie danych**
  - Zapis metryk do bazy danych
  - Walidacja danych przed zapisem
  - Indeksowanie dla wydajnych zapytań
  
- **API REST**
  - Endpoint `/api/metrics` - pobieranie metryk w zakresach czasowych
  - Endpoint `/api/metrics/latest` - najnowsze metryki
  - Endpoint `/api/metrics/stats` - statystyki agregowane
  - Endpoint `/api/status` - status systemu
  - Endpoint `/api/login` - autoryzacja
  - Endpoint `/api/logout` - wylogowanie
  
- **Autoryzacja i sesje**
  - Logowanie hasłem z `.env`
  - Zarządzanie sesjami (timeout 30 minut)
  - Ochrona CSRF
  - Przekierowania dla nieautoryzowanych użytkowników
  
- **Komendy konsolowe**
  - `app:collect-metrics` - zbieranie metryk
  - `app:cleanup-old-metrics` - czyszczenie starych danych

#### 2.1.2. Frontend
- **Dashboard**
  - Wyświetlanie wykresów dla wszystkich metryk
  - Przełączanie zakresów czasowych (1h, 6h, 24h, 7d, 30d)
  - Odświeżanie danych
  - Obsługa stanów: brak danych, błędy, dane przestarzałe
  - Responsywność (mobile, tablet, desktop)
  
- **Logowanie**
  - Formularz logowania
  - Walidacja po stronie klienta i serwera
  - Obsługa błędów autoryzacji
  - Komunikaty o wygaśnięciu sesji
  
- **Interakcje użytkownika**
  - Przełączanie jednostek (GB/MB dla RAM)
  - Tooltips i komunikaty
  - Alerty i powiadomienia

### 2.2. Funkcjonalności poza Zakresem Testów
- Testy integracyjne z zewnętrznymi systemami monitorowania
- Testy obciążeniowe na dużą skalę (więcej niż jeden serwer)
- Testy bezpieczeństwa penetracyjne (pentesty)
- Testy dostępności (WCAG) - podstawowa weryfikacja

---

## 3. Typy Testów do Przeprowadzenia

### 3.1. Testy Jednostkowe (Unit Tests)

#### 3.1.1. Serwisy
- **MetricsService**
  - `calculateTimeRange()` - walidacja zakresów czasowych
  - `calculateCustomTimeRange()` - walidacja niestandardowych zakresów
  - `getMetrics()` - logika agregacji danych
  - `getLatest()` - pobieranie najnowszych metryk
  - `getStatistics()` - obliczanie statystyk
  
- **SshMetricsCollector**
  - `collectMetrics()` - logika retry i obsługa błędów
  - `collectCpuUsage()` - parsowanie `/proc/loadavg`
  - `collectRamUsage()` - parsowanie `/proc/meminfo`
  - `collectDiskUsage()` - parsowanie wyjścia `df`
  - `collectIoMetrics()` - parsowanie `/proc/diskstats`
  - `collectNetworkMetrics()` - parsowanie `/proc/net/dev`

#### 3.1.2. Kontrolery
- **MetricsController**
  - Walidacja parametrów zapytań
  - Obsługa błędów i wyjątków
  - Formatowanie odpowiedzi JSON
  
- **LoginController**
  - Walidacja formularza
  - Weryfikacja hasła
  - Zarządzanie sesjami
  
- **AuthController**
  - Walidacja JSON
  - Autoryzacja API
  - Wylogowanie

#### 3.1.3. Repozytoria
- **ServerMetricRepository**
  - `findByTimeRange()` - zapytania z zakresem czasowym
  - `findLatest()` - pobieranie najnowszego rekordu
  - `getStatistics()` - agregacje SQL
  - `getTimeRangeInfo()` - informacje o zakresie danych

#### 3.1.4. Komendy
- **CollectMetricsCommand**
  - Walidacja konfiguracji SSH
  - Obsługa błędów zbierania
  - Zapis do bazy danych
  
- **CleanupOldMetricsCommand**
  - Logika czyszczenia w batchach
  - Walidacja parametru retention-days
  - Obsługa błędów

#### 3.1.5. Encje
- **ServerMetric**
  - Gettery i settery
  - Walidacja wartości (CPU 0-100%, wartości nieujemne)

### 3.2. Testy Integracyjne (Integration Tests)

#### 3.2.1. Integracja Backend-Frontend
- Endpointy API z rzeczywistymi danymi z bazy
- Sesje i autoryzacja między kontrolerami
- Przekierowania i routing

#### 3.2.2. Integracja z Bazą Danych
- Zapis i odczyt metryk
- Zapytania z zakresami czasowymi
- Agregacje danych
- Czyszczenie starych rekordów

#### 3.2.3. Integracja SSH
- Połączenie z testowym serwerem SSH
- Zbieranie metryk z rzeczywistego serwera
- Obsługa błędów połączenia
- Retry logic

### 3.3. Testy Funkcjonalne (Functional Tests)

#### 3.3.1. Scenariusze Użytkownika
- **Scenariusz 1: Logowanie i dostęp do dashboardu**
  1. Użytkownik otwiera stronę logowania
  2. Wprowadza poprawne hasło
  3. Zostaje przekierowany do dashboardu
  4. Dashboard wyświetla wykresy metryk
  
- **Scenariusz 2: Przeglądanie metryk w różnych zakresach**
  1. Użytkownik jest zalogowany
  2. Wybiera zakres czasowy (1h, 6h, 24h, 7d, 30d)
  3. Wykresy aktualizują się z odpowiednimi danymi
  4. Agregacja działa poprawnie dla długich zakresów
  
- **Scenariusz 3: Odświeżanie danych**
  1. Użytkownik klika przycisk "Odśwież"
  2. Dane są ponownie pobierane z API
  3. Wykresy aktualizują się
  4. Spinner pokazuje stan ładowania
  
- **Scenariusz 4: Wylogowanie**
  1. Użytkownik klika "Wyloguj"
  2. Sesja zostaje unieważniona
  3. Użytkownik zostaje przekierowany do logowania

#### 3.3.2. Testy API
- Wszystkie endpointy API z różnymi parametrami
- Obsługa błędów (401, 400, 500)
- Walidacja odpowiedzi JSON
- Headers i cookies

### 3.4. Testy End-to-End (E2E Tests)

#### 3.4.1. Narzędzie: Cypress
- **Przepływ logowania**
  - Otwarcie strony logowania
  - Wypełnienie formularza
  - Przekierowanie do dashboardu
  
- **Interakcje z dashboardem**
  - Kliknięcie przycisków zakresu czasowego
  - Odświeżanie danych
  - Przełączanie jednostek RAM
  - Wylogowanie
  
- **Obsługa błędów**
  - Nieprawidłowe hasło
  - Wygaśnięcie sesji
  - Błąd połączenia z API
  - Brak danych do wyświetlenia

### 3.5. Testy Wydajnościowe (Performance Tests)

#### 3.5.1. Backend
- Czas odpowiedzi API dla różnych zakresów czasowych
- Wydajność zapytań do bazy danych (z indeksami)
- Czas zbierania metryk przez SSH
- Wydajność czyszczenia starych danych (batch processing)

#### 3.5.2. Frontend
- Czas ładowania dashboardu
- Wydajność renderowania wykresów Chart.js
- Czas aktualizacji danych
- Zużycie pamięci przy długotrwałym użytkowaniu

### 3.6. Testy Bezpieczeństwa (Security Tests)

#### 3.6.1. Autoryzacja
- Ochrona przed nieautoryzowanym dostępem
- Timeout sesji (30 minut)
- Ochrona CSRF
- Hashowanie haseł (hash_equals)

#### 3.6.2. Walidacja Danych
- SQL Injection (parametryzowane zapytania)
- XSS (escaping w Twig)
- Walidacja parametrów API

#### 3.6.3. Konfiguracja
- Bezpieczne przechowywanie haseł w `.env`
- Ochrona kluczy SSH (base64 encoding)

### 3.7. Testy Kompatybilności (Compatibility Tests)

#### 3.7.1. Przeglądarki
- Chrome (najnowsza wersja)
- Firefox (najnowsza wersja)
- Safari (najnowsza wersja)
- Edge (najnowsza wersja)

#### 3.7.2. Urządzenia
- Desktop (1920x1080, 1366x768)
- Tablet (768x1024)
- Mobile (375x667, 414x896)

#### 3.7.3. Systemy Operacyjne
- Linux (Ubuntu/Debian) - monitorowany serwer
- Różne wersje PHP (8.2, 8.3)
- MySQL 8.0+

### 3.8. Testy Regresyjne (Regression Tests)

- Weryfikacja, że nowe zmiany nie zepsuły istniejących funkcji
- Testy automatyczne uruchamiane przed każdym commitem/PR
- Smoke tests dla krytycznych ścieżek

---

## 4. Scenariusze Testowe dla Kluczowych Funkcjonalności

### 4.1. Zbieranie Metryk przez SSH

#### TC-SSH-001: Pomyślne zbieranie metryk
**Warunki wstępne**: 
- Skonfigurowane połączenie SSH w `.env`
- Dostęp do serwera Linux z `/proc` filesystem

**Kroki**:
1. Uruchom komendę `app:collect-metrics`
2. Sprawdź połączenie SSH
3. Zbierz wszystkie metryki (CPU, RAM, Disk, I/O, Network)
4. Zapisz do bazy danych

**Oczekiwany rezultat**: 
- Metryki zostały zebrane i zapisane w bazie
- Wszystkie wartości są poprawne (CPU 0-100%, pozostałe >= 0)
- Timestamp jest ustawiony na UTC

#### TC-SSH-002: Obsługa błędu połączenia SSH
**Warunki wstępne**: 
- Nieprawidłowe dane SSH w `.env`

**Kroki**:
1. Uruchom komendę z nieprawidłowymi danymi
2. Sprawdź retry logic (3 próby)
3. Sprawdź logowanie błędów

**Oczekiwany rezultat**: 
- Komenda zwraca błąd po 3 próbach
- Błędy są zalogowane w Monolog
- Exponential backoff działa poprawnie

#### TC-SSH-003: Parsowanie metryk z różnych formatów
**Warunki wstępne**: 
- Dostęp do serwera z różnymi wersjami Linux

**Kroki**:
1. Zbierz metryki z różnych systemów
2. Sprawdź parsowanie każdego typu metryki

**Oczekiwany rezultat**: 
- Wszystkie metryki są poprawnie sparsowane
- Obsługa edge cases (brak danych, nieprawidłowe formaty)

### 4.2. API Metrics

#### TC-API-001: Pobieranie metryk dla zakresu 24h
**Warunki wstępne**: 
- Zalogowany użytkownik
- Dane w bazie dla ostatnich 24 godzin

**Kroki**:
1. Wyślij GET `/api/metrics?range=24h`
2. Sprawdź odpowiedź JSON

**Oczekiwany rezultat**: 
- Status 200 OK
- JSON z polami `success: true`, `data: [...]`, `meta: {...}`
- Dane zawierają wszystkie wymagane pola
- Timestamps w formacie ISO 8601

#### TC-API-002: Agregacja dla zakresu 7d
**Warunki wstępne**: 
- Zalogowany użytkownik
- Dane w bazie dla ostatnich 7 dni

**Kroki**:
1. Wyślij GET `/api/metrics?range=7d`
2. Sprawdź agregację (10-minutowe buckety)

**Oczekiwany rezultat**: 
- `meta.aggregated: true`
- `meta.bucket_size_minutes: 10`
- Liczba punktów danych jest mniejsza niż liczba rekordów w bazie

#### TC-API-003: Niestandardowy zakres czasowy
**Warunki wstępne**: 
- Zalogowany użytkownik

**Kroki**:
1. Wyślij GET `/api/metrics?start=2024-01-01T00:00:00Z&end=2024-01-02T00:00:00Z`
2. Sprawdź walidację

**Oczekiwany rezultat**: 
- Status 200 OK dla prawidłowego zakresu
- Status 400 dla nieprawidłowego zakresu (start >= end, zakres > 30 dni)

#### TC-API-004: Brak autoryzacji
**Warunki wstępne**: 
- Niezalogowany użytkownik

**Kroki**:
1. Wyślij GET `/api/metrics` bez sesji
2. Sprawdź odpowiedź

**Oczekiwany rezultat**: 
- Status 401 lub przekierowanie do `/login`

### 4.3. Dashboard Frontend

#### TC-DASH-001: Wyświetlanie wykresów
**Warunki wstępne**: 
- Zalogowany użytkownik
- Dane w bazie

**Kroki**:
1. Otwórz `/dashboard`
2. Sprawdź renderowanie wszystkich wykresów (CPU, RAM, Disk, I/O, Network)

**Oczekiwany rezultat**: 
- Wszystkie wykresy są widoczne
- Wykresy używają Chart.js
- Dane są poprawnie wyświetlone
- Tooltips działają

#### TC-DASH-002: Przełączanie zakresów czasowych
**Warunki wstępne**: 
- Zalogowany użytkownik na dashboardzie

**Kroki**:
1. Kliknij przycisk "1h"
2. Sprawdź aktualizację wykresów
3. Kliknij "7d"
4. Sprawdź agregację

**Oczekiwany rezultat**: 
- Wykresy aktualizują się po kliknięciu
- Spinner pokazuje stan ładowania
- Przyciski są nieaktywne podczas ładowania
- Dane odpowiadają wybranemu zakresowi

#### TC-DASH-003: Przełączanie jednostek RAM
**Warunki wstępne**: 
- Zalogowany użytkownik na dashboardzie

**Kroki**:
1. Kliknij przycisk "MB" w sekcji RAM
2. Sprawdź aktualizację wykresu

**Oczekiwany rezultat**: 
- Wykres RAM aktualizuje się z wartościami w MB
- Oś Y pokazuje jednostki MB
- Tooltip pokazuje wartości w MB

#### TC-DASH-004: Obsługa braku danych
**Warunki wstępne**: 
- Zalogowany użytkownik
- Brak danych w bazie

**Kroki**:
1. Otwórz `/dashboard`
2. Sprawdź wyświetlanie komunikatu "Brak danych"

**Oczekiwany rezultat**: 
- Komunikat "Brak danych" jest widoczny
- Wykresy nie są renderowane
- Ikony ostrzeżeń są ukryte

#### TC-DASH-005: Alert o przestarzałych danych
**Warunki wstępne**: 
- Zalogowany użytkownik
- Ostatnia kolekcja metryk > 5 minut temu

**Kroki**:
1. Otwórz `/dashboard`
2. Sprawdź wyświetlanie alertu

**Oczekiwany rezultat**: 
- Alert warning jest widoczny
- Komunikat zawiera informację o ostatniej aktualizacji
- Ikony ostrzeżeń przy wykresach są widoczne

### 4.4. Logowanie i Autoryzacja

#### TC-AUTH-001: Pomyślne logowanie
**Warunki wstępne**: 
- Poprawne hasło w `.env`

**Kroki**:
1. Otwórz `/login`
2. Wprowadź poprawne hasło
3. Kliknij "Zaloguj"

**Oczekiwany rezultat**: 
- Przekierowanie do `/dashboard`
- Sesja jest ustawiona
- Cookie sesji jest ustawione

#### TC-AUTH-002: Nieprawidłowe hasło
**Warunki wstępne**: 
- Formularz logowania

**Kroki**:
1. Wprowadź nieprawidłowe hasło
2. Kliknij "Zaloguj"

**Oczekiwany rezultat**: 
- Komunikat błędu "Nieprawidłowe hasło"
- Brak przekierowania
- Sesja nie jest ustawiona

#### TC-AUTH-003: Timeout sesji
**Warunki wstępne**: 
- Zalogowany użytkownik
- Sesja starsza niż 30 minut

**Kroki**:
1. Poczekaj 30+ minut bez aktywności
2. Spróbuj odświeżyć dashboard

**Oczekiwany rezultat**: 
- Przekierowanie do `/login?expired=1`
- Komunikat o wygaśnięciu sesji
- Sesja jest unieważniona

#### TC-AUTH-004: Ochrona przed CSRF
**Warunki wstępne**: 
- Formularz logowania

**Kroki**:
1. Wyślij POST bez tokenu CSRF
2. Sprawdź odpowiedź

**Oczekiwany rezultat**: 
- Błąd walidacji CSRF
- Formularz nie jest przetwarzany

### 4.5. Czyszczenie Starych Danych

#### TC-CLEAN-001: Czyszczenie danych starszych niż 90 dni
**Warunki wstępne**: 
- Dane w bazie starsze niż 90 dni

**Kroki**:
1. Uruchom `app:cleanup-old-metrics`
2. Sprawdź usunięte rekordy

**Oczekiwany rezultat**: 
- Rekordy starsze niż 90 dni są usunięte
- Rekordy nowsze niż 90 dni pozostają
- Operacja jest zalogowana

#### TC-CLEAN-002: Czyszczenie z niestandardowym retention
**Warunki wstępne**: 
- Dane w bazie

**Kroki**:
1. Uruchom `app:cleanup-old-metrics --retention-days=60`
2. Sprawdź usunięte rekordy

**Oczekiwany rezultat**: 
- Rekordy starsze niż 60 dni są usunięte
- Walidacja parametru działa

#### TC-CLEAN-003: Batch processing
**Warunki wstępne**: 
- Duża ilość danych do usunięcia (>1000 rekordów)

**Kroki**:
1. Uruchom komendę czyszczenia
2. Sprawdź przetwarzanie w batchach

**Oczekiwany rezultat**: 
- Usuwanie odbywa się w batchach po 1000 rekordów
- Opóźnienia między batchami działają
- Baza danych nie jest zablokowana na długo

---

## 5. Środowisko Testowe

### 5.1. Środowisko Testowe Backend

#### 5.1.1. Wymagania
- **PHP**: 8.2 lub 8.3
- **MySQL**: 8.0+
- **Symfony**: 7.3
- **DDEV**: Dla lokalnego rozwoju
- **Testowy serwer SSH**: Linux (Ubuntu/Debian) z dostępem SSH

#### 5.1.2. Konfiguracja
- Osobna baza danych testowa (`server_monitor_test`)
- Osobny plik `.env.test` z konfiguracją testową
- Mock serwera SSH lub dedykowany testowy serwer
- Konfiguracja PHPUnit (`phpunit.dist.xml`)

#### 5.1.3. Dane Testowe
- Fixtures z przykładowymi metrykami
- Różne zakresy czasowe danych
- Edge cases (brak danych, nieprawidłowe wartości)

### 5.2. Środowisko Testowe Frontend

#### 5.2.1. Wymagania
- **Node.js**: Dla Cypress (jeśli używany)
- **Przeglądarki**: Chrome, Firefox, Safari, Edge
- **Narzędzia**: Cypress dla E2E

#### 5.2.2. Konfiguracja
- Cypress config (`cypress.config.js`)
- Test data dla mock API (jeśli potrzebne)
- Screenshots i videos dla failed tests

### 5.3. Środowisko CI/CD

#### 5.3.1. Wymagania
- GitHub Actions / GitLab CI / Jenkins
- Automatyczne uruchamianie testów przy PR
- Raportowanie wyników

#### 5.3.2. Konfiguracja
- Workflow dla testów jednostkowych
- Workflow dla testów integracyjnych
- Workflow dla testów E2E (opcjonalnie w CI)

---

## 6. Narzędzia do Testowania

### 6.1. Backend

#### 6.1.1. PHPUnit 12.4
- **Cel**: Testy jednostkowe i integracyjne
- **Konfiguracja**: `phpunit.dist.xml`
- **Użycie**: 
  ```bash
  ddev exec php bin/phpunit
  ```

#### 6.1.2. Symfony Test Client
- **Cel**: Testy funkcjonalne kontrolerów
- **Użycie**: Wbudowany w PHPUnit dla Symfony

#### 6.1.3. Doctrine Test Database
- **Cel**: Testy z bazą danych
- **Użycie**: Osobna baza testowa, transakcje rollback

#### 6.1.4. Monolog
- **Cel**: Weryfikacja logowania
- **Użycie**: Sprawdzanie logów w testach

### 6.2. Frontend

#### 6.2.1. Cypress
- **Cel**: Testy E2E
- **Konfiguracja**: `cypress.config.js`
- **Użycie**: 
  ```bash
  npx cypress run
  npx cypress open
  ```

#### 6.2.2. Chrome DevTools / Firefox DevTools
- **Cel**: Debugowanie i profilowanie
- **Użycie**: Manual testing, performance profiling

#### 6.2.3. BrowserStack / Sauce Labs (opcjonalnie)
- **Cel**: Testy kompatybilności na różnych przeglądarkach
- **Użycie**: Cloud-based testing

### 6.3. Narzędzia Wspomagające

#### 6.3.1. PHPStan / Psalm
- **Cel**: Analiza statyczna kodu
- **Użycie**: Wykrywanie potencjalnych błędów

#### 6.3.2. PHP CS Fixer
- **Cel**: Spójność stylu kodu
- **Użycie**: Automatyczne formatowanie

#### 6.3.3. Postman / Insomnia
- **Cel**: Testowanie API manualne
- **Użycie**: Tworzenie kolekcji requestów

---

## 7. Harmonogram Testów

### 7.1. Faza 1: Testy Jednostkowe (Tydzień 1-2)
- **Cel**: Pokrycie kodu testami jednostkowymi >80%
- **Zakres**: 
  - Serwisy (MetricsService, SshMetricsCollector)
  - Kontrolery
  - Repozytoria
  - Komendy
- **Kryterium**: Wszystkie testy jednostkowe przechodzą

### 7.2. Faza 2: Testy Integracyjne (Tydzień 2-3)
- **Cel**: Weryfikacja integracji komponentów
- **Zakres**:
  - Backend-Frontend
  - Baza danych
  - SSH
- **Kryterium**: Wszystkie testy integracyjne przechodzą

### 7.3. Faza 3: Testy Funkcjonalne (Tydzień 3-4)
- **Cel**: Weryfikacja scenariuszy użytkownika
- **Zakres**:
  - Przepływy użytkownika
  - API endpoints
  - Dashboard interactions
- **Kryterium**: Wszystkie scenariusze testowe przechodzą

### 7.4. Faza 4: Testy E2E (Tydzień 4)
- **Cel**: Weryfikacja pełnych przepływów
- **Zakres**:
  - Logowanie → Dashboard → Wylogowanie
  - Interakcje z wykresami
  - Obsługa błędów
- **Kryterium**: Wszystkie testy E2E przechodzą

### 7.5. Faza 5: Testy Wydajnościowe i Bezpieczeństwa (Tydzień 5)
- **Cel**: Weryfikacja wydajności i bezpieczeństwa
- **Zakres**:
  - Czas odpowiedzi API
  - Wydajność zapytań
  - Testy bezpieczeństwa
- **Kryterium**: Wszystkie metryki wydajnościowe są w akceptowalnych zakresach

### 7.6. Faza 6: Testy Kompatybilności (Tydzień 5-6)
- **Cel**: Weryfikacja działania na różnych platformach
- **Zakres**:
  - Przeglądarki
  - Urządzenia
  - Systemy operacyjne
- **Kryterium**: Aplikacja działa poprawnie na wszystkich testowanych platformach

### 7.7. Faza 7: Testy Regresyjne (Ongoing)
- **Cel**: Zapobieganie regresjom
- **Zakres**: 
  - Automatyczne testy przy każdym PR
  - Smoke tests przed release
- **Kryterium**: Wszystkie testy regresyjne przechodzą przed release

---

## 8. Kryteria Akceptacji Testów

### 8.1. Kryteria Ogólne
- **Pokrycie kodu**: Minimum 80% dla backendu, 70% dla frontendu
- **Wszystkie testy przechodzą**: 100% testów jednostkowych, integracyjnych i funkcjonalnych
- **Brak krytycznych błędów**: Zero błędów krytycznych (P0, P1)
- **Dokumentacja**: Wszystkie testy są udokumentowane

### 8.2. Kryteria Funkcjonalne
- **Zbieranie metryk**: Wszystkie metryki są zbierane poprawnie
- **API**: Wszystkie endpointy działają zgodnie ze specyfikacją
- **Dashboard**: Wszystkie wykresy wyświetlają się poprawnie
- **Autoryzacja**: Mechanizmy bezpieczeństwa działają poprawnie

### 8.3. Kryteria Wydajnościowe
- **Czas odpowiedzi API**: < 500ms dla zakresów do 24h, < 2s dla 7d/30d
- **Czas zbierania metryk**: < 10s (łącznie z retry)
- **Czas ładowania dashboardu**: < 3s
- **Renderowanie wykresów**: < 1s dla każdego wykresu

### 8.4. Kryteria Bezpieczeństwa
- **Autoryzacja**: Wszystkie chronione endpointy wymagają autoryzacji
- **CSRF**: Wszystkie formularze są chronione
- **Walidacja**: Wszystkie dane wejściowe są walidowane
- **Sesje**: Timeout działa poprawnie (30 minut)

### 8.5. Kryteria Kompatybilności
- **Przeglądarki**: Działa na Chrome, Firefox, Safari, Edge (najnowsze wersje)
- **Urządzenia**: Responsywność działa na mobile, tablet, desktop
- **Systemy**: Działa z PHP 8.2+, MySQL 8.0+

---

## 9. Role i Odpowiedzialności w Procesie Testowania

### 9.1. Zespół Rozwojowy
- **Rola**: Tworzenie testów jednostkowych i integracyjnych
- **Odpowiedzialności**:
  - Pisanie testów dla nowych funkcji
  - Utrzymanie istniejących testów
  - Naprawa testów po zmianach w kodzie
  - Code review testów

### 9.2. Zespół QA
- **Rola**: Testy funkcjonalne, E2E, kompatybilności
- **Odpowiedzialności**:
  - Tworzenie scenariuszy testowych
  - Wykonywanie testów manualnych
  - Testy E2E z Cypress
  - Testy kompatybilności
  - Raportowanie błędów

### 9.3. DevOps
- **Rola**: Konfiguracja środowisk testowych i CI/CD
- **Odpowiedzialności**:
  - Konfiguracja środowisk testowych
  - Automatyzacja testów w CI/CD
  - Monitorowanie wyników testów
  - Zarządzanie testowymi bazami danych

### 9.4. Product Owner
- **Rola**: Definiowanie kryteriów akceptacji
- **Odpowiedzialności**:
  - Definiowanie wymagań
  - Akceptacja funkcjonalności
  - Priorytetyzacja napraw błędów

---

## 10. Procedury Raportowania Błędów

### 10.1. Klasyfikacja Błędów

#### 10.1.1. Priorytety
- **P0 - Krytyczny**: Aplikacja nie działa, brak dostępu do funkcji krytycznych
  - Przykład: Nie można się zalogować, dashboard nie ładuje się
  - Czas naprawy: Natychmiast
  
- **P1 - Wysoki**: Ważna funkcjonalność nie działa poprawnie
  - Przykład: Wykresy nie wyświetlają się, metryki nie są zbierane
  - Czas naprawy: W ciągu 24 godzin
  
- **P2 - Średni**: Funkcjonalność działa, ale z problemami
  - Przykład: Błędy w wyświetlaniu danych, problemy z responsywnością
  - Czas naprawy: W ciągu tygodnia
  
- **P3 - Niski**: Drobne problemy, nie wpływają na funkcjonalność
  - Przykład: Błędy w UI, literówki, drobne problemy z UX
  - Czas naprawy: W następnej iteracji

#### 10.1.2. Typy Błędów
- **Functional**: Funkcjonalność nie działa zgodnie z wymaganiami
- **UI/UX**: Problemy z interfejsem użytkownika
- **Performance**: Problemy z wydajnością
- **Security**: Problemy bezpieczeństwa
- **Compatibility**: Problemy z kompatybilnością
- **Data**: Problemy z danymi

### 10.2. Format Raportu Błędu

#### 10.2.1. Wymagane Informacje
- **ID**: Unikalny identyfikator (np. BUG-001)
- **Tytuł**: Krótki opis błędu
- **Priorytet**: P0, P1, P2, P3
- **Typ**: Functional, UI/UX, Performance, Security, Compatibility, Data
- **Komponent**: Backend, Frontend, API, Database, SSH
- **Kroki reprodukcji**: Szczegółowe kroki do odtworzenia błędu
- **Oczekiwany rezultat**: Co powinno się wydarzyć
- **Rzeczywisty rezultat**: Co się faktycznie wydarzyło
- **Środowisko**: Przeglądarka, system operacyjny, wersja aplikacji
- **Załączniki**: Screenshots, logi, pliki
- **Status**: New, In Progress, Fixed, Verified, Closed
- **Przypisany do**: Osoba odpowiedzialna za naprawę

#### 10.2.2. Przykład Raportu
```
ID: BUG-001
Tytuł: Dashboard nie wyświetla wykresów po wybraniu zakresu 7d
Priorytet: P1
Typ: Functional
Komponent: Frontend
Kroki reprodukcji:
1. Zaloguj się do aplikacji
2. Przejdź do dashboardu
3. Kliknij przycisk "7d"
4. Sprawdź wyświetlanie wykresów

Oczekiwany rezultat: Wykresy aktualizują się z danymi dla 7 dni
Rzeczywisty rezultat: Wykresy nie wyświetlają się, komunikat błędu w konsoli
Środowisko: Chrome 120, macOS 14, wersja aplikacji 1.0.0
Załączniki: screenshot.png, console.log
Status: New
Przypisany do: [Developer Name]
```

### 10.3. Narzędzia do Śledzenia Błędów

#### 10.3.1. Rekomendowane Narzędzia
- **GitHub Issues**: Dla małych projektów
- **Jira**: Dla większych projektów
- **Linear**: Nowoczesne narzędzie do zarządzania
- **Trello**: Proste zarządzanie zadaniami

#### 10.3.2. Workflow
1. **Zgłoszenie**: QA/Developer zgłasza błąd
2. **Weryfikacja**: Sprawdzenie czy błąd jest reprodukowalny
3. **Priorytetyzacja**: Przypisanie priorytetu
4. **Przypisanie**: Przypisanie do developera
5. **Naprawa**: Developer naprawia błąd
6. **Weryfikacja**: QA weryfikuje naprawę
7. **Zamknięcie**: Błąd jest zamknięty po weryfikacji

### 10.4. Metryki i Raportowanie

#### 10.4.1. Metryki do Śledzenia
- **Liczba zgłoszonych błędów**: Całkowita liczba
- **Liczba otwartych błędów**: Błędy w statusie New/In Progress
- **Liczba naprawionych błędów**: Błędy w statusie Fixed
- **Średni czas naprawy**: Czas od zgłoszenia do naprawy
- **Wskaźnik naprawy**: Procent naprawionych błędów
- **Błędy według priorytetu**: Rozkład P0, P1, P2, P3
- **Błędy według komponentu**: Rozkład Backend, Frontend, API, etc.

#### 10.4.2. Raporty
- **Dzienny raport**: Błędy zgłoszone i naprawione w ciągu dnia
- **Tygodniowy raport**: Podsumowanie tygodnia
- **Raport przed release**: Wszystkie otwarte błędy przed wydaniem

---

## 11. Załączniki

### 11.1. Checklist Testów Przed Release

#### Backend
- [ ] Wszystkie testy jednostkowe przechodzą
- [ ] Wszystkie testy integracyjne przechodzą
- [ ] API działa poprawnie dla wszystkich endpointów
- [ ] Komendy konsolowe działają poprawnie
- [ ] Logowanie działa poprawnie
- [ ] Baza danych działa poprawnie
- [ ] SSH zbieranie metryk działa poprawnie

#### Frontend
- [ ] Wszystkie testy E2E przechodzą
- [ ] Dashboard wyświetla się poprawnie
- [ ] Wykresy renderują się poprawnie
- [ ] Logowanie działa poprawnie
- [ ] Responsywność działa na mobile/tablet/desktop
- [ ] Kompatybilność z przeglądarkami działa

#### Ogólne
- [ ] Brak krytycznych błędów (P0, P1)
- [ ] Dokumentacja jest aktualna
- [ ] Konfiguracja środowiska jest poprawna
- [ ] Backup i restore działa

### 11.2. Przykładowe Test Cases (Szczegółowe)

#### TC-DETAIL-001: Zbieranie metryk CPU z różną liczbą rdzeni
**Opis**: Weryfikacja poprawnego obliczania CPU usage dla serwerów z różną liczbą rdzeni

**Warunki wstępne**: 
- Dostęp do serwerów z 1, 2, 4, 8 rdzeniami

**Kroki**:
1. Zbierz metryki z serwera 1-rdzeniowego
2. Zbierz metryki z serwera 4-rdzeniowego
3. Sprawdź obliczenia CPU usage

**Oczekiwany rezultat**: 
- CPU usage jest poprawnie obliczane jako (load_avg / cores) * 100
- Wartości są w zakresie 0-100%

**Dane testowe**:
- Serwer 1-core: load_avg=0.5 → CPU=50%
- Serwer 4-core: load_avg=2.0 → CPU=50%

---

## 12. Podsumowanie

Niniejszy plan testów definiuje kompleksowe podejście do testowania aplikacji Server Monitor. Plan obejmuje wszystkie aspekty testowania od testów jednostkowych po testy E2E, wydajnościowe i bezpieczeństwa.

Kluczowe elementy planu:
- **Pokrycie**: Testy dla wszystkich głównych komponentów
- **Automatyzacja**: Maksymalna automatyzacja testów
- **Dokumentacja**: Szczegółowa dokumentacja scenariuszy
- **Metryki**: Śledzenie jakości i postępu
- **Procesy**: Zdefiniowane procedury i workflow

Plan testów powinien być aktualizowany wraz z rozwojem aplikacji i nowymi wymaganiami.

---

**Wersja dokumentu**: 1.0  
**Data utworzenia**: 2024-11-22  
**Ostatnia aktualizacja**: 2024-11-22  
**Autor**: Zespół QA

