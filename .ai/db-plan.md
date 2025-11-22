# Schemat bazy danych - Server Monitor

## 1. Tabele

### 1.1. Tabela: `server_metrics`

Tabela przechowująca metryki systemowe zbierane z monitorowanego serwera Linux przez SSH.

**Kolumny:**

| Nazwa kolumny | Typ danych | Ograniczenia | Opis |
|--------------|------------|--------------|------|
| `id` | `INT UNSIGNED` | `AUTO_INCREMENT`, `PRIMARY KEY`, `NOT NULL` | Unikalny identyfikator rekordu |
| `timestamp` | `DATETIME` | `NOT NULL` | Czas zbierania metryki w UTC (ustawiane explicite w aplikacji) |
| `cpu_usage` | `DECIMAL(5,2) UNSIGNED` | `NOT NULL`, `DEFAULT 0` | Procentowe wykorzystanie procesora (zakres: 0.00-100.00) |
| `ram_usage` | `DECIMAL(10,2) UNSIGNED` | `NOT NULL`, `DEFAULT 0` | Wykorzystanie pamięci RAM w GB (do ~99,999,999.99 GB) |
| `disk_usage` | `DECIMAL(10,2) UNSIGNED` | `NOT NULL`, `DEFAULT 0` | Wykorzystanie dysku w GB (do ~99,999,999.99 GB) |
| `io_read_bytes` | `BIGINT UNSIGNED` | `NOT NULL`, `DEFAULT 0` | Kumulatywne bajty odczytane z dysku (wartość bezwzględna z `/proc/diskstats`) |
| `io_write_bytes` | `BIGINT UNSIGNED` | `NOT NULL`, `DEFAULT 0` | Kumulatywne bajty zapisane na dysk (wartość bezwzględna z `/proc/diskstats`) |
| `network_sent_bytes` | `BIGINT UNSIGNED` | `NOT NULL`, `DEFAULT 0` | Kumulatywne bajty wysłane przez sieć (wartość bezwzględna z `/proc/net/dev`) |
| `network_received_bytes` | `BIGINT UNSIGNED` | `NOT NULL`, `DEFAULT 0` | Kumulatywne bajty odebrane z sieci (wartość bezwzględna z `/proc/net/dev`) |

**Silnik:** `InnoDB` (domyślny w MySQL 8.0+)

**Kodowanie:** `utf8mb4` (domyślne w MySQL 8.0+)

**Uwagi:**
- Wszystkie metryki mają wartości domyślne `0`, co zapobiega wartościom NULL w przypadku błędów parsowania
- Kolumna `timestamp` jest ustawiana explicite w aplikacji Symfony z użyciem `new \DateTime('now', new \DateTimeZone('UTC'))`
- Metryki I/O i Network są przechowywane jako wartości kumulatywne (bezwzględne), różnice są obliczane w aplikacji/frontendzie
- Duplikaty timestamp są akceptowalne (brak UNIQUE constraint), co pozwala na retry i równoczesne wykonania cron bez błędów
- Brak kolumny `server_id` - aplikacja MVP monitoruje tylko jeden serwer konfigurowany w `.env`

## 2. Indeksy

### 2.1. Indeks podstawowy

- **PRIMARY KEY** na kolumnie `id` (automatycznie tworzony przez `AUTO_INCREMENT PRIMARY KEY`)

### 2.2. Indeksy pomocnicze

- **INDEX `idx_timestamp`** na kolumnie `timestamp`
  - **Cel:** Optymalizacja zapytań zakresowych z warunkami `WHERE timestamp BETWEEN ... AND ...`
  - **Wydajność:** Wspiera zapytania z zakresami czasowymi, `ORDER BY timestamp`, oraz agregację dla długich zakresów (7d, 30d)
  - **Definicja SQL:**
    ```sql
    CREATE INDEX idx_timestamp ON server_metrics (timestamp);
    ```

## 3. Relacje

Brak relacji z innymi tabelami. Aplikacja MVP monitoruje tylko jeden serwer, więc nie jest wymagana normalizacja do osobnej tabeli `servers`.

## 4. Zasady MySQL (Row Level Security)

Brak zasad RLS (Row Level Security). Autoryzacja jest obsługiwana na poziomie aplikacji przez hasło z `.env`.

## 5. Strategie zapytań

### 5.1. Zapytania zakresowe

Zapytania dla zakresów czasowych (1h, 6h, 24h) wykorzystują indeks na `timestamp`:

```sql
SELECT * FROM server_metrics 
WHERE timestamp BETWEEN :start_time AND :end_time 
ORDER BY timestamp ASC;
```

### 5.2. Agregacja dla długich zakresów

Dla zakresów 7d i 30d dane są agregowane bezpośrednio w SQL przy zapytaniu z użyciem `GROUP BY` po 10-minutowych przedziałach:

```sql
SELECT 
    FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 600) * 600) AS time_bucket,
    AVG(cpu_usage) AS cpu_usage,
    AVG(ram_usage) AS ram_usage,
    AVG(disk_usage) AS disk_usage,
    MAX(io_read_bytes) AS io_read_bytes,
    MAX(io_write_bytes) AS io_write_bytes,
    MAX(network_sent_bytes) AS network_sent_bytes,
    MAX(network_received_bytes) AS network_received_bytes
FROM server_metrics
WHERE timestamp BETWEEN :start_time AND :end_time
GROUP BY time_bucket
ORDER BY time_bucket ASC;
```

