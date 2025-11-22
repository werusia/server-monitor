# Architektura UI dla Server Monitor

## 1. Przegląd struktury UI

Server Monitor MVP składa się z minimalistycznej, jednostronicowej architektury interfejsu użytkownika z dwoma głównymi widokami: ekranem logowania i dashboardem monitorującym. Aplikacja wykorzystuje podejście "single-page dashboard" z sekcjami metryk ułożonymi wertykalnie, zapewniając użytkownikowi pełny przegląd stanu serwera na jednej stronie.

Architektura UI jest zaprojektowana z myślą o:
- **Prostocie**: Minimalna liczba widoków i przejść między nimi
- **Dostępności**: Pełna responsywność na mobile, tablet i desktop
- **Czytelności**: Hierarchiczna prezentacja metryk z wyraźnymi sekcjami
- **Niezawodności**: Wielopoziomowa obsługa błędów i stanów wyjątkowych
- **Wydajności**: Płynne aktualizacje wykresów bez pełnego przeładowania strony

Główne komponenty techniczne:
- **Framework UI**: Bootstrap 5 dla responsywności i komponentów
- **Wykresy**: Chart.js dla wszystkich wizualizacji metryk
- **Backend**: Symfony 7.3 z Security Component dla autoryzacji
- **Komunikacja**: AJAX/fetch dla asynchronicznego ładowania danych

## 2. Lista widoków

### 2.1. Widok logowania (`/login`)

**Ścieżka**: `/login`

**Główny cel**: Autoryzacja użytkownika przez hasło z `.env` przed uzyskaniem dostępu do dashboardu.

**Kluczowe informacje do wyświetlenia**:
- Formularz logowania z polem hasła
- Komunikaty błędów przy nieprawidłowym haśle
- Komunikat o wygaśnięciu sesji (jeśli użytkownik został przekierowany z powodu timeoutu)
- Tytuł aplikacji "Server Monitor" dla kontekstu

**Kluczowe komponenty widoku**:
- **Formularz logowania**: Pole typu `password` z walidacją po stronie serwera
- **Przycisk "Zaloguj"**: Submit formularza z obsługą CSRF token (Symfony Security)
- **Alert błędu**: Bootstrap `alert-danger` wyświetlany przy nieprawidłowym haśle
- **Alert informacyjny**: Bootstrap `alert-info` wyświetlany przy wygaśnięciu sesji z komunikatem "Sesja wygasła"
- **Layout**: Centrowany formularz na stronie z minimalnym designem

**UX, dostępność i względy bezpieczeństwa**:
- **UX**: Prosty, jednoetapowy proces logowania bez dodatkowych pól
- **Dostępność**: 
  - Semantyczny tag `<form>` z właściwymi labelami
  - ARIA labels dla pól formularza
  - Keyboard navigation (Enter do submit)
  - Focus management przy błędach
- **Bezpieczeństwo**:
  - CSRF protection przez Symfony Security
  - Hasło nie wyświetlane w polu (type="password")
  - Walidacja po stronie serwera
  - Przekierowanie do dashboardu tylko po udanej autoryzacji
  - Sesja Symfony z timeoutem 30 minut bezczynności

**Przepływ**:
- Użytkownik nieautoryzowany próbujący wejść na `/` lub `/dashboard` → przekierowanie do `/login`
- Po udanym logowaniu → przekierowanie do `/dashboard` (lub `/` jako domyślna)
- Po wygaśnięciu sesji → przekierowanie do `/login?expired=1` z komunikatem

### 2.2. Dashboard (`/dashboard` lub `/`)

**Ścieżka**: `/dashboard` (lub `/` jako domyślna po zalogowaniu)

**Główny cel**: Prezentacja wszystkich metryk serwera w formie interaktywnych wykresów z możliwością wyboru zakresu czasowego i ręcznego odświeżania danych.

**Kluczowe informacje do wyświetlenia**:
- **Sticky header**: Zawiera selektor zakresu czasowego (1h, 6h, 24h domyślnie, 7d, 30d), przycisk "Odśwież" i przycisk "Wyloguj"
- **Banner alertowy**: Wyświetlany pod headerem przy błędach SSH z informacją o ostatniej aktualizacji danych
- **Sekcje metryk** (wertykalnie, jedna pod drugą):
  - **CPU**: Wykres liniowy z wartościami w procentach (%)
  - **RAM**: Wykres liniowy z wartościami w GB (domyślnie) z toggle button do przełączania na MB
  - **Disk**: Wykres liniowy z wartościami w GB
  - **I/O**: Wykres liniowy z dwoma liniami (read bytes i write bytes) w jednym wykresie
  - **Network**: Wykres liniowy z dwoma liniami (sent bytes i received bytes) w jednym wykresie z jednostkami MB/s lub GB/h

**Kluczowe komponenty widoku**:

#### 2.2.1. Sticky Header
- **Pozycjonowanie**: `position: sticky; top: 0;` z `z-index` zapewniającym widoczność podczas scrollowania
- **Selektor zakresu czasowego**: Button group (Bootstrap) z opcjami: 1h, 6h, 24h (domyślnie aktywny), 7d, 30d
- **Przycisk "Odśwież"**: Po prawej stronie headeru, z możliwością wyłączenia (disabled) podczas ładowania
- **Przycisk "Wyloguj"**: W prawym górnym rogu headeru
- **Spinner ładowania**: Wyświetlany obok selektora zakresu czasowego podczas odświeżania danych
- **Tło**: Jasne tło z cieniem dla wyraźnego oddzielenia od treści

#### 2.2.2. Banner alertowy (warunkowy)
- **Banner alert-warning**: Wyświetlany pod headerem przy błędach SSH
- **Zawartość**: Komunikat o braku połączenia z serwerem + timestamp ostatniej udanej aktualizacji danych
- **Format timestamp**: Czytelny format ("2 minuty temu" lub "2024-01-15 14:30") w strefie czasowej przeglądarki
- **Banner alert-danger**: Wyświetlany przy błędach krytycznych (500, timeout) z komunikatem o problemie technicznym

#### 2.2.3. Sekcja metryki CPU
- **Nagłówek**: "Wykorzystanie CPU" z jednostką "%"
- **Wykres**: Chart.js line chart z jedną linią
- **Wysokość**: Dynamiczna (min. 300px, max. 500px na desktop, min. 250px na mobile)
- **Dane**: Wartości `cpu_usage` z zakresu 0.00-100.00
- **Oś Y**: Skalowana automatycznie (0-100% lub zakres danych)
- **Oś X**: Timestamps w formacie zależnym od zakresu czasowego (HH:mm dla krótkich, DD.MM HH:mm dla długich)
- **Ikona ostrzegawcza**: Wyświetlana obok nagłówka z tooltipem przy nieaktualnych danych (błąd SSH)
- **Komunikat "Brak danych"**: Wyświetlany zamiast wykresu gdy brak danych dla wybranego zakresu

#### 2.2.4. Sekcja metryki RAM
- **Nagłówek**: "Wykorzystanie RAM" z toggle button (GB/MB) obok nagłówka
- **Toggle button**: Przełącznik jednostek z aktywnym stanem (GB domyślnie)
- **Wykres**: Chart.js line chart z jedną linią
- **Wysokość**: Dynamiczna (min. 300px, max. 500px na desktop, min. 250px na mobile)
- **Dane**: Wartości `ram_usage` w GB, przeliczane na MB po stronie klienta przy przełączeniu
- **Oś Y**: Etykiety z jednostkami (GB lub MB) zależnie od wyboru
- **Oś X**: Timestamps w formacie zależnym od zakresu czasowego
- **Ikona ostrzegawcza**: Wyświetlana obok nagłówka z tooltipem przy nieaktualnych danych
- **Komunikat "Brak danych"**: Wyświetlany zamiast wykresu gdy brak danych

#### 2.2.5. Sekcja metryki Disk
- **Nagłówek**: "Wykorzystanie dysku" z jednostką "GB"
- **Wykres**: Chart.js line chart z jedną linią
- **Wysokość**: Dynamiczna (min. 300px, max. 500px na desktop, min. 250px na mobile)
- **Dane**: Wartości `disk_usage` w GB
- **Oś Y**: Etykiety w GB
- **Oś X**: Timestamps w formacie zależnym od zakresu czasowego
- **Ikona ostrzegawcza**: Wyświetlana obok nagłówka z tooltipem przy nieaktualnych danych
- **Komunikat "Brak danych"**: Wyświetlany zamiast wykresu gdy brak danych

#### 2.2.6. Sekcja metryki I/O
- **Nagłówek**: "Aktywność I/O" z jednostką "Bajty"
- **Wykres**: Chart.js line chart z dwoma liniami (read bytes i write bytes) w jednym wykresie
- **Wysokość**: Dynamiczna (min. 300px, max. 500px na desktop, min. 250px na mobile)
- **Dane**: 
  - Linia 1 (read): `io_read_bytes` - wartości kumulatywne, różnice obliczane w JavaScript dla wyświetlania
  - Linia 2 (write): `io_write_bytes` - wartości kumulatywne, różnice obliczane w JavaScript
- **Legenda**: Wyświetlana z dwoma kolorami (np. niebieski dla read, pomarańczowy dla write)
- **Oś Y**: Etykiety z automatycznym formatowaniem jednostek (KB, MB, GB) zależnie od wartości
- **Oś X**: Timestamps w formacie zależnym od zakresu czasowego
- **Tooltips**: Wyświetlają wartości read i write osobno przy hover
- **Ikona ostrzegawcza**: Wyświetlana obok nagłówka z tooltipem przy nieaktualnych danych
- **Komunikat "Brak danych"**: Wyświetlany zamiast wykresu gdy brak danych
- **Responsywność**: Na mobile wykresy mogą być układane jeden pod drugim w trybie kolumnowym (opcjonalnie)

