# Plan implementacji widoku Dashboard

## 1. Przegląd

Dashboard jest głównym widokiem aplikacji Server Monitor, prezentującym metryki serwera Linux w formie interaktywnych wykresów Chart.js. Widok umożliwia użytkownikowi monitorowanie stanu serwera w czasie rzeczywistym poprzez wizualizację danych z zakresów czasowych: 1h, 6h, 24h (domyślnie), 7d, 30d. Dashboard składa się z sticky headeru z kontrolkami nawigacyjnymi oraz pięciu sekcji metryk ułożonych wertykalnie: CPU, RAM, Disk, I/O i Network. Wszystkie wykresy są responsywne i dostosowują się do rozmiaru ekranu, zapewniając pełną funkcjonalność na urządzeniach mobile, tablet i desktop.

## 2. Routing widoku

**Ścieżka**: `/dashboard`

**Kontroler**: Wymaga utworzenia nowego kontrolera `DashboardController` z metodą `index()` zwracającą widok Twig.

**Autoryzacja**: Wymagana autoryzacja przez Symfony Security. Nieautoryzowani użytkownicy są automatycznie przekierowywani do `/login`.

**Metoda routingu**: Atrybut `#[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]`

## 3. Struktura komponentów

Dashboard składa się z następujących głównych komponentów ułożonych hierarchicznie:

```
Dashboard (główny kontener)
├── StickyHeader
│   ├── TimeRangeSelector (button group)
│   ├── RefreshButton
│   ├── LogoutButton
│   └── LoadingSpinner (warunkowy)
├── AlertBanner (warunkowy)
│   ├── WarningBanner (błąd SSH)
│   └── DangerBanner (błąd krytyczny)
└── MetricsSections (kontener sekcji)
    ├── CPUMetricSection
    ├── RAMMetricSection
    ├── DiskMetricSection
    ├── IOMetricSection
    └── NetworkMetricSection
```

Każda sekcja metryki zawiera:
- Nagłówek z tytułem i jednostką
- Toggle button (tylko RAM) lub ikona ostrzegawcza (warunkowa)
- Kontener wykresu Chart.js lub komunikat "Brak danych"
- Tooltip z informacją o ostatniej aktualizacji (warunkowy)

## 4. Szczegóły komponentów

### StickyHeader

**Opis komponentu**: Nagłówek przyklejony do góry strony (`position: sticky; top: 0;`), zawierający wszystkie główne kontrolki nawigacyjne i funkcjonalne dashboardu. Pozostaje widoczny podczas scrollowania, umożliwiając szybki dostęp do kontrolek bez powrotu na górę strony.

**Główne elementy**:
- Kontener `<header>` z klasami Bootstrap `sticky-top`, `bg-white`, `shadow-sm`, `py-3`, `mb-3`
- Kontener flexbox (`d-flex`, `justify-content-between`, `align-items-center`) dla układu poziomego
- TimeRangeSelector (lewa strona)
- RefreshButton i LogoutButton (prawa strona)
- LoadingSpinner wyświetlany obok TimeRangeSelector podczas ładowania danych

**Obsługiwane zdarzenia**:
- `click` na przyciskach TimeRangeSelector → zmiana zakresu czasowego i aktualizacja wszystkich wykresów
- `click` na RefreshButton → ręczne odświeżenie danych dla aktualnego zakresu
- `click` na LogoutButton → wylogowanie i przekierowanie do `/login`
- `keydown` (Tab, Enter, strzałki) → keyboard navigation dla dostępności

**Obsługiwana walidacja**:
- TimeRangeSelector: Walidacja wybranego zakresu przed wysłaniem żądania API (musi być jednym z: `1h`, `6h`, `24h`, `7d`, `30d`)
- RefreshButton: Sprawdzenie czy nie trwa już ładowanie danych (zapobieganie wielokrotnym żądaniom)
- LogoutButton: Brak walidacji (bezpieczna operacja)

**Typy**:
- `selectedRange`: `'1h' | '6h' | '24h' | '7d' | '30d'` (domyślnie `'24h'`)
- `isLoading`: `boolean` (stan ładowania danych)
- `isRefreshing`: `boolean` (stan odświeżania)

**Propsy**:
- `initialRange`: `string` (opcjonalny, domyślnie `'24h'`)
- `onRangeChange`: `(range: string) => void` (callback przy zmianie zakresu)
- `onRefresh`: `() => void` (callback przy kliknięciu odśwież)
- `onLogout`: `() => void` (callback przy kliknięciu wyloguj)

### TimeRangeSelector

**Opis komponentu**: Grupa przycisków Bootstrap (`btn-group`) umożliwiająca wybór zakresu czasowego danych wyświetlanych na wykresach. Tylko jeden przycisk może być aktywny w danym momencie.

**Główne elementy**:
- Kontener `<div>` z klasą Bootstrap `btn-group` i atrybutem `role="group"`
- Pięć przycisków (`<button>`) z klasami `btn`, `btn-outline-primary` dla opcji: `1h`, `6h`, `24h`, `7d`, `30d`
- Aktywny przycisk ma klasę `active` i `btn-primary`
- Każdy przycisk ma atrybut `aria-label` dla dostępności

**Obsługiwane zdarzenia**:
- `click` na przycisku → zmiana aktywnego zakresu, wywołanie callbacku `onRangeChange`
- `keydown` (Enter, Space) → aktywacja przycisku przez klawiaturę
- `keydown` (strzałki lewo/prawo) → nawigacja między przyciskami (opcjonalnie)

**Obsługiwana walidacja**:
- Sprawdzenie czy wybrany zakres jest poprawny przed wysłaniem żądania API
- Zapobieganie wielokrotnym kliknięciom podczas ładowania (disable przycisków)

**Typy**:
- `selectedRange`: `'1h' | '6h' | '24h' | '7d' | '30d'`
- `disabled`: `boolean` (wyłączenie podczas ładowania)

**Propsy**:
- `value`: `string` (aktualnie wybrany zakres)
- `onChange`: `(range: string) => void` (callback przy zmianie)
- `disabled`: `boolean` (czy kontrolka jest wyłączona)

### RefreshButton

**Opis komponentu**: Przycisk umożliwiający ręczne odświeżenie danych dla aktualnie wybranego zakresu czasowego. Podczas ładowania przycisk jest wyłączony i wyświetla tekst "Odświeżanie...".

**Główne elementy**:
- Przycisk `<button>` z klasami Bootstrap `btn`, `btn-primary`
- Tekst "Odśwież" (domyślnie) lub "Odświeżanie..." (podczas ładowania)
- Atrybut `disabled` podczas ładowania
- Ikona spinnera (opcjonalnie) obok tekstu podczas ładowania

**Obsługiwane zdarzenia**:
- `click` → wywołanie callbacku `onRefresh`, wyłączenie przycisku, rozpoczęcie ładowania
- `keydown` (Enter, Space) → aktywacja przez klawiaturę

**Obsługiwana walidacja**:
- Sprawdzenie czy nie trwa już ładowanie danych (zapobieganie wielokrotnym żądaniom)
- Sprawdzenie czy istnieje aktywna sesja (obsługa wygaśnięcia sesji)

**Typy**:
- `isLoading`: `boolean` (stan ładowania)
- `isDisabled`: `boolean` (czy przycisk jest wyłączony)

**Propsy**:
- `onClick`: `() => void` (callback przy kliknięciu)
- `isLoading`: `boolean` (stan ładowania)
- `disabled`: `boolean` (czy przycisk jest wyłączony)

### LogoutButton

**Opis komponentu**: Przycisk umożliwiający wylogowanie użytkownika i zakończenie sesji. Po kliknięciu następuje przekierowanie do strony logowania.