**Uwagi dotyczące agregacji:**
- `AVG()` dla wartości ciągłych (CPU, RAM, Disk)
- `MAX()` dla wartości kumulatywnych (I/O, Network) - ostatnia wartość w przedziale reprezentuje kumulatywną wartość
- Grupowanie po 10 minutach: `FLOOR(UNIX_TIMESTAMP(timestamp) / 600) * 600` (600 sekund = 10 minut)

### 5.3. Usuwanie starych rekordów

Usuwanie rekordów starszych niż 90 dni odbywa się w małych batchach z przerwami:

```sql
DELETE FROM server_metrics 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY) 
LIMIT 1000;
```

**Strategia:**
- Usuwanie w batchach po 1000 rekordów (`LIMIT 1000`)
- Wykonywane w pętli z krótkimi przerwami (`SLEEP(0.1)`) między batchami
- Uruchamiane w godzinach niskiego obciążenia (np. 3:00 AM przez cron)
- Minimalizuje blokowanie zapytań SELECT podczas operacji DELETE

## 6. Ograniczenia i walidacja

### 6.1. Ograniczenia na poziomie bazy danych

- **NOT NULL:** Wszystkie kolumny mają ograniczenie `NOT NULL` z wartościami domyślnymi
- **UNIQUE:** Brak UNIQUE constraints (duplikaty timestamp są akceptowalne)
- **CHECK:** Brak CHECK constraints na poziomie bazy danych (walidacja w aplikacji)

### 6.2. Walidacja w aplikacji

Walidacja wartości metryk odbywa się w aplikacji Symfony przed zapisem do bazy:
- `cpu_usage`: zakres 0.00-100.00
- `ram_usage`, `disk_usage`: wartości nieujemne
- Wszystkie metryki bajtowe: wartości nieujemne

## 7. Skalowalność i wydajność

### 7.1. Pojemność

- **Szacowana liczba rekordów w 90 dniach:** ~129,600 rekordów (zbieranie co minutę: 60 * 24 * 90)
- **Rozmiar rekordu:** ~80 bajtów (szacunkowo)
- **Szacowany rozmiar tabeli:** ~10 MB (bez indeksów)
- **`INT UNSIGNED` dla `id`:** Wystarcza dla MVP (zakres do ~4.3 miliarda rekordów)

### 7.2. Wydajność

- **InnoDB:** Zapewnia row-level locking dla równoczesnych zapisów
- **Indeks na `timestamp`:** Optymalizuje zapytania zakresowe i agregację
- **Agregacja w SQL:** Eliminuje potrzebę materializowanych widoków w MVP
- **Usuwanie w batchach:** Minimalizuje blokowanie zapytań SELECT

### 7.3. Retencja danych

- **Okres retencji:** 90 dni
- **Automatyczne czyszczenie:** Raz dziennie przez cron command
- **Brak archiwizacji:** Dane starsze niż 90 dni są bezpowrotnie usuwane

## 8. Bezpieczeństwo

### 8.1. Poziom bazy danych

- Wszystkie metryki mają wartości domyślne `0`, co zapobiega NULL w przypadku błędów parsowania
- `timestamp` ustawiane explicite w aplikacji w UTC zapewnia spójność czasową
- Brak dodatkowych mechanizmów bezpieczeństwa na poziomie bazy (autoryzacja w aplikacji przez hasło z `.env`)

### 8.2. Poziom aplikacji

- Autoryzacja przez hasło z `.env` (nie w zakresie schematu bazy danych)
- Wszystkie credentials przechowywane w `.env` (gitignored)

## 9. Definicja SQL (CREATE TABLE)

```sql
CREATE TABLE `server_metrics` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `timestamp` DATETIME NOT NULL,
    `cpu_usage` DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0,
    `ram_usage` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    `disk_usage` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    `io_read_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `io_write_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `network_sent_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `network_received_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 10. Uwagi implementacyjne

### 10.1. Doctrine Migrations

Schemat powinien być zarządzany przez Doctrine Migrations w Symfony:
- Migracja tworzy tabelę `server_metrics` z wszystkimi kolumnami i indeksami
- Migracje można wykonać i cofnąć
- Brak seederów (dane tylko z rzeczywistego zbierania)

### 10.2. Entity w Symfony

Entity `ServerMetric` powinna mapować wszystkie kolumny z odpowiednimi typami Doctrine:
- `id`: `integer`, `unsigned: true`, `autoincrement: true`
- `timestamp`: `datetime`
- `cpu_usage`: `decimal`, `precision: 5`, `scale: 2`
- `ram_usage`, `disk_usage`: `decimal`, `precision: 10`, `scale: 2`
- Metryki bajtowe: `bigint`, `unsigned: true`

### 10.3. Timezone

Wszystkie wartości `timestamp` są przechowywane w UTC i ustawiane explicite w aplikacji Symfony z użyciem `new \DateTime('now', new \DateTimeZone('UTC'))`.