#### 2.2.7. Sekcja metryki Network
- **Nagłówek**: "Ruch sieciowy" z jednostką "MB/s" lub "GB/h"
- **Wykres**: Chart.js line chart z dwoma liniami (sent bytes i received bytes) w jednym wykresie
- **Wysokość**: Dynamiczna (min. 300px, max. 500px na desktop, min. 250px na mobile)
- **Dane**: 
  - Linia 1 (sent): `network_sent_bytes` - wartości kumulatywne, różnice obliczane w JavaScript dla wyświetlania w MB/s lub GB/h
  - Linia 2 (received): `network_received_bytes` - wartości kumulatywne, różnice obliczane w JavaScript
- **Obliczenia**: Różnice między kolejnymi pomiarami dzielone przez czas (sekundy) i konwertowane na MB/s lub GB/h
- **Legenda**: Wyświetlana z dwoma kolorami (np. zielony dla sent, czerwony dla received)
- **Oś Y**: Etykiety w MB/s lub GB/h zależnie od zakresu czasowego i wartości
- **Oś X**: Timestamps w formacie zależnym od zakresu czasowego
- **Tooltips**: Wyświetlają wartości sent i received osobno przy hover
- **Ikona ostrzegawcza**: Wyświetlana obok nagłówka z tooltipem przy nieaktualnych danych
- **Komunikat "Brak danych"**: Wyświetlany zamiast wykresu gdy brak danych
- **Responsywność**: Na mobile wykresy mogą być układane jeden pod drugim w trybie kolumnowym (opcjonalnie)

**UX, dostępność i względy bezpieczeństwa**:
- **UX**: 
  - Sticky header umożliwia zmianę zakresu czasowego bez powrotu na górę strony
  - Płynne aktualizacje wykresów bez pełnego przeładowania strony
  - Czytelne komunikaty błędów i stanów wyjątkowych
  - Przełącznik jednostek RAM działa natychmiastowo bez opóźnień
- **Dostępność**: 
  - Semantyczne tagi HTML (`<section>`, `<header>`, `<nav>`)
  - ARIA labels dla przycisków i kontrolek
  - Keyboard navigation dla selektora zakresu czasowego (Tab, Enter, strzałki)
  - Kontrast kolorów zgodny z WCAG 2.1 AA
  - Tooltips dostępne przez keyboard (focus)
  - Alternatywne teksty dla ikon ostrzegawczych
- **Bezpieczeństwo**:
  - Wymagana autoryzacja przez Symfony Security (przekierowanie do `/login` przy braku sesji)
  - Timeout sesji 30 minut bezczynności z automatycznym przekierowaniem
  - Escapowanie danych w Twig (ochrona przed XSS)
  - CSRF protection dla formularzy (jeśli będą)
  - Przycisk "Wyloguj" umożliwia manualne zakończenie sesji

**Stany wyjątkowe**:
- **Brak danych**: Komunikat "Brak danych" w każdej sekcji osobno zamiast wykresu
- **Błąd SSH**: Banner alert-warning + ikony ostrzegawcze w sekcjach + wyświetlanie ostatniej znanej wartości z timestampem
- **Błąd krytyczny (500, timeout)**: Banner alert-danger na górze dashboardu z komunikatem o problemie technicznym
- **Ładowanie**: Disable przycisku "Odśwież", spinner, subtelny overlay na wykresach
- **Sesja wygasła**: Automatyczne przekierowanie do `/login` z komunikatem

## 3. Mapa podróży użytkownika

### 3.1. Przepływ logowania (główny przypadek użycia)

**Krok 1: Wejście do aplikacji**
- Użytkownik odwiedza `/` lub `/dashboard` bez aktywnej sesji
- Aplikacja wykrywa brak autoryzacji przez Symfony Security
- Automatyczne przekierowanie do `/login`

**Krok 2: Formularz logowania**
- Użytkownik widzi formularz logowania z polem hasła
- Wprowadza hasło z `.env`
- Kliknięcie "Zaloguj" lub naciśnięcie Enter

**Krok 3: Walidacja i autoryzacja**
- Symfony Security waliduje hasło
- Jeśli hasło nieprawidłowe: wyświetlenie alert-danger z komunikatem błędu, powrót do formularza
- Jeśli hasło prawidłowe: utworzenie sesji Symfony, przekierowanie do `/dashboard`

**Krok 4: Pierwsze ładowanie dashboardu**
- Dashboard ładuje się z domyślnym zakresem czasowym 24h
- Wyświetlanie skeleton screens lub spinnerów podczas ładowania
- Po otrzymaniu danych: renderowanie wszystkich wykresów Chart.js jednocześnie

### 3.2. Przepływ pracy w dashboardzie (główny przypadek użycia)

**Krok 1: Przeglądanie metryk**
- Użytkownik scrolluje przez sekcje metryk (CPU, RAM, Disk, I/O, Network)
- Wszystkie wykresy wyświetlają dane z zakresu 24h (domyślnie)
- Użytkownik może interakcjonować z wykresami (hover dla tooltips, zoom opcjonalnie)