**Główne elementy**:
- Przycisk `<button>` z klasami Bootstrap `btn`, `btn-outline-secondary`
- Tekst "Wyloguj"
- Ikona logout (opcjonalnie)
- Atrybut `aria-label` dla dostępności

**Obsługiwane zdarzenia**:
- `click` → wywołanie callbacku `onLogout`, wysłanie żądania POST do `/api/logout`, przekierowanie do `/login`
- `keydown` (Enter, Space) → aktywacja przez klawiaturę

**Obsługiwana walidacja**:
- Brak walidacji (bezpieczna operacja, zawsze dostępna)

**Typy**:
- Brak (prosty przycisk bez stanu)

**Propsy**:
- `onClick`: `() => void` (callback przy kliknięciu)

### LoadingSpinner

**Opis komponentu**: Wskaźnik wizualny informujący o trwającym ładowaniu danych. Wyświetlany obok TimeRangeSelector podczas pobierania danych z API.

**Główne elementy**:
- Kontener `<div>` z klasami Bootstrap `spinner-border`, `spinner-border-sm`, `text-primary`
- Atrybut `role="status"` i `<span class="visually-hidden">` z tekstem "Ładowanie..." dla dostępności

**Obsługiwane zdarzenia**:
- Brak (komponent wizualny bez interakcji)

**Obsługiwana walidacja**:
- Wyświetlany tylko gdy `isLoading === true`

**Typy**:
- `isVisible`: `boolean` (czy spinner jest widoczny)

**Propsy**:
- `isLoading`: `boolean` (stan ładowania)

### AlertBanner

**Opis komponentu**: Banner alertowy wyświetlany pod sticky headerem, informujący użytkownika o błędach SSH lub problemach krytycznych. Może wyświetlać dwa typy alertów: warning (błąd SSH) lub danger (błąd krytyczny).

**Główne elementy**:
- Kontener `<div>` z klasami Bootstrap `alert`, `alert-warning` (dla błędów SSH) lub `alert-danger` (dla błędów krytycznych)
- Ikona ostrzegawcza (opcjonalnie)
- Tekst komunikatu
- Timestamp ostatniej udanej aktualizacji (tylko dla alert-warning)
- Przycisk zamknięcia (opcjonalnie, `alert-dismissible`)

**Obsługiwane zdarzenia**:
- `click` na przycisku zamknięcia → ukrycie bannera (opcjonalnie)
- Automatyczne ukrycie po określonym czasie (opcjonalnie)

**Obsługiwana walidacja**:
- Wyświetlany tylko gdy `hasError === true` lub `hasSSHError === true`
- Sprawdzenie typu błędu (SSH vs krytyczny) dla odpowiedniego stylu alertu
- Walidacja formatu timestampu przed wyświetleniem

**Typy**:
- `alertType`: `'warning' | 'danger' | null`
- `message`: `string | null`
- `lastUpdateTimestamp`: `string | null` (ISO 8601 UTC)
- `isVisible`: `boolean`

**Propsy**:
- `type`: `'warning' | 'danger'`
- `message`: `string`
- `lastUpdateTimestamp`: `string | null` (opcjonalny)
- `onDismiss`: `() => void` (opcjonalny callback przy zamknięciu)

### CPUMetricSection

**Opis komponentu**: Sekcja metryki CPU wyświetlająca wykres liniowy Chart.js z historią wykorzystania procesora w procentach (0-100%). Zawiera nagłówek, wykres lub komunikat "Brak danych", oraz opcjonalną ikonę ostrzegawczą przy nieaktualnych danych.

**Główne elementy**:
- Kontener `<section>` z klasami Bootstrap `mb-5`
- Nagłówek `<h2>` z tekstem "Wykorzystanie CPU" i jednostką "%"
- Ikona ostrzegawcza (warunkowa) z tooltipem obok nagłówka
- Kontener wykresu `<canvas>` z ID `cpu-chart` i wysokością min. 300px (desktop) / 250px (mobile)
- Komunikat "Brak danych" (warunkowy) zamiast wykresu
- Instancja Chart.js line chart

**Obsługiwane zdarzenia**:
- Inicjalizacja wykresu Chart.js przy pierwszym renderowaniu
- Aktualizacja danych wykresu przez metodę `update()` przy zmianie zakresu czasowego
- Hover na wykresie → wyświetlenie tooltipa z wartością CPU i timestampem
- Kliknięcie na ikonie ostrzegawczej → wyświetlenie tooltipa z informacją o ostatniej aktualizacji

**Obsługiwana walidacja**:
- Sprawdzenie czy dane są dostępne przed renderowaniem wykresu
- Walidacja wartości CPU (0-100%) przed wyświetleniem
- Sprawdzenie czy dane są aktualne (porównanie timestampu z aktualnym czasem)
- Walidacja formatu timestampów z API (ISO 8601 UTC)

**Typy**:
- `data`: `Array<{timestamp: string, cpu_usage: number}> | null`
- `isLoading`: `boolean`
- `hasData`: `boolean`
- `isStale`: `boolean` (czy dane są nieaktualne)
- `lastUpdateTimestamp`: `string | null`

**Propsy**:
- `data`: `Array<MetricData> | null` (dane z API)
- `isLoading`: `boolean` (stan ładowania)
- `isStale`: `boolean` (czy dane są nieaktualne)
- `lastUpdateTimestamp`: `string | null` (timestamp ostatniej aktualizacji)
- `timeRange`: `string` (aktualny zakres czasowy dla formatowania osi X)

### RAMMetricSection

**Opis komponentu**: Sekcja metryki RAM wyświetlająca wykres liniowy Chart.js z historią wykorzystania pamięci. Zawiera toggle button do przełączania jednostek między GB (domyślnie) a MB, nagłówek, wykres lub komunikat "Brak danych", oraz opcjonalną ikonę ostrzegawczą.

**Główne elementy**:
- Kontener `<section>` z klasami Bootstrap `mb-5`
- Nagłówek `<h2>` z tekstem "Wykorzystanie RAM"
- Toggle button (`btn-group`) z opcjami GB/MB obok nagłówka
- Ikona ostrzegawcza (warunkowa) z tooltipem
- Kontener wykresu `<canvas>` z ID `ram-chart`
- Komunikat "Brak danych" (warunkowy)
- Instancja Chart.js line chart

**Obsługiwane zdarzenia**:
- `click` na toggle button → przełączenie jednostek (GB ↔ MB), przeliczenie wartości, aktualizacja wykresu
- Inicjalizacja wykresu z danymi w GB
- Aktualizacja danych wykresu przy zmianie zakresu czasowego
- Aktualizacja etykiet osi Y przy przełączeniu jednostek
- Hover na wykresie → tooltip z wartością w aktualnej jednostce

**Obsługiwana walidacja**:
- Sprawdzenie czy dane są dostępne
- Walidacja wartości RAM (nieujemne liczby)
- Sprawdzenie czy przełącznik jednostek działa poprawnie (GB * 1024 = MB)
- Walidacja formatu danych z API

**Typy**:
- `data`: `Array<{timestamp: string, ram_usage: number}> | null` (wartości w GB)
- `unit`: `'GB' | 'MB'` (domyślnie `'GB'`)
- `isLoading`: `boolean`
- `hasData`: `boolean`
- `isStale`: `boolean`

**Propsy**:
- `data`: `Array<MetricData> | null` (wartości w GB z API)
- `isLoading`: `boolean`
- `isStale`: `boolean`
- `lastUpdateTimestamp`: `string | null`
- `timeRange`: `string`
- `initialUnit`: `'GB' | 'MB'` (opcjonalny, domyślnie `'GB'`)

### DiskMetricSection

**Opis komponentu**: Sekcja metryki Disk wyświetlająca wykres liniowy Chart.js z historią wykorzystania dysku w GB. Zawiera nagłówek, wykres lub komunikat "Brak danych", oraz opcjonalną ikonę ostrzegawczą.