**Krok 2: Zmiana zakresu czasowego**
- Użytkownik klika na przycisk zakresu czasowego w sticky header (np. "7d")
- Przycisk staje się aktywny (highlighted)
- Wyświetlanie spinnera obok selektora i overlay na wykresach
- Po otrzymaniu danych: aktualizacja wszystkich wykresów przez Chart.js `update()` z nowymi danymi
- Jeśli zakres >7d: dane są agregowane co 10 minut w SQL (automatycznie przez backend)

**Krok 3: Przełączanie jednostek RAM**
- Użytkownik klika toggle button "MB" obok nagłówka sekcji RAM
- Przycisk zmienia stan na aktywny (GB → MB)
- Wykres Chart.js aktualizuje się przez `update()` z nowymi wartościami i etykietami osi Y
- Proces natychmiastowy, bez opóźnień

**Krok 4: Odświeżanie danych**
- Użytkownik klika przycisk "Odśwież" w sticky header
- Przycisk staje się disabled z tekstem "Odświeżanie..."
- Wyświetlanie spinnera obok selektora zakresu czasowego
- Subtelny overlay na wykresach (opcjonalnie)
- Po otrzymaniu danych: płynna aktualizacja wszystkich wykresów przez Chart.js `update()`
- Przycisk "Odśwież" wraca do normalnego stanu

**Krok 5: Obsługa błędów SSH**
- Jeśli wystąpi błąd SSH podczas zbierania danych:
  - Banner alert-warning wyświetla się pod headerem z komunikatem o braku połączenia
  - Timestamp ostatniej udanej aktualizacji wyświetlany w bannerze
  - Ikony ostrzegawcze wyświetlane obok nagłówków sekcji metryk z tooltipami
  - Ostatnie znane wartości metryk pozostają widoczne na wykresach
  - Przycisk "Odśwież" pozostaje dostępny (użytkownik może spróbować ponownie)

**Krok 6: Wylogowanie**
- Użytkownik klika przycisk "Wyloguj" w prawym górnym rogu headeru
- Symfony Security niszczy sesję
- Przekierowanie do `/login` z komunikatem o wylogowaniu (opcjonalnie)

### 3.3. Przepływ wygaśnięcia sesji

**Krok 1: Timeout sesji**
- Użytkownik pozostaje bezczynny przez 30 minut
- Symfony Security wykrywa wygaśnięcie sesji

**Krok 2: Próba dostępu**
- Użytkownik próbuje wykonać akcję (kliknięcie "Odśwież", zmiana zakresu czasowego)
- Aplikacja wykrywa brak aktywnej sesji

**Krok 3: Przekierowanie**
- Automatyczne przekierowanie do `/login?expired=1`
- Wyświetlenie alert-info z komunikatem "Sesja wygasła"
- Użytkownik musi zalogować się ponownie

### 3.4. Przepływ obsługi błędów

**Scenariusz 1: Brak danych dla zakresu czasowego**
- Użytkownik wybiera zakres czasowy (np. 1h) dla którego nie ma danych
- W każdej sekcji metryk wyświetlany jest komunikat "Brak danych" zamiast wykresu
- Pozostałe sekcje z danymi działają normalnie
- Przycisk "Odśwież" i selektor zakresu czasowego pozostają dostępne

**Scenariusz 2: Błąd krytyczny (500, timeout)**
- Banner alert-danger wyświetla się na górze dashboardu pod headerem
- Komunikat informuje o problemie technicznym
- Wykresy pozostają z ostatnimi danymi (jeśli były wcześniej załadowane)
- Przycisk "Odśwież" pozostaje dostępny dla ponownej próby

**Scenariusz 3: Częściowy błąd (błąd SSH, ale dane historyczne dostępne)**
- Błąd SSH podczas zbierania nowych danych, ale stare dane są dostępne
- Banner alert-warning wyświetla się z informacją o braku połączenia
- Ikony ostrzegawcze w sekcjach metryk
- Wykresy wyświetlają ostatnie znane wartości z timestampem ostatniej aktualizacji
- Użytkownik rozumie, że dane mogą być nieaktualne

## 4. Układ i struktura nawigacji

### 4.1. Struktura nawigacji

Aplikacja wykorzystuje **minimalistyczną strukturę nawigacji** z dwoma głównymi punktami:

1. **Widok logowania** (`/login`): Punkt wejścia dla nieautoryzowanych użytkowników
2. **Dashboard** (`/dashboard` lub `/`): Główny widok aplikacji dostępny tylko po autoryzacji

**Brak tradycyjnego menu nawigacyjnego** - aplikacja MVP jest jednostronicowa, więc nie jest wymagana nawigacja między wieloma widokami.

### 4.2. Mechanizmy nawigacji

#### 4.2.1. Przekierowania automatyczne (Symfony Security)
- **Nieautoryzowany dostęp do `/` lub `/dashboard`** → przekierowanie do `/login`
- **Udane logowanie** → przekierowanie do `/dashboard` (lub `/` jako domyślna)
- **Wygaśnięcie sesji** → przekierowanie do `/login?expired=1`
- **Wylogowanie** → przekierowanie do `/login`