**Główne elementy**:
- Kontener `<section>` z klasami Bootstrap `mb-5`
- Nagłówek `<h2>` z tekstem "Wykorzystanie dysku" i jednostką "GB"
- Ikona ostrzegawcza (warunkowa) z tooltipem
- Kontener wykresu `<canvas>` z ID `disk-chart`
- Komunikat "Brak danych" (warunkowy)
- Instancja Chart.js line chart

**Obsługiwane zdarzenia**:
- Inicjalizacja wykresu przy pierwszym renderowaniu
- Aktualizacja danych wykresu przy zmianie zakresu czasowego
- Hover na wykresie → tooltip z wartością w GB i timestampem

**Obsługiwana walidacja**:
- Sprawdzenie czy dane są dostępne
- Walidacja wartości Disk (nieujemne liczby)
- Walidacja formatu danych z API

**Typy**:
- `data`: `Array<{timestamp: string, disk_usage: number}> | null` (wartości w GB)
- `isLoading`: `boolean`
- `hasData`: `boolean`
- `isStale`: `boolean`

**Propsy**:
- `data`: `Array<MetricData> | null`
- `isLoading`: `boolean`
- `isStale`: `boolean`
- `lastUpdateTimestamp`: `string | null`
- `timeRange`: `string`

### IOMetricSection

**Opis komponentu**: Sekcja metryki I/O wyświetlająca wykres liniowy Chart.js z dwoma liniami: read bytes i write bytes. Wartości są kumulatywne, więc różnice są obliczane po stronie klienta dla wyświetlania. Zawiera nagłówek, wykres z legendą, lub komunikat "Brak danych", oraz opcjonalną ikonę ostrzegawczą.

**Główne elementy**:
- Kontener `<section>` z klasami Bootstrap `mb-5`
- Nagłówek `<h2>` z tekstem "Aktywność I/O" i jednostką "Bajty"
- Ikona ostrzegawcza (warunkowa) z tooltipem
- Kontener wykresu `<canvas>` z ID `io-chart`
- Komunikat "Brak danych" (warunkowy)
- Instancja Chart.js line chart z dwoma datasetami (read i write)
- Legenda wykresu z dwoma kolorami

**Obsługiwane zdarzenia**:
- Inicjalizacja wykresu z dwoma liniami (read: niebieski, write: pomarańczowy)
- Obliczanie różnic między kolejnymi pomiarami dla każdej linii
- Aktualizacja danych wykresu przy zmianie zakresu czasowego
- Hover na wykresie → tooltip z wartościami read i write osobno
- Formatowanie wartości na osi Y z automatycznym doborem jednostek (KB, MB, GB)

**Obsługiwana walidacja**:
- Sprawdzenie czy dane są dostępne
- Walidacja wartości I/O (nieujemne liczby całkowite)
- Sprawdzenie czy wartości są kumulatywne (każda kolejna wartość >= poprzedniej)
- Walidacja obliczeń różnic (zapobieganie ujemnym różnicom przy resetach liczników)

**Typy**:
- `data`: `Array<{timestamp: string, io_read_bytes: number, io_write_bytes: number}> | null` (wartości kumulatywne)
- `processedData`: `Array<{timestamp: string, read_diff: number, write_diff: number}> | null` (wartości różnicowe)
- `isLoading`: `boolean`
- `hasData`: `boolean`
- `isStale`: `boolean`

**Propsy**:
- `data`: `Array<MetricData> | null` (wartości kumulatywne z API)
- `isLoading`: `boolean`
- `isStale`: `boolean`
- `lastUpdateTimestamp`: `string | null`
- `timeRange`: `string`

### NetworkMetricSection

**Opis komponentu**: Sekcja metryki Network wyświetlająca wykres liniowy Chart.js z dwoma liniami: sent bytes i received bytes. Wartości są kumulatywne, więc różnice są obliczane po stronie klienta i konwertowane na MB/s lub GB/h. Zawiera nagłówek, wykres z legendą, lub komunikat "Brak danych", oraz opcjonalną ikonę ostrzegawczą.

**Główne elementy**:
- Kontener `<section>` z klasami Bootstrap `mb-5`
- Nagłówek `<h2>` z tekstem "Ruch sieciowy" i jednostką "MB/s" lub "GB/h"
- Ikona ostrzegawcza (warunkowa) z tooltipem
- Kontener wykresu `<canvas>` z ID `network-chart`
- Komunikat "Brak danych" (warunkowy)
- Instancja Chart.js line chart z dwoma datasetami (sent i received)
- Legenda wykresu z dwoma kolorami (sent: zielony, received: czerwony)

**Obsługiwane zdarzenia**:
- Inicjalizacja wykresu z dwoma liniami
- Obliczanie różnic między kolejnymi pomiarami
- Konwersja różnic na MB/s lub GB/h (dzielenie przez czas w sekundach)
- Aktualizacja danych wykresu przy zmianie zakresu czasowego
- Hover na wykresie → tooltip z wartościami sent i received osobno
- Automatyczny dobór jednostek (MB/s dla krótkich zakresów, GB/h dla długich)

**Obsługiwana walidacja**:
- Sprawdzenie czy dane są dostępne
- Walidacja wartości Network (nieujemne liczby całkowite)
- Sprawdzenie czy wartości są kumulatywne
- Walidacja obliczeń różnic i konwersji jednostek
- Sprawdzenie czy czas między pomiarami jest poprawny (zapobieganie dzieleniu przez zero)

**Typy**:
- `data`: `Array<{timestamp: string, network_sent_bytes: number, network_received_bytes: number}> | null` (wartości kumulatywne)
- `processedData`: `Array<{timestamp: string, sent_rate: number, received_rate: number, unit: 'MB/s' | 'GB/h'}> | null` (wartości przeliczone)
- `isLoading`: `boolean`
- `hasData`: `boolean`
- `isStale`: `boolean`
- `unit`: `'MB/s' | 'GB/h'` (zależnie od zakresu czasowego)

**Propsy**:
- `data`: `Array<MetricData> | null` (wartości kumulatywne z API)
- `isLoading`: `boolean`
- `isStale`: `boolean`
- `lastUpdateTimestamp`: `string | null`
- `timeRange`: `string` (wpływa na dobór jednostek)

## 5. Integracja API

### 5.1. Endpoint GET /api/metrics

**Typ żądania**: `GET`

**URL**: `/api/metrics?range={range}`

**Parametry zapytania**:
- `range` (wymagany): `'1h' | '6h' | '24h' | '7d' | '30d'` (domyślnie `'24h'`)

**Nagłówki**:
- `Content-Type: application/json`
- Sesja cookie (automatycznie przez przeglądarkę)

**Odpowiedź sukcesu (200 OK)**:
```typescript
{
  success: true,
  data: Array<{
    id: number | null,
    timestamp: string, // ISO 8601 UTC
    cpu_usage: number, // 0.00-100.00
    ram_usage: number, // GB
    disk_usage: number, // GB
    io_read_bytes: number, // kumulatywne
    io_write_bytes: number, // kumulatywne
    network_sent_bytes: number, // kumulatywne
    network_received_bytes: number // kumulatywne
  }>,
  meta: {
    range: string,
    count: number,
    aggregated: boolean,
    start_time: string, // ISO 8601 UTC
    end_time: string, // ISO 8601 UTC
    bucket_size_minutes?: number // tylko dla aggregated=true
  }
}
```

**Odpowiedź błędu (400 Bad Request)**:
```typescript
{
  success: false,
  error: string // "Invalid range. Must be one of: 1h, 6h, 24h, 7d, 30d"
}
```

**Odpowiedź błędu (401 Unauthorized)**:
```typescript
{
  success: false,
  error: "Authentication required"
}
```

**Odpowiedź błędu (500 Internal Server Error)**:
```typescript
{
  success: false,
  error: "Internal server error"
}
```

**Obsługa w komponencie**:
- Wywołanie `fetch('/api/metrics?range=' + selectedRange)` przy inicjalizacji i zmianie zakresu
- Parsowanie odpowiedzi JSON
- Walidacja struktury odpowiedzi przed użyciem danych
- Obsługa błędów HTTP (401 → przekierowanie do `/login`, 400 → komunikat błędu, 500 → banner alert-danger)
- Aktualizacja stanu `isLoading` przed i po żądaniu
- Zapis danych do stanu komponentu dla wszystkich sekcji metryk

### 5.2. Endpoint GET /api/metrics/latest

**Typ żądania**: `GET`

**URL**: `/api/metrics/latest`

**Nagłówki**: Sesja cookie

**Odpowiedź sukcesu (200 OK)**:
```typescript
{
  success: true,
  data: {
    id: number,
    timestamp: string, // ISO 8601 UTC
    cpu_usage: number,
    ram_usage: number,
    disk_usage: number,
    io_read_bytes: number,
    io_write_bytes: number,
    network_sent_bytes: number,
    network_received_bytes: number
  } | null // null jeśli brak danych
}
```

**Obsługa w komponencie**:
- Wywołanie przy wykryciu błędu SSH lub nieaktualnych danych
- Użycie danych do wyświetlenia ostatniej znanej wartości w sekcjach metryk
- Wyświetlenie timestampu ostatniej aktualizacji w bannerze alert-warning

### 5.3. Endpoint GET /api/status

**Typ żądania**: `GET`

**URL**: `/api/status`

**Nagłówki**: Sesja cookie

**Odpowiedź sukcesu (200 OK)**:
```typescript
{
  success: true,
  data: {
    last_collection: string | null, // ISO 8601 UTC
    last_collection_status: 'success' | 'unknown',
    ssh_connected: boolean,
    data_available: boolean,
    oldest_record: string | null, // ISO 8601 UTC
    newest_record: string | null, // ISO 8601 UTC
    total_records: number
  }
}
```

**Obsługa w komponencie**:
- Wywołanie przy inicjalizacji dashboardu i okresowo (opcjonalnie)
- Użycie `ssh_connected` do określenia czy wyświetlić banner alert-warning
- Użycie `last_collection` do wyświetlenia timestampu w bannerze
- Użycie `data_available` do sprawdzenia czy wyświetlić komunikaty "Brak danych"

### 5.4. Endpoint POST /api/logout

**Typ żądania**: `POST`

**URL**: `/api/logout`

**Nagłówki**: 
- `Content-Type: application/json`
- Sesja cookie

**Odpowiedź sukcesu (200 OK)**:
```typescript
{
  success: true,
  message: "Logged out successfully"
}
```

**Obsługa w komponencie**:
- Wywołanie przy kliknięciu przycisku "Wyloguj"
- Po sukcesie: przekierowanie do `/login`
- Obsługa błędów (401, 500) z komunikatem użytkownikowi

## 6. Interakcje użytkownika

### 6.1. Zmiana zakresu czasowego

**Akcja użytkownika**: Kliknięcie przycisku zakresu czasowego w TimeRangeSelector (1h, 6h, 24h, 7d, 30d)

**Oczekiwany wynik**:
1. Przycisk staje się aktywny (wizualne wyróżnienie)
2. Wyświetlenie spinnera obok selektora
3. Wyłączenie przycisków TimeRangeSelector i RefreshButton
4. Wysłanie żądania GET `/api/metrics?range={wybrany_range}`
5. Po otrzymaniu odpowiedzi:
   - Ukrycie spinnera
   - Włączenie przycisków
   - Aktualizacja wszystkich wykresów (CPU, RAM, Disk, I/O, Network) przez Chart.js `update()`
   - Aktualizacja formatu osi X (timestamps) zależnie od zakresu
   - Dla zakresów 7d i 30d: dane są już zagregowane przez backend (10-minutowe buckety)

**Obsługa błędów**:
- Błąd 401 → przekierowanie do `/login?expired=1`
- Błąd 400 → wyświetlenie komunikatu błędu w konsoli (lub toast), przywrócenie poprzedniego zakresu
- Błąd 500 → wyświetlenie banner alert-danger, przywrócenie poprzedniego zakresu
- Timeout sieciowy → wyświetlenie banner alert-danger, możliwość ponownej próby

### 6.2. Odświeżanie danych

**Akcja użytkownika**: Kliknięcie przycisku "Odśwież" w StickyHeader

**Oczekiwany wynik**:
1. Przycisk "Odśwież" staje się disabled z tekstem "Odświeżanie..."
2. Wyświetlenie spinnera obok selektora zakresu czasowego
3. Wyłączenie przycisków TimeRangeSelector
4. Wysłanie żądania GET `/api/metrics?range={aktualny_range}` (ten sam zakres)
5. Po otrzymaniu odpowiedzi:
   - Ukrycie spinnera
   - Włączenie wszystkich przycisków
   - Aktualizacja wszystkich wykresów przez Chart.js `update()` z nowymi danymi
   - Aktualizacja timestampów ostatniej aktualizacji w sekcjach metryk

**Obsługa błędów**: Analogicznie jak przy zmianie zakresu czasowego

### 6.3. Przełączanie jednostek RAM

**Akcja użytkownika**: Kliknięcie toggle button "MB" lub "GB" w sekcji RAM

**Oczekiwany wynik**:
1. Przycisk staje się aktywny (wizualne wyróżnienie)
2. Natychmiastowe przeliczenie wartości z GB na MB (GB * 1024) lub odwrotnie
3. Aktualizacja wykresu Chart.js przez `update()` z nowymi wartościami
4. Aktualizacja etykiet osi Y z jednostkami (GB lub MB)
5. Aktualizacja tooltipów z wartościami w nowej jednostce
6. Brak opóźnień (operacja po stronie klienta, bez żądania API)

**Obsługa błędów**: Brak (operacja lokalna, zawsze bezpieczna)

### 6.4. Wylogowanie

**Akcja użytkownika**: Kliknięcie przycisku "Wyloguj" w StickyHeader

**Oczekiwany wynik**:
1. Wysłanie żądania POST `/api/logout`
2. Po sukcesie: przekierowanie do `/login`
3. Zniszczenie sesji po stronie serwera

**Obsługa błędów**:
- Błąd 401 → przekierowanie do `/login` (sesja już wygasła)
- Błąd 500 → wyświetlenie komunikatu błędu, możliwość ponownej próby

### 6.5. Hover na wykresie

**Akcja użytkownika**: Najechanie myszką na punkt danych na wykresie Chart.js

**Oczekiwany wynik**:
1. Wyświetlenie tooltipa Chart.js z wartością metryki
2. Dla wykresów z wieloma liniami (I/O, Network): wyświetlenie wartości dla wszystkich linii
3. Formatowanie wartości z odpowiednimi jednostkami
4. Wyświetlenie timestampu w formacie zależnym od zakresu czasowego (HH:mm, DD.MM HH:mm)
5. Konwersja timestampu z UTC do strefy czasowej przeglądarki

**Obsługa błędów**: Brak (funkcjonalność Chart.js)

### 6.6. Scrollowanie strony

**Akcja użytkownika**: Przewijanie strony w dół do sekcji metryk

**Oczekiwany wynik**:
1. StickyHeader pozostaje widoczny na górze ekranu
2. Sekcje metryk przewijają się normalnie
3. Wykresy pozostają interaktywne podczas scrollowania
4. Możliwość zmiany zakresu czasowego bez powrotu na górę strony