#### 4.2.2. Nawigacja w dashboardzie (sticky header)
Sticky header pełni rolę **nawigacji funkcjonalnej** wewnątrz dashboardu:

- **Selektor zakresu czasowego**: Umożliwia zmianę zakresu danych bez scrollowania
  - Pozycjonowanie: Lewa strona headeru
  - Format: Button group z opcjami 1h, 6h, 24h, 7d, 30d
  - Interakcja: Kliknięcie przycisku → aktualizacja wszystkich wykresów
  - Stan aktywny: Wizualne wyróżnienie wybranego zakresu

- **Przycisk "Odśwież"**: Umożliwia ręczne odświeżenie danych
  - Pozycjonowanie: Prawa strona headeru (obok przycisku "Wyloguj")
  - Interakcja: Kliknięcie → ponowne załadowanie danych dla aktualnego zakresu
  - Stan disabled: Podczas ładowania z tekstem "Odświeżanie..."

- **Przycisk "Wyloguj"**: Umożliwia zakończenie sesji
  - Pozycjonowanie: Prawy górny róg headeru
  - Interakcja: Kliknięcie → wylogowanie i przekierowanie do `/login`

#### 4.2.3. Nawigacja przez scrollowanie
- **Sekcje metryk**: Ułożone wertykalnie, dostępne przez scrollowanie strony
- **Sticky header**: Pozostaje widoczny podczas scrollowania, umożliwiając szybki dostęp do kontrolek
- **Smooth scroll**: Opcjonalnie dla lepszego UX (nie wymagane w MVP)

### 4.3. Dostępność nawigacji

- **Keyboard navigation**: 
  - Tab do przechodzenia między elementami (selektor zakresu, przycisk "Odśwież", przycisk "Wyloguj")
  - Enter/Space do aktywacji przycisków
  - Strzałki (lewo/prawo) do zmiany zakresu czasowego w button group (jeśli zaimplementowane)
- **ARIA labels**: Wszystkie przyciski i kontrolki mają odpowiednie etykiety dla czytników ekranu
- **Focus management**: Focus pozostaje na aktywnym elemencie po interakcji (np. po kliknięciu zakresu czasowego)

## 5. Kluczowe komponenty

### 5.1. Komponenty Bootstrap 5

#### 5.1.1. Button Group (Selektor zakresu czasowego)
- **Cel**: Grupa przycisków do wyboru zakresu czasowego (1h, 6h, 24h, 7d, 30d)
- **Lokalizacja**: Sticky header, lewa strona
- **Zachowanie**: Tylko jeden przycisk aktywny w danym momencie
- **Styl**: Bootstrap `btn-group` z `btn-outline-primary` (lub podobny)
- **Dostępność**: ARIA labels, keyboard navigation

#### 5.1.2. Alerts (Bannery błędów i ostrzeżeń)
- **Alert-warning**: Banner wyświetlany przy błędach SSH z informacją o ostatniej aktualizacji
- **Alert-danger**: Banner wyświetlany przy błędach krytycznych (500, timeout)
- **Alert-info**: Komunikat o wygaśnięciu sesji na ekranie logowania
- **Lokalizacja**: Pod sticky header w dashboardzie, w formularzu logowania
- **Zawartość**: Komunikaty tekstowe z możliwymi ikonami

#### 5.1.3. Spinner (Wskaźnik ładowania)
- **Cel**: Wizualna informacja o trwającym ładowaniu danych
- **Lokalizacja**: Obok selektora zakresu czasowego w sticky header
- **Styl**: Bootstrap `spinner-border` z odpowiednim rozmiarem

#### 5.1.4. Navbar/Header (Sticky header)
- **Cel**: Kontener dla kontrolek nawigacyjnych i funkcjonalnych
- **Lokalizacja**: Górna część dashboardu
- **Zachowanie**: `position: sticky; top: 0;` z odpowiednim `z-index`
- **Zawartość**: Selektor zakresu czasowego, przycisk "Odśwież", przycisk "Wyloguj", spinner
- **Styl**: Jasne tło z cieniem dla wyraźnego oddzielenia

### 5.2. Komponenty Chart.js

#### 5.2.1. Line Chart (Wykresy liniowe)
- **Cel**: Wizualizacja wszystkich metryk jako wykresy liniowe
- **Konfiguracja**:
  - `responsive: true` - automatyczne dostosowanie do rozmiaru kontenera
  - `maintainAspectRatio: false` - umożliwia dynamiczną wysokość
  - `animation: true` - płynne animacje przy aktualizacji
  - Tooltips z wartościami przy hover
  - Legenda dla wykresów z wieloma liniami (I/O, Network)
- **Lokalizacja**: W każdej sekcji metryk
- **Aktualizacja**: Metoda `update()` bez pełnego przeładowania strony

#### 5.2.2. Konfiguracja osi
- **Oś X (czasowa)**: 
  - Format zależny od zakresu czasowego (HH:mm dla krótkich, DD.MM HH:mm dla długich)
  - Konwersja z UTC do strefy czasowej przeglądarki przez JavaScript `Intl.DateTimeFormat`