**Obsługa błędów**: Brak

## 7. Warunki i walidacja

### 7.1. Walidacja zakresu czasowego

**Komponent**: TimeRangeSelector, główny komponent Dashboard

**Warunki**:
- Wybrany zakres musi być jednym z: `'1h'`, `'6h'`, `'24h'`, `'7d'`, `'30d'`
- Domyślnie ustawiony zakres to `'24h'`
- Nie można wybrać zakresu podczas trwającego ładowania danych

**Weryfikacja**:
- Przed wysłaniem żądania API: sprawdzenie czy `selectedRange` jest w dozwolonej liście wartości
- Po otrzymaniu odpowiedzi: sprawdzenie czy `meta.range` w odpowiedzi odpowiada wysłanemu zakresowi
- W przypadku niepoprawnego zakresu: wyświetlenie komunikatu błędu, przywrócenie poprzedniego zakresu

**Wpływ na stan interfejsu**:
- Niepoprawny zakres → wyłączenie przycisków TimeRangeSelector, wyświetlenie komunikatu błędu
- Poprawny zakres → normalne działanie, aktualizacja wykresów

### 7.2. Walidacja danych z API

**Komponent**: Wszystkie sekcje metryk (CPU, RAM, Disk, I/O, Network)

**Warunki**:
- Struktura odpowiedzi musi zawierać pole `success: true`
- Pole `data` musi być tablicą (może być pusta)
- Każdy element `data` musi zawierać wymagane pola: `timestamp`, oraz odpowiednie pola metryk
- Wartości metryk muszą być liczbami (nie null, nie undefined)
- `cpu_usage` musi być w zakresie 0-100
- `ram_usage`, `disk_usage` muszą być nieujemne
- `io_read_bytes`, `io_write_bytes`, `network_sent_bytes`, `network_received_bytes` muszą być nieujemne i całkowite
- `timestamp` musi być w formacie ISO 8601 UTC

**Weryfikacja**:
- Po otrzymaniu odpowiedzi API: walidacja struktury JSON
- Dla każdej sekcji metryk: sprawdzenie czy dane zawierają wymagane pola
- Sprawdzenie typów wartości (number, string)
- Sprawdzenie zakresów wartości (CPU 0-100, pozostałe >= 0)
- Parsowanie i walidacja formatu timestampów

**Wpływ na stan interfejsu**:
- Niepoprawne dane → wyświetlenie komunikatu błędu w konsoli, wyświetlenie "Brak danych" w sekcjach
- Poprawne dane → normalne renderowanie wykresów
- Pusta tablica `data` → wyświetlenie "Brak danych" w każdej sekcji

### 7.3. Walidacja stanu sesji

**Komponent**: Główny komponent Dashboard, wszystkie żądania API

**Warunki**:
- Sesja musi być aktywna (nie wygasła)
- Timeout sesji: 30 minut bezczynności
- Wszystkie żądania API wymagają aktywnej sesji

**Weryfikacja**:
- Przy każdej odpowiedzi API: sprawdzenie statusu HTTP
- Status 401 → wykrycie wygaśnięcia sesji
- Sprawdzenie czy odpowiedź zawiera `success: false` i `error: "Authentication required"`

**Wpływ na stan interfejsu**:
- Wygaśnięcie sesji → automatyczne przekierowanie do `/login?expired=1`
- Aktywna sesja → normalne działanie dashboardu

### 7.4. Walidacja aktualności danych

**Komponent**: AlertBanner, wszystkie sekcje metryk

**Warunki**:
- Dane są aktualne jeśli ostatnia kolekcja była w ciągu ostatnich 5 minut (dla 1-minutowego interwału zbierania)
- Dla zakresów czasowych: dane są aktualne jeśli najnowszy rekord jest z ostatnich 2 minut

**Weryfikacja**:
- Wywołanie `/api/status` przy inicjalizacji
- Porównanie `last_collection` z aktualnym czasem (UTC)
- Sprawdzenie `ssh_connected` z odpowiedzi `/api/status`
- Dla każdej sekcji: porównanie timestampu najnowszego rekordu z aktualnym czasem

**Wpływ na stan interfejsu**:
- Nieaktualne dane → wyświetlenie banner alert-warning, ikon ostrzegawczych w sekcjach, wyświetlenie timestampu ostatniej aktualizacji
- Aktualne dane → normalne wyświetlanie bez bannerów i ikon ostrzegawczych

### 7.5. Walidacja dostępności danych

**Komponent**: Wszystkie sekcje metryk

**Warunki**:
- Dane są dostępne jeśli tablica `data` z API nie jest pusta
- Dla każdej sekcji: dane są dostępne jeśli istnieje co najmniej jeden rekord z odpowiednią metryką

**Weryfikacja**:
- Sprawdzenie `data.length > 0` po otrzymaniu odpowiedzi API
- Dla każdej sekcji: sprawdzenie czy istnieją rekordy z wymaganymi polami
- Sprawdzenie `data_available` z odpowiedzi `/api/status`

**Wpływ na stan interfejsu**:
- Brak danych → wyświetlenie komunikatu "Brak danych" zamiast wykresu w sekcji
- Dostępne dane → normalne renderowanie wykresu

### 7.6. Walidacja formatu timestampów

**Komponent**: Wszystkie sekcje metryk, AlertBanner

**Warunki**:
- Timestamps z API są w formacie ISO 8601 UTC (np. `"2024-01-15T14:30:00Z"`)
- Timestamps muszą być poprawnie parsowalne przez `Date` w JavaScript

**Weryfikacja**:
- Parsowanie każdego timestampu przez `new Date(timestamp)`
- Sprawdzenie czy `Date` nie zwraca `Invalid Date`
- Konwersja do strefy czasowej przeglądarki przez `Intl.DateTimeFormat`

**Wpływ na stan interfejsu**:
- Niepoprawny format → wyświetlenie timestampu w formacie UTC jako fallback, logowanie błędu w konsoli
- Poprawny format → normalne wyświetlanie timestampów w strefie czasowej przeglądarki

### 7.7. Walidacja obliczeń różnic (I/O, Network)

**Komponent**: IOMetricSection, NetworkMetricSection

**Warunki**:
- Wartości I/O i Network są kumulatywne (każda kolejna wartość >= poprzedniej)
- Różnice między kolejnymi pomiarami muszą być nieujemne (lub zero przy resecie licznika)
- Czas między pomiarami musi być dodatni (nie dzielenie przez zero)

**Weryfikacja**:
- Dla każdej pary kolejnych rekordów: obliczenie różnicy wartości
- Sprawdzenie czy różnica >= 0 (lub obsługa resełu licznika, gdy wartość spada)
- Dla Network: sprawdzenie czy czas między pomiarami > 0 przed obliczeniem MB/s lub GB/h
- Walidacja czy obliczone wartości są liczbami (nie NaN, nie Infinity)

**Wpływ na stan interfejsu**:
- Niepoprawne obliczenia → wyświetlenie "Brak danych" lub ostatniej poprawnej wartości, logowanie błędu
- Poprawne obliczenia → normalne wyświetlanie wykresów z wartościami różnicowymi

## 8. Obsługa błędów

### 8.1. Błąd autoryzacji (401 Unauthorized)

**Scenariusz**: Sesja wygasła lub użytkownik nie jest zalogowany

**Obsługa**:
1. Wykrycie statusu HTTP 401 w odpowiedzi API
2. Sprawdzenie czy odpowiedź zawiera `error: "Authentication required"`
3. Automatyczne przekierowanie do `/login?expired=1`
4. Wyświetlenie komunikatu "Sesja wygasła" na stronie logowania (jeśli parametr `expired=1`)

**Komponenty zaangażowane**: Wszystkie komponenty wykonujące żądania API, główny komponent Dashboard