- **Oś Y (wartości)**:
  - Automatyczne skalowanie zależnie od danych
  - Etykiety z jednostkami (%, GB, MB, KB/MB/GB dla bajtów, MB/s lub GB/h dla Network)

### 5.3. Komponenty niestandardowe

#### 5.3.1. Toggle Button (Przełącznik jednostek RAM)
- **Cel**: Przełączanie między jednostkami GB i MB dla metryki RAM
- **Lokalizacja**: Obok nagłówka sekcji RAM
- **Zachowanie**: 
  - Kliknięcie → przeliczanie wartości po stronie klienta (GB * 1024 = MB)
  - Wizualne wyróżnienie aktywnej jednostki
- **Styl**: Bootstrap `btn-group` z dwoma przyciskami (GB/MB) lub pojedynczy toggle button

#### 5.3.2. Ikona ostrzegawcza (Tooltip)
- **Cel**: Informowanie użytkownika o nieaktualnych danych w sekcji metryki
- **Lokalizacja**: Obok nagłówka każdej sekcji metryk
- **Zachowanie**: 
  - Wyświetlana tylko przy błędach SSH lub nieaktualnych danych
  - Tooltip z informacją o ostatniej aktualizacji przy hover/focus
- **Styl**: Bootstrap ikona (`bi-exclamation-triangle` lub podobna) z kolorem warning

#### 5.3.3. Overlay ładowania (opcjonalny)
- **Cel**: Subtelna wizualna informacja o trwającym ładowaniu danych
- **Lokalizacja**: Na wykresach podczas odświeżania
- **Zachowanie**: 
  - Wyświetlany podczas zapytań AJAX
  - Półprzezroczyste tło z możliwym spinnerem
  - Usuwany po zakończeniu ładowania
- **Styl**: CSS overlay z `opacity: 0.5` i odpowiednim tłem

### 5.4. Komponenty formatowania

#### 5.4.1. Formatowanie dat i czasów
- **Cel**: Konwersja timestampów z UTC do strefy czasowej przeglądarki
- **Implementacja**: JavaScript `Intl.DateTimeFormat` z automatyczną detekcją strefy czasowej
- **Formaty**:
  - Krótkie zakresy (1h, 6h): HH:mm
  - Średnie zakresy (24h): DD.MM HH:mm
  - Długie zakresy (7d, 30d): DD.MM HH:mm (z możliwym skróceniem dla czytelności)
  - Timestamp ostatniej aktualizacji: "2 minuty temu" lub "2024-01-15 14:30"

#### 5.4.2. Formatowanie jednostek
- **Bajty**: Automatyczne formatowanie do KB, MB, GB zależnie od wartości
- **Network**: Obliczanie różnic między pomiarami i konwersja na MB/s lub GB/h
- **RAM**: Przeliczanie GB ↔ MB po stronie klienta (GB * 1024 = MB)

### 5.5. Komponenty obsługi błędów

#### 5.5.1. Komunikat "Brak danych"
- **Cel**: Informowanie użytkownika o braku danych dla wybranej sekcji metryki
- **Lokalizacja**: W miejscu wykresu w sekcji metryki
- **Styl**: Wyśrodkowany tekst z odpowiednim kolorem (szary lub podobny)
- **Zachowanie**: Wyświetlany tylko gdy brak danych dla wybranego zakresu czasowego

#### 5.5.2. Wyświetlanie ostatniej znanej wartości
- **Cel**: Pokazanie ostatniej znanej wartości metryki przy błędach SSH
- **Lokalizacja**: Na wykresie lub obok wykresu z timestampem
- **Format**: "Ostatnia wartość: [wartość] z [timestamp]"
- **Zachowanie**: Wyświetlana tylko przy błędach SSH, gdy dostępne są stare dane

## 6. Mapowanie historyjek użytkownika do architektury UI

### US-011: Autoryzacja hasłem w dashboardzie
- **Widok**: Ekran logowania (`/login`)
- **Komponenty**: Formularz logowania z polem hasła, przycisk "Zaloguj", alert błędu
- **Przepływ**: Wprowadzenie hasła → walidacja → przekierowanie do dashboardu lub wyświetlenie błędu

### US-012: Wyświetlanie wykresu CPU w dashboardzie
- **Widok**: Dashboard, sekcja CPU
- **Komponenty**: Chart.js line chart z wartościami w procentach
- **Interakcje**: Hover dla tooltips, scrollowanie przez sekcję

### US-013: Wyświetlanie wykresu RAM w dashboardzie
- **Widok**: Dashboard, sekcja RAM
- **Komponenty**: Chart.js line chart, toggle button (GB/MB)
- **Interakcje**: Przełączanie jednostek, hover dla tooltips

### US-014: Wyświetlanie wykresu Disk w dashboardzie
- **Widok**: Dashboard, sekcja Disk
- **Komponenty**: Chart.js line chart z wartościami w GB
- **Interakcje**: Hover dla tooltips

### US-015: Wyświetlanie wykresów I/O w dashboardzie
- **Widok**: Dashboard, sekcja I/O
- **Komponenty**: Chart.js line chart z dwoma liniami (read/write)
- **Interakcje**: Hover dla tooltips z wartościami read i write, legenda

### US-016: Wyświetlanie wykresów Network w dashboardzie
- **Widok**: Dashboard, sekcja Network
- **Komponenty**: Chart.js line chart z dwoma liniami (sent/received), jednostki MB/s lub GB/h
- **Interakcje**: Hover dla tooltips z wartościami sent i received, legenda

### US-017: Wybór zakresu czasowego w dashboardzie
- **Widok**: Dashboard, sticky header
- **Komponenty**: Button group z opcjami 1h, 6h, 24h, 7d, 30d
- **Interakcje**: Kliknięcie przycisku → aktualizacja wszystkich wykresów

### US-018: Agregacja danych dla długich zakresów czasowych
- **Widok**: Dashboard (automatycznie w backendzie)
- **Komponenty**: Wykresy Chart.js z danymi zagregowanymi co 10 minut
- **Interakcje**: Przezroczyste dla użytkownika (automatyczna agregacja w SQL)

### US-019: Responsywny design dashboardu
- **Widok**: Wszystkie widoki
- **Komponenty**: Bootstrap 5 grid system, responsywne wykresy Chart.js
- **Breakpointy**: Mobile (<768px), Tablet (768px-1024px), Desktop (>1024px)

### US-020: Obsługa braku danych w dashboardzie
- **Widok**: Dashboard, każda sekcja metryki
- **Komponenty**: Komunikat "Brak danych" zamiast wykresu
- **Interakcje**: Wyświetlany tylko gdy brak danych dla wybranego zakresu

### US-021: Wyświetlanie ostatniej znanej wartości przy błędach SSH
- **Widok**: Dashboard, sekcje metryk
- **Komponenty**: Banner alert-warning, ikony ostrzegawcze, wyświetlanie ostatniej wartości z timestampem
- **Interakcje**: Tooltip przy ikonach ostrzegawczych

### US-022: Odświeżanie dashboardu na żądanie
- **Widok**: Dashboard, sticky header
- **Komponenty**: Przycisk "Odśwież", spinner, overlay (opcjonalny)
- **Interakcje**: Kliknięcie → ładowanie danych → aktualizacja wykresów

### US-023: Layout dashboardu z sekcjami wertykalnymi
- **Widok**: Dashboard
- **Komponenty**: Sekcje metryk ułożone wertykalnie (CPU, RAM, Disk, I/O, Network)
- **Interakcje**: Scrollowanie przez sekcje, sticky header pozostaje widoczny

## 7. Rozwiązanie punktów bólu użytkownika

### 7.1. Problem: Konieczność powrotu na górę strony do zmiany zakresu czasowego
**Rozwiązanie**: Sticky header z selektorem zakresu czasowego pozostaje widoczny podczas scrollowania, umożliwiając zmianę zakresu bez powrotu na górę.

### 7.2. Problem: Brak informacji o nieaktualnych danych
**Rozwiązanie**: Wielopoziomowa obsługa błędów SSH:
- Banner alert-warning z timestampem ostatniej aktualizacji
- Ikony ostrzegawcze w każdej sekcji z tooltipami
- Wyświetlanie ostatniej znanej wartości z timestampem

### 7.3. Problem: Trudność w porównywaniu read/write dla I/O i sent/received dla Network
**Rozwiązanie**: Jeden wykres z dwoma liniami w różnych kolorach z legendą, umożliwiający bezpośrednie porównanie wartości.

### 7.4. Problem: Nieczytelne jednostki dla dużych wartości bajtowych
**Rozwiązanie**: Automatyczne formatowanie jednostek (KB, MB, GB) zależnie od wartości, z odpowiednimi etykietami na osiach wykresów.

### 7.5. Problem: Brak informacji o stanie ładowania danych
**Rozwiązanie**: Wielowarstwowy feedback ładowania:
- Disable przycisku "Odśwież" z tekstem "Odświeżanie..."
- Spinner obok selektora zakresu czasowego
- Subtelny overlay na wykresach (opcjonalnie)

### 7.6. Problem: Trudność w interpretacji timestampów w UTC
**Rozwiązanie**: Automatyczna konwersja timestampów do strefy czasowej przeglądarki użytkownika przez JavaScript `Intl.DateTimeFormat`, bez konieczności konfiguracji.

### 7.7. Problem: Niezrozumiałe komunikaty błędów
**Rozwiązanie**: Hierarchiczna obsługa błędów:
- Globalny banner dla błędów krytycznych (500, timeout)
- Banner warning dla błędów SSH z konkretną informacją
- Komunikaty "Brak danych" per-sekcja dla częściowych problemów
- Czytelne komunikaty w języku polskim

### 7.8. Problem: Brak możliwości szybkiego przełączenia jednostek RAM
**Rozwiązanie**: Toggle button obok nagłówka sekcji RAM z natychmiastowym przeliczaniem po stronie klienta, bez opóźnień