**Komunikaty użytkownikowi**: Przekierowanie do logowania z komunikatem o wygaśnięciu sesji

### 8.2. Błąd walidacji (400 Bad Request)

**Scenariusz**: Niepoprawny parametr zakresu czasowego lub niepoprawny format daty

**Obsługa**:
1. Wykrycie statusu HTTP 400 w odpowiedzi API
2. Parsowanie komunikatu błędu z odpowiedzi (`error` field)
3. Wyświetlenie komunikatu błędu w konsoli przeglądarki (lub toast notification, jeśli zaimplementowany)
4. Przywrócenie poprzedniego zakresu czasowego w TimeRangeSelector
5. Włączenie przycisków TimeRangeSelector i RefreshButton
6. Ukrycie spinnera

**Komponenty zaangażowane**: TimeRangeSelector, główny komponent Dashboard

**Komunikaty użytkownikowi**: Komunikat w konsoli lub toast (opcjonalnie): "Niepoprawny zakres czasowy. Wybierz jeden z: 1h, 6h, 24h, 7d, 30d"

### 8.3. Błąd serwera (500 Internal Server Error)

**Scenariusz**: Błąd bazy danych, błąd serwera, timeout

**Obsługa**:
1. Wykrycie statusu HTTP 500 w odpowiedzi API
2. Wyświetlenie banner alert-danger pod StickyHeader z komunikatem: "Wystąpił błąd techniczny. Spróbuj odświeżyć stronę."
3. Przywrócenie poprzedniego zakresu czasowego (jeśli dotyczy)
4. Włączenie przycisków TimeRangeSelector i RefreshButton
5. Ukrycie spinnera
6. Zachowanie ostatnich załadowanych danych na wykresach (jeśli były)

**Komponenty zaangażowane**: AlertBanner (typ danger), główny komponent Dashboard

**Komunikaty użytkownikowi**: Banner alert-danger z komunikatem o błędzie technicznym i możliwością ponownej próby

### 8.4. Błąd SSH (nieaktualne dane)

**Scenariusz**: Ostatnia kolekcja metryk nie powiodła się z powodu błędu SSH, ale stare dane są dostępne

**Obsługa**:
1. Wywołanie `/api/status` przy inicjalizacji i okresowo
2. Sprawdzenie `ssh_connected: false` lub `last_collection_status !== 'success'`
3. Wyświetlenie banner alert-warning pod StickyHeader z komunikatem: "Brak połączenia z serwerem. Ostatnia aktualizacja: [timestamp]"
4. Wyświetlenie ikon ostrzegawczych obok nagłówków wszystkich sekcji metryk
5. Wywołanie `/api/metrics/latest` dla wyświetlenia ostatniej znanej wartości
6. Wyświetlenie tooltipów przy ikonach ostrzegawczych z informacją o ostatniej aktualizacji
7. Wykresy wyświetlają ostatnie dostępne dane (nie są puste)

**Komponenty zaangażowane**: AlertBanner (typ warning), wszystkie sekcje metryk, główny komponent Dashboard

**Komunikaty użytkownikowi**: Banner z komunikatem o braku połączenia i timestampem, ikony ostrzegawcze w sekcjach

### 8.5. Brak danych dla zakresu czasowego

**Scenariusz**: Wybrany zakres czasowy nie zawiera żadnych rekordów w bazie danych

**Obsługa**:
1. Wykrycie pustej tablicy `data: []` w odpowiedzi API
2. Sprawdzenie `meta.count === 0`
3. Dla każdej sekcji metryk: wyświetlenie komunikatu "Brak danych" zamiast wykresu
4. Komunikat jest wyśrodkowany w kontenerze wykresu
5. Pozostałe sekcje z danymi działają normalnie
6. Przycisk "Odśwież" i selektor zakresu czasowego pozostają dostępne

**Komponenty zaangażowane**: Wszystkie sekcje metryk

**Komunikaty użytkownikowi**: Komunikat "Brak danych" w każdej sekcji osobno

### 8.6. Błąd sieciowy (timeout, brak połączenia)

**Scenariusz**: Timeout żądania API, brak połączenia z internetem, błąd CORS

**Obsługa**:
1. Wykrycie błędu sieciowego w `fetch()` (rejected promise)
2. Sprawdzenie typu błędu (timeout, network error, CORS)
3. Wyświetlenie banner alert-danger z komunikatem: "Błąd połączenia z serwerem. Sprawdź połączenie internetowe i spróbuj ponownie."
4. Przywrócenie poprzedniego zakresu czasowego
5. Włączenie przycisków TimeRangeSelector i RefreshButton
6. Ukrycie spinnera
7. Zachowanie ostatnich załadowanych danych na wykresach (jeśli były)

**Komponenty zaangażowane**: AlertBanner (typ danger), główny komponent Dashboard

**Komunikaty użytkownikowi**: Banner z komunikatem o błędzie połączenia i możliwością ponownej próby

### 8.7. Błąd parsowania JSON

**Scenariusz**: Odpowiedź API nie jest poprawnym JSON

**Obsługa**:
1. Wykrycie błędu podczas `response.json()` (rejected promise)
2. Wyświetlenie banner alert-danger z komunikatem: "Błąd przetwarzania danych. Spróbuj odświeżyć stronę."
3. Logowanie błędu w konsoli przeglądarki
4. Przywrócenie poprzedniego stanu (zakres czasowy, przyciski)

**Komponenty zaangażowane**: Główny komponent Dashboard

**Komunikaty użytkownikowi**: Banner z komunikatem o błędzie przetwarzania danych

### 8.8. Błąd inicjalizacji wykresu Chart.js

**Scenariusz**: Błąd podczas tworzenia instancji Chart.js (np. nieprawidłowe dane, brak elementu canvas)

**Obsługa**:
1. Obsługa wyjątków w `try-catch` podczas inicjalizacji wykresu
2. Sprawdzenie czy element canvas istnieje przed inicjalizacją
3. Walidacja danych przed przekazaniem do Chart.js
4. Wyświetlenie komunikatu "Błąd renderowania wykresu" zamiast wykresu
5. Logowanie błędu w konsoli przeglądarki

**Komponenty zaangażowane**: Wszystkie sekcje metryk z wykresami

**Komunikaty użytkownikowi**: Komunikat "Błąd renderowania wykresu" w sekcji metryki

### 8.9. Błąd obliczeń różnic (I/O, Network)

**Scenariusz**: Niepoprawne obliczenia różnic między kolejnymi pomiarami (np. reset licznika, ujemne różnice)

**Obsługa**:
1. Wykrycie ujemnych różnic lub wartości NaN/Infinity podczas obliczeń
2. Obsługa resełu licznika (wartość spada) przez pominięcie tego pomiaru lub użycie 0
3. Walidacja obliczonych wartości przed przekazaniem do Chart.js
4. Wyświetlenie ostatniej poprawnej wartości lub "Brak danych" w przypadku krytycznego błędu
5. Logowanie błędu w konsoli przeglądarki

**Komponenty zaangażowane**: IOMetricSection, NetworkMetricSection

**Komunikaty użytkownikowi**: Wyświetlenie ostatniej poprawnej wartości lub "Brak danych" na wykresie

## 9. Kroki implementacji

### Krok 1: Przygotowanie struktury plików i zależności

1. Utworzenie kontrolera `DashboardController` w `src/Controller/DashboardController.php` z metodą `index()` zwracającą widok Twig
2. Utworzenie szablonu Twig `templates/dashboard/index.html.twig` rozszerzającego `base.html.twig`
3. Sprawdzenie czy Bootstrap 5 jest dostępny (dodać przez CDN lub npm jeśli brakuje)
4. Dodanie Chart.js do projektu (przez importmap lub CDN): `npm install chart.js` lub dodanie do `importmap.php`
5. Utworzenie pliku JavaScript `assets/js/dashboard.js` dla logiki dashboardu