### 7.9. Problem: Utrata kontekstu przy wygaśnięciu sesji
**Rozwiązanie**: Automatyczne przekierowanie do `/login` z komunikatem "Sesja wygasła", umożliwiające szybkie ponowne zalogowanie.

### 7.10. Problem: Trudność w korzystaniu z dashboardu na urządzeniach mobile
**Rozwiązanie**: Pełna responsywność Bootstrap 5:
- Wykresy dostosowują się do szerokości ekranu
- Dynamiczna wysokość wykresów (min. 250px na mobile)
- Wszystkie kontrolki dostępne i użyteczne na mobile
- Sticky header pozostaje funkcjonalny na małych ekranach

## 8. Przypadki brzegowe i stany wyjątkowe

### 8.1. Brak danych dla wybranego zakresu czasowego
- **Obsługa**: Komunikat "Brak danych" w każdej sekcji osobno
- **Zachowanie**: Pozostałe sekcje z danymi działają normalnie
- **Kontynuacja**: Użytkownik może zmienić zakres czasowy lub odświeżyć dane

### 8.2. Częściowy brak danych (niektóre sekcje mają dane, inne nie)
- **Obsługa**: Komunikat "Brak danych" tylko w sekcjach bez danych
- **Zachowanie**: Sekcje z danymi wyświetlają wykresy normalnie
- **Kontynuacja**: Dashboard pozostaje funkcjonalny

### 8.3. Błąd SSH podczas zbierania danych, ale stare dane dostępne
- **Obsługa**: 
  - Banner alert-warning z informacją o braku połączenia
  - Ikony ostrzegawcze w sekcjach metryk
  - Wyświetlanie ostatniej znanej wartości z timestampem
- **Zachowanie**: Wykresy pokazują ostatnie dostępne dane
- **Kontynuacja**: Przycisk "Odśwież" dostępny dla ponownej próby

### 8.4. Wygaśnięcie sesji podczas pracy w dashboardzie
- **Obsługa**: Automatyczne przekierowanie do `/login?expired=1` z komunikatem "Sesja wygasła"
- **Zachowanie**: Utrata stanu dashboardu (zakres czasowy, wybrane jednostki RAM)
- **Kontynuacja**: Użytkownik musi zalogować się ponownie

### 8.5. Równoczesne kliknięcie "Odśwież" i zmiana zakresu czasowego
- **Obsługa**: Ostatnia akcja (zmiana zakresu lub odświeżanie) anuluje poprzednią
- **Zachowanie**: Tylko jedno zapytanie AJAX w danym momencie
- **Kontynuacja**: Po zakończeniu ostatniego zapytania wykresy aktualizują się

### 8.6. Przełączanie jednostek RAM podczas ładowania danych
- **Obsługa**: Przełącznik pozostaje aktywny, ale aktualizacja wykresu następuje po zakończeniu ładowania
- **Zachowanie**: Stan przełącznika (GB/MB) jest zachowywany
- **Kontynuacja**: Po otrzymaniu danych wykres aktualizuje się z odpowiednimi jednostkami

### 8.7. Bardzo długi czas ładowania danych (np. zakres 30d z dużą ilością danych)
- **Obsługa**: Spinner i disable przycisku "Odśwież" pozostają widoczne
- **Zachowanie**: Użytkownik widzi, że dane są ładowane
- **Kontynuacja**: Po zakończeniu ładowania wykresy aktualizują się z zagregowanymi danymi

### 8.8. Brak wsparcia dla strefy czasowej w przeglądarce
- **Obsługa**: Fallback do UTC jeśli `Intl.DateTimeFormat` nie jest dostępne
- **Zachowanie**: Timestamps wyświetlane w UTC z odpowiednią etykietą
- **Kontynuacja**: Dashboard pozostaje funkcjonalny

### 8.9. Bardzo wąski ekran (mobile w orientacji pionowej)
- **Obsługa**: Wykresy dostosowują się do szerokości ekranu, min. wysokość 250px
- **Zachowanie**: Wszystkie kontrolki pozostają dostępne, sticky header może być zwijany (opcjonalnie)
- **Kontynuacja**: Pełna funkcjonalność na małych ekranach

## 10. Podsumowanie architektury

Architektura UI dla Server Monitor MVP została zaprojektowana z myślą o prostocie, dostępności i niezawodności. Aplikacja składa się z dwóch głównych widoków (logowanie i dashboard) z minimalistyczną nawigacją i jednostronicowym podejściem do prezentacji metryk.

Kluczowe decyzje architektoniczne:
- **Sticky header** umożliwia dostęp do kontrolek bez scrollowania
- **Wielopoziomowa obsługa błędów** informuje użytkownika bez przytłaczania
- **Responsywność** zapewnia pełną funkcjonalność na wszystkich urządzeniach
- **Płynne aktualizacje** wykresów bez pełnego przeładowania strony
- **Automatyczna lokalizacja** timestampów do strefy czasowej przeglądarki

Architektura jest zgodna z wymaganiami PRD, planem bazy danych i decyzjami z sesji planowania, zapewniając spójne i użyteczne doświadczenie użytkownika.