### Krok 2: Implementacja StickyHeader

1. Utworzenie struktury HTML dla StickyHeader w szablonie Twig:
   - Kontener `<header>` z klasami Bootstrap `sticky-top`, `bg-white`, `shadow-sm`
   - Kontener flexbox dla układu poziomego
   - TimeRangeSelector jako button group
   - RefreshButton i LogoutButton po prawej stronie
2. Dodanie stylów CSS dla sticky header (jeśli potrzebne)
3. Implementacja logiki JavaScript dla TimeRangeSelector:
   - Obsługa kliknięć na przyciskach
   - Zmiana aktywnego przycisku
   - Wywołanie callbacku przy zmianie zakresu
4. Implementacja logiki dla RefreshButton:
   - Obsługa kliknięć
   - Zmiana stanu disabled podczas ładowania
   - Wywołanie callbacku przy odświeżeniu
5. Implementacja logiki dla LogoutButton:
   - Obsługa kliknięć
   - Wywołanie POST `/api/logout`
   - Przekierowanie do `/login` po sukcesie
6. Dodanie LoadingSpinner wyświetlanego warunkowo

### Krok 3: Implementacja AlertBanner

1. Utworzenie struktury HTML dla AlertBanner w szablonie Twig:
   - Kontener `<div>` z klasami Bootstrap `alert`, `alert-warning` lub `alert-danger`
   - Tekst komunikatu
   - Timestamp ostatniej aktualizacji (dla alert-warning)
2. Implementacja logiki JavaScript dla wyświetlania bannera:
   - Funkcja wyświetlająca banner warning (błąd SSH)
   - Funkcja wyświetlająca banner danger (błąd krytyczny)
   - Funkcja ukrywająca banner
3. Implementacja formatowania timestampu:
   - Parsowanie ISO 8601 UTC
   - Konwersja do strefy czasowej przeglądarki
   - Formatowanie do czytelnego tekstu ("2 minuty temu" lub "2024-01-15 14:30")
4. Integracja z głównym komponentem Dashboard (wywołanie przy błędach)

### Krok 4: Implementacja sekcji metryki CPU

1. Utworzenie struktury HTML dla CPUMetricSection w szablonie Twig:
   - Kontener `<section>` z klasami Bootstrap
   - Nagłówek `<h2>` z tekstem "Wykorzystanie CPU" i jednostką "%"
   - Kontener `<canvas>` z ID `cpu-chart` i odpowiednią wysokością
   - Komunikat "Brak danych" (warunkowy)
   - Ikona ostrzegawcza (warunkowa) z tooltipem
2. Implementacja logiki JavaScript dla wykresu:
   - Funkcja inicjalizująca wykres Chart.js (line chart)
   - Konfiguracja osi X (timestamps) i osi Y (wartości 0-100%)
   - Funkcja aktualizująca dane wykresu przez `update()`
   - Funkcja formatująca timestamps zależnie od zakresu czasowego
3. Implementacja obsługi danych:
   - Parsowanie danych z API
   - Walidacja wartości CPU (0-100%)
   - Przekazanie danych do Chart.js
4. Implementacja obsługi braku danych:
   - Sprawdzenie czy `data.length === 0`
   - Wyświetlenie komunikatu "Brak danych" zamiast wykresu
5. Implementacja obsługi nieaktualnych danych:
   - Wyświetlenie ikony ostrzegawczej
   - Tooltip z informacją o ostatniej aktualizacji

### Krok 5: Implementacja sekcji metryki RAM

1. Utworzenie struktury HTML dla RAMMetricSection (analogicznie do CPU):
   - Dodanie toggle button (GB/MB) obok nagłówka
2. Implementacja logiki JavaScript dla wykresu (analogicznie do CPU):
   - Dodanie obsługi przełącznika jednostek
   - Funkcja przeliczająca wartości GB ↔ MB (GB * 1024 = MB)
   - Aktualizacja etykiet osi Y przy przełączeniu jednostek
   - Aktualizacja tooltipów z wartościami w aktualnej jednostce
3. Implementacja toggle button:
   - Obsługa kliknięć
   - Zmiana aktywnego przycisku (GB/MB)
   - Natychmiastowe przeliczenie wartości bez żądania API
   - Aktualizacja wykresu przez `update()`

### Krok 6: Implementacja sekcji metryki Disk

1. Utworzenie struktury HTML dla DiskMetricSection (analogicznie do CPU, bez toggle button)
2. Implementacja logiki JavaScript dla wykresu (analogicznie do CPU, wartości w GB)

### Krok 7: Implementacja sekcji metryki I/O

1. Utworzenie struktury HTML dla IOMetricSection:
   - Kontener z wykresem i legendą
2. Implementacja logiki JavaScript dla wykresu:
   - Inicjalizacja wykresu Chart.js z dwoma datasetami (read i write)
   - Konfiguracja kolorów linii (read: niebieski, write: pomarańczowy)
   - Funkcja obliczająca różnice między kolejnymi pomiarami dla każdej linii
   - Obsługa resełu licznika (wartość spada) przez pominięcie lub użycie 0
   - Formatowanie wartości na osi Y z automatycznym doborem jednostek (KB, MB, GB)
   - Tooltips z wartościami read i write osobno
3. Implementacja obliczeń różnic:
   - Iteracja przez dane i obliczenie różnic: `diff = current_value - previous_value`
   - Walidacja czy różnica >= 0 (lub obsługa resełu)
   - Przekazanie wartości różnicowych do Chart.js

### Krok 8: Implementacja sekcji metryki Network

1. Utworzenie struktury HTML dla NetworkMetricSection (analogicznie do I/O)
2. Implementacja logiki JavaScript dla wykresu:
   - Inicjalizacja wykresu z dwoma datasetami (sent i received)
   - Konfiguracja kolorów (sent: zielony, received: czerwony)
   - Funkcja obliczająca różnice między kolejnymi pomiarami
   - Funkcja konwertująca różnice na MB/s lub GB/h:
     - Obliczenie czasu między pomiarami w sekundach
     - Dzielenie różnicy przez czas: `rate = diff_bytes / time_seconds`
     - Konwersja na MB/s: `rate_mb_per_s = rate / (1024 * 1024)`
     - Konwersja na GB/h: `rate_gb_per_h = rate / (1024 * 1024 * 1024) * 3600`
   - Automatyczny dobór jednostek (MB/s dla krótkich zakresów, GB/h dla długich)
   - Formatowanie etykiet osi Y z jednostkami
   - Tooltips z wartościami sent i received osobno
3. Implementacja walidacji obliczeń:
   - Sprawdzenie czy czas między pomiarami > 0
   - Sprawdzenie czy obliczone wartości są liczbami (nie NaN, nie Infinity)

### Krok 9: Implementacja integracji API

1. Utworzenie funkcji pomocniczych do żądań API:
   - `fetchMetrics(range: string): Promise<MetricsResponse>` - żądanie GET `/api/metrics?range={range}`
   - `fetchLatestMetrics(): Promise<LatestMetricsResponse>` - żądanie GET `/api/metrics/latest`
   - `fetchStatus(): Promise<StatusResponse>` - żądanie GET `/api/status`
   - `logout(): Promise<void>` - żądanie POST `/api/logout`
2. Implementacja obsługi odpowiedzi API:
   - Parsowanie JSON
   - Walidacja struktury odpowiedzi
   - Obsługa błędów HTTP (401, 400, 500)
   - Obsługa błędów sieciowych (timeout, brak połączenia)
3. Implementacja obsługi sesji:
   - Wykrycie błędu 401 → przekierowanie do `/login?expired=1`
   - Sprawdzenie aktywności sesji przy inicjalizacji

### Krok 10: Implementacja głównego komponentu Dashboard

1. Utworzenie głównej funkcji inicjalizującej dashboard:
   - `initDashboard()` - wywoływana przy załadowaniu strony
   - Inicjalizacja wszystkich sekcji metryk
   - Ustawienie domyślnego zakresu czasowego (24h)
   - Wywołanie `/api/status` dla sprawdzenia stanu systemu
   - Wywołanie `/api/metrics?range=24h` dla załadowania danych
2. Implementacja zarządzania stanem:
   - Zmienne stanu: `selectedRange`, `isLoading`, `metricsData`, `statusData`, `isStale`
   - Funkcje aktualizujące stan
3. Implementacja obsługi zmiany zakresu czasowego:
   - Callback `onRangeChange(range)` w TimeRangeSelector
   - Aktualizacja stanu `selectedRange`
   - Wywołanie `fetchMetrics(range)`
   - Aktualizacja wszystkich wykresów po otrzymaniu danych
4. Implementacja obsługi odświeżania:
   - Callback `onRefresh()` w RefreshButton
   - Wywołanie `fetchMetrics(selectedRange)` (ten sam zakres)
   - Aktualizacja wszystkich wykresów
5. Implementacja obsługi błędów:
   - Funkcje obsługujące różne typy błędów (401, 400, 500, network)
   - Wyświetlanie odpowiednich bannerów alertowych
   - Przywracanie poprzedniego stanu przy błędach

### Krok 11: Implementacja formatowania i konwersji danych

1. Utworzenie funkcji pomocniczych do formatowania:
   - `formatTimestamp(timestamp: string, range: string): string` - formatowanie timestampów zależnie od zakresu (HH:mm, DD.MM HH:mm)
   - `formatBytes(bytes: number): string` - formatowanie bajtów do KB/MB/GB
   - `formatRelativeTime(timestamp: string): string` - formatowanie względnego czasu ("2 minuty temu")
   - `convertToLocalTime(utcTimestamp: string): Date` - konwersja UTC do strefy czasowej przeglądarki
2. Implementacja konwersji jednostek:
   - `convertGBToMB(gb: number): number` - konwersja GB na MB
   - `convertMBToGB(mb: number): number` - konwersja MB na GB
   - `calculateIORates(data: Array<MetricData>): Array<ProcessedIOData>` - obliczanie różnic I/O
   - `calculateNetworkRates(data: Array<MetricData>, range: string): Array<ProcessedNetworkData>` - obliczanie różnic Network i konwersja na MB/s lub GB/h

### Krok 12: Implementacja responsywności

1. Dodanie klas Bootstrap dla responsywności:
   - Użycie grid system Bootstrap dla układu sekcji
   - Klasy responsywne dla wykresów (`col-12`, `col-md-6`, etc. jeśli potrzebne)
2. Implementacja dynamicznej wysokości wykresów:
   - Media queries w CSS dla różnych rozdzielczości
   - Min. wysokość 300px na desktop, 250px na mobile
   - Max. wysokość 500px na desktop
3. Testowanie responsywności:
   - Sprawdzenie działania na różnych rozdzielczościach (mobile, tablet, desktop)
   - Sprawdzenie czy wszystkie kontrolki są dostępne na mobile
   - Sprawdzenie czy wykresy są czytelne na małych ekranach

### Krok 13: Implementacja dostępności (a11y)

1. Dodanie semantycznych tagów HTML:
   - `<header>`, `<section>`, `<nav>` zamiast `<div>` gdzie możliwe
   - Właściwe nagłówki hierarchiczne (`<h1>`, `<h2>`)
2. Dodanie ARIA labels:
   - `aria-label` dla wszystkich przycisków
   - `aria-describedby` dla tooltipów
   - `role="status"` dla spinnera
   - `aria-live="polite"` dla dynamicznych komunikatów
3. Implementacja keyboard navigation:
   - Obsługa Tab, Enter, Space dla wszystkich kontrolek
   - Obsługa strzałek dla TimeRangeSelector (opcjonalnie)
   - Focus management po interakcjach
4. Dodanie alternatywnych tekstów:
   - `alt` dla ikon (jeśli używane jako `<img>`)
   - `aria-label` dla ikon SVG
5. Sprawdzenie kontrastu kolorów:
   - Zgodność z WCAG 2.1 AA
   - Testowanie z czytnikami ekranu (opcjonalnie)

### Krok 14: Implementacja obsługi stanów wyjątkowych

1. Implementacja obsługi braku danych:
   - Sprawdzenie `data.length === 0` dla każdej sekcji
   - Wyświetlenie komunikatu "Brak danych" zamiast wykresu
   - Zachowanie funkcjonalności kontrolek (przyciski dostępne)
2. Implementacja obsługi nieaktualnych danych:
   - Wywołanie `/api/status` przy inicjalizacji
   - Sprawdzenie `ssh_connected` i `last_collection_status`
   - Wyświetlenie banner alert-warning i ikon ostrzegawczych
   - Wywołanie `/api/metrics/latest` dla ostatniej znanej wartości
3. Implementacja obsługi wygaśnięcia sesji:
   - Wykrycie błędu 401 w każdej odpowiedzi API
   - Automatyczne przekierowanie do `/login?expired=1`
4. Implementacja obsługi równoczesnych akcji:
   - Zapobieganie wielokrotnym żądaniom podczas ładowania
   - Anulowanie poprzedniego żądania przy nowej akcji (opcjonalnie, przez AbortController)

### Krok 15: Testowanie i optymalizacja

1. Testowanie funkcjonalności:
   - Test zmiany zakresu czasowego dla wszystkich opcji
   - Test odświeżania danych
   - Test przełączania jednostek RAM
   - Test wylogowania
   - Test hover na wykresach
2. Testowanie obsługi błędów:
   - Symulacja błędu 401 (wygaśnięcie sesji)
   - Symulacja błędu 400 (niepoprawny zakres)
   - Symulacja błędu 500 (błąd serwera)
   - Symulacja błędu sieciowego (timeout)
   - Symulacja braku danych
   - Symulacja nieaktualnych danych (błąd SSH)
3. Testowanie responsywności:
   - Test na różnych rozdzielczościach ekranu
   - Test na różnych urządzeniach (mobile, tablet, desktop)
   - Test orientacji ekranu (portrait, landscape)
4. Testowanie wydajności:
   - Sprawdzenie czasu ładowania dashboardu
   - Sprawdzenie płynności animacji wykresów
   - Sprawdzenie zużycia pamięci przy dużych zakresach czasowych (30d)
5. Optymalizacja:
   - Lazy loading wykresów (opcjonalnie, jeśli potrzebne)
   - Debouncing dla szybkich zmian zakresu czasowego (opcjonalnie)
   - Cache ostatnio załadowanych danych (opcjonalnie)

### Krok 16: Dokumentacja i finalizacja

1. Dodanie komentarzy w kodzie JavaScript:
   - Dokumentacja funkcji pomocniczych
   - Opis logiki biznesowej
   - Wyjaśnienie obliczeń (I/O, Network)
2. Sprawdzenie zgodności z PRD:
   - Weryfikacja wszystkich wymagań funkcjonalnych
   - Weryfikacja wszystkich user stories
   - Weryfikacja obsługi błędów
3. Finalne testy:
   - Test end-to-end przepływu użytkownika
   - Test integracji z backendem
   - Test na różnych przeglądarkach (Chrome, Firefox, Safari)
4. Przygotowanie do wdrożenia:
   - Sprawdzenie czy wszystkie zależności są zainstalowane
   - Sprawdzenie czy routing jest poprawnie skonfigurowany
   - Sprawdzenie czy autoryzacja działa poprawnie

