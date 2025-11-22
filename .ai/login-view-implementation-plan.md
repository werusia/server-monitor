# Plan implementacji widoku logowania

## 1. Przegląd

Widok logowania (`/login`) jest punktem wejścia do aplikacji Server Monitor. Umożliwia autoryzację użytkownika przez hasło przechowywane w konfiguracji `.env` przed uzyskaniem dostępu do dashboardu z metrykami serwera. Widok składa się z prostego formularza logowania z polem hasła, obsługą błędów autoryzacji oraz komunikatem o wygaśnięciu sesji. Implementacja wykorzystuje Symfony Security Component do zarządzania sesją i ochrony CSRF.

## 2. Routing widoku

**Ścieżka:** `/login`

**Metoda HTTP:** GET (wyświetlenie formularza), POST (przetworzenie formularza)

**Kontroler:** `LoginController` w namespace `App\Controller`

**Nazwa routy:** `app_login` (GET), `app_login_post` (POST)

**Ochrona routingu:**
- Ruta `/login` jest dostępna publicznie (bez wymaganej autoryzacji)
- Symfony Security automatycznie przekierowuje nieautoryzowanych użytkowników z `/` i `/dashboard` do `/login`
- Po udanym logowaniu użytkownik jest przekierowywany do `/dashboard` (lub `/` jako domyślna)

## 3. Struktura komponentów

```
LoginView (Twig Template)
├── Page Container (Bootstrap container)
│   ├── Header Section
│   │   └── Application Title ("Server Monitor")
│   ├── Alert Section (warunkowy)
│   │   ├── Alert Info (wygaśnięcie sesji)
│   │   └── Alert Danger (błąd logowania)
│   └── Login Form
│       ├── CSRF Token Field
│       ├── Password Input Group
│       │   ├── Label
│       │   └── Input (type="password")
│       └── Submit Button Group
│           └── Submit Button ("Zaloguj")
```

## 4. Szczegóły komponentów

### LoginView (Główny widok Twig)

**Opis komponentu:** Główny template Twig renderujący całą stronę logowania. Zawiera strukturę HTML z Bootstrap 5, formularz logowania, komunikaty błędów i alerty informacyjne.

**Główne elementy:**
- `<html>` z atrybutami `lang="pl"`
- `<head>` z meta tagami, tytułem strony, linkami do Bootstrap 5 CSS i custom CSS
- `<body>` z kontenerem Bootstrap (`container` lub `container-fluid`)
- Sekcja nagłówka z tytułem aplikacji
- Sekcja alertów (warunkowa)
- Formularz logowania z polami i przyciskiem submit
- Skrypty JavaScript (opcjonalnie dla ulepszonej dostępności)

**Obsługiwane zdarzenia:**
- Renderowanie strony (GET request)
- Przetwarzanie formularza (POST request przez Symfony)
- Wyświetlanie alertów na podstawie parametrów URL i błędów formularza

**Obsługiwana walidacja:**
- Walidacja CSRF token przez Symfony Security
- Walidacja hasła po stronie serwera (porównanie z wartością z `.env`)
- Walidacja pustego hasła (frontend i backend)

**Typy:**
- `LoginFormType` (Symfony Form Type) dla formularza
- `LoginController` (Symfony Controller) do obsługi requestów

**Propsy/Parametry:**
- `error` (opcjonalny): Komunikat błędu z Symfony Security
- `last_username` (opcjonalny): Ostatnia wprowadzona nazwa użytkownika (nieużywane w MVP, ale może być przekazane przez Symfony)
- `expired` (opcjonalny): Parametr URL `?expired=1` wskazujący na wygaśnięcie sesji

---

### Header Section

**Opis komponentu:** Sekcja nagłówka wyświetlająca tytuł aplikacji "Server Monitor" dla kontekstu użytkownika.

**Główne elementy:**
- `<header>` lub `<div>` z klasą Bootstrap (np. `text-center mb-4`)
- `<h1>` z tytułem "Server Monitor"
- Opcjonalnie: podtytuł lub logo aplikacji

**Obsługiwane zdarzenia:**
- Brak interakcji (tylko wyświetlanie)

**Obsługiwana walidacja:**
- Brak walidacji

**Typy:**
- Brak specjalnych typów

**Propsy/Parametry:**
- Brak parametrów

---

### Alert Section (Warunkowy)

**Opis komponentu:** Sekcja wyświetlająca komunikaty informacyjne i błędy. Składa się z dwóch typów alertów Bootstrap 5: `alert-info` dla wygaśnięcia sesji i `alert-danger` dla błędów logowania.

**Główne elementy:**
- `<div>` z klasą Bootstrap `alert` i odpowiednią klasą kontekstu (`alert-info` lub `alert-danger`)
- Ikona (opcjonalnie, Bootstrap Icons)
- Tekst komunikatu
- Przycisk zamykający (opcjonalnie, `alert-dismissible`)

**Obsługiwane zdarzenia:**
- Wyświetlanie alertu na podstawie parametrów URL (`?expired=1`) lub błędów formularza
- Zamykanie alertu przez użytkownika (jeśli `alert-dismissible`)

**Obsługiwana walidacja:**
- Sprawdzanie obecności parametru `expired` w URL
- Sprawdzanie obecności błędu w zmiennej `error` przekazanej do template

**Typy:**
- `string|null` dla komunikatu błędu
- `bool` dla flagi wygaśnięcia sesji

**Propsy/Parametry:**
- `expired` (bool): Flaga wskazująca wygaśnięcie sesji (z parametru URL `?expired=1`)
- `error` (string|null): Komunikat błędu z Symfony Security

**Komunikaty:**
- Alert Info: "Sesja wygasła. Zaloguj się ponownie, aby kontynuować."
- Alert Danger: Komunikat z zmiennej `error` lub domyślny "Nieprawidłowe hasło. Spróbuj ponownie."

---

### Login Form

**Opis komponentu:** Formularz logowania z polem hasła, tokenem CSRF i przyciskiem submit. Formularz wykorzystuje Symfony Form Component z walidacją po stronie serwera.

**Główne elementy:**
- `<form>` z atrybutami:
  - `method="POST"`
  - `action="{{ path('app_login_post') }}"` lub `action=""`
  - `novalidate` (walidacja po stronie serwera)
- Pole CSRF token (ukryte, generowane przez Symfony)
- Grupa input dla hasła:
  - `<label>` z atrybutem `for` i tekstem "Hasło"
  - `<input>` typu `password` z wymaganymi atrybutami dostępności
- Grupa przycisków:
  - `<button>` typu `submit` z tekstem "Zaloguj"

**Obsługiwane zdarzenia:**
- `submit`: Przesłanie formularza do endpointu POST `/api/login` (lub bezpośrednio do kontrolera)
- `focus`: Zarządzanie focusem dla dostępności
- `keydown`: Obsługa klawisza Enter do submit formularza

**Obsługiwana walidacja:**
- **Frontend (opcjonalna):**
  - Sprawdzanie czy pole hasła nie jest puste przed submit
  - Wyświetlanie komunikatu błędu przy pustym haśle
- **Backend (wymagana):**
  - Walidacja CSRF token przez Symfony Security
  - Sprawdzanie czy pole `password` jest wypełnione
  - Porównanie hasła z wartością z `.env` (np. `APP_PASSWORD`)
  - Walidacja sesji i utworzenie sesji przy udanym logowaniu

**Typy:**
- `LoginFormType` (Symfony Form Type) z polami:
  - `_token` (CSRF token, ukryte)
  - `password` (TextType z opcją `mapped: false`)
- `LoginController` obsługujący GET i POST requesty

**Propsy/Parametry:**
- `form` (FormView): Obiekt formularza Symfony przekazany do template
- `error` (AuthenticationException|null): Wyjątek autoryzacji z Symfony Security (jeśli wystąpił błąd)

**Atrybuty dostępności:**
- `aria-label="Hasło"` dla pola input
- `aria-describedby` dla komunikatu błędu (jeśli występuje)
- `aria-invalid="true"` przy błędzie walidacji
- `aria-required="true"` dla pola hasła
- `role="alert"` dla komunikatu błędu

---

### Password Input Group

**Opis komponentu:** Grupa formularza zawierająca label i pole input dla hasła. Zapewnia semantyczną strukturę HTML i dostępność.

**Główne elementy:**
- `<div>` z klasą Bootstrap `mb-3` (lub `form-group`)
- `<label>` z atrybutami:
  - `for="login_form_password"`
  - Klasa Bootstrap `form-label`
  - Tekst "Hasło"
- `<input>` z atrybutami:
  - `type="password"`
  - `id="login_form_password"`
  - `name="login_form[password]"`
  - `class="form-control"`
  - `required` (walidacja HTML5, opcjonalnie)
  - `autocomplete="current-password"`
  - `aria-label`, `aria-describedby`, `aria-invalid` (dla dostępności)
- `<div>` z klasą `invalid-feedback` dla komunikatu błędu (warunkowy)

**Obsługiwane zdarzenia:**
- `focus`: Ustawienie focusu na pole input
- `blur`: Sprawdzenie walidacji po opuszczeniu pola (opcjonalnie)
- `input`: Aktualizacja stanu walidacji w czasie rzeczywistym (opcjonalnie)

**Obsługiwana walidacja:**
- Sprawdzanie czy pole nie jest puste (frontend, opcjonalnie)
- Walidacja po stronie serwera przez Symfony

**Typy:**
- `string|null` dla wartości hasła

**Propsy/Parametry:**
- `value` (string|null): Wartość pola (zazwyczaj pusta ze względów bezpieczeństwa)
- `error` (string|null): Komunikat błędu walidacji

---

### Submit Button Group

**Opis komponentu:** Grupa przycisków zawierająca przycisk submit do przesłania formularza logowania.

**Główne elementy:**
- `<div>` z klasą Bootstrap `d-grid` (lub `text-center`)
- `<button>` z atrybutami:
  - `type="submit"`
  - `class="btn btn-primary btn-lg"` (lub podobne klasy Bootstrap)
  - Tekst "Zaloguj"
  - `aria-label="Zaloguj się"` (dla dostępności)

**Obsługiwane zdarzenia:**
- `click`: Przesłanie formularza (domyślne zachowanie)
- `keydown`: Obsługa klawisza Enter (domyślne zachowanie formularza)

**Obsługiwana walidacja:**
- Walidacja formularza przed submit (HTML5 i Symfony)

**Typy:**
- Brak specjalnych typów

**Propsy/Parametry:**
- Brak parametrów

**Stany:**
- Normalny: Przycisk aktywny, gotowy do kliknięcia
- Disabled (opcjonalnie): Podczas przetwarzania formularza (zapobieganie wielokrotnym kliknięciom)

## 5. Typy

### 5.1. Typy formularza Symfony

#### LoginFormType

**Namespace:** `App\Form\Type\LoginFormType` (lub `App\Form\LoginFormType`)

**Opis:** Symfony Form Type definiujący strukturę formularza logowania.

**Pola:**
- `_token` (CSRFProtectionType, ukryte)
  - Typ: `Symfony\Component\Form\Extension\Core\Type\HiddenType`
  - Opcje: Automatycznie generowane przez Symfony Security
- `password` (TextType)
  - Typ: `Symfony\Component\Form\Extension\Core\Type\PasswordType`
  - Opcje:
    - `label`: `"Hasło"`
    - `required`: `true`
    - `mapped`: `false` (hasło nie jest mapowane do encji)
    - `attr`: `['class' => 'form-control', 'autocomplete' => 'current-password', 'aria-label' => 'Hasło']`

**Walidacja:**
- `NotBlank` constraint dla pola `password`
- CSRF token walidowany automatycznie przez Symfony Security

---

### 5.2. Typy danych

#### LoginRequest (DTO, opcjonalnie)

**Namespace:** `App\DTO\LoginRequest` (jeśli używany)

**Opis:** Data Transfer Object dla danych formularza logowania (opcjonalny, jeśli nie używamy bezpośrednio formularza Symfony).

**Pola:**
- `password` (string)
  - Typ: `string`
  - Walidacja: `NotBlank`, `Length(min: 1)`

---

### 5.3. Typy odpowiedzi API

#### LoginResponse (dla integracji z `/api/login`, jeśli używany)

**Opis:** Struktura odpowiedzi z endpointu POST `/api/login`.

**Pola:**
- `success` (bool)
  - Typ: `boolean`
  - Wartość: `true` przy udanym logowaniu, `false` przy błędzie
- `message` (string, opcjonalny)
  - Typ: `string|null`
  - Wartość: Komunikat sukcesu (np. "Authentication successful")
- `error` (string, opcjonalny)
  - Typ: `string|null`
  - Wartość: Komunikat błędu (np. "Invalid password" lub "Password is required")

**Przykład odpowiedzi sukcesu:**
```json
{
  "success": true,
  "message": "Authentication successful"
}
```

**Przykład odpowiedzi błędu:**
```json
{
  "success": false,
  "error": "Invalid password"
}
```

---

### 5.4. Typy dla template Twig

#### LoginViewData

**Opis:** Struktura danych przekazywanych do template Twig.

**Pola:**
- `form` (FormView)
  - Typ: `Symfony\Component\Form\FormView`
  - Opis: Obiekt formularza Symfony do renderowania
- `error` (AuthenticationException|null)
  - Typ: `Symfony\Component\Security\Core\Exception\AuthenticationException|null`
  - Opis: Wyjątek autoryzacji (jeśli wystąpił błąd)
- `last_username` (string|null)
  - Typ: `string|null`
  - Opis: Ostatnia wprowadzona nazwa użytkownika (nieużywane w MVP, ale może być przekazane)
- `expired` (bool)
  - Typ: `boolean`
  - Opis: Flaga wskazująca wygaśnięcie sesji (z parametru URL `?expired=1`)

## 6. Zarządzanie stanem

### 6.1. Stan formularza

**Frontend (JavaScript, opcjonalnie):**
- Stan walidacji pola hasła (valid/invalid)
- Stan przetwarzania formularza (loading/submitted)
- Stan wyświetlania alertów (visible/hidden)

**Backend (Symfony):**
- Stan sesji użytkownika (authenticated/unauthenticated)
- Stan formularza Symfony (submitted/not submitted, valid/invalid)
- Stan błędów autoryzacji (error/no error)

### 6.2. Przechowywanie stanu

- **Sesja Symfony:** Przechowywana po stronie serwera, zarządzana przez Symfony Security Component
- **Parametry URL:** `?expired=1` dla komunikatu o wygaśnięciu sesji
- **Flash messages (opcjonalnie):** Komunikaty błędów przekazywane przez sesję Symfony

### 6.3. Przekazywanie stanu między requestami

- **GET `/login`:** Renderowanie formularza z opcjonalnymi alertami
- **POST `/login`:** Przetworzenie formularza, walidacja, przekierowanie lub wyświetlenie błędu
- **Przekierowania:** Symfony Security automatycznie przekierowuje nieautoryzowanych użytkowników do `/login?expired=1` (jeśli sesja wygasła)

## 7. Integracja API

### 7.1. Endpoint POST /api/login

**Opis:** Endpoint do autoryzacji użytkownika przez hasło z `.env`.

**Typ żądania:**
- **Method:** POST
- **Content-Type:** `application/json` (jeśli używamy AJAX) lub `application/x-www-form-urlencoded` (dla tradycyjnego formularza HTML)
- **Body:**
  ```json
  {
    "password": "string"
  }
  ```

**Typ odpowiedzi:**
- **Success (200 OK):**
  ```json
  {
    "success": true,
    "message": "Authentication successful"
  }
  ```
- **Error (401 Unauthorized):**
  ```json
  {
    "success": false,
    "error": "Invalid password"
  }
  ```
- **Error (400 Bad Request):**
  ```json
  {
    "success": false,
    "error": "Password is required"
  }
  ```

**Implementacja:**
- **Opcja 1 (Tradycyjny formularz HTML):** Formularz Symfony przetwarzany przez kontroler, bezpośrednia integracja z Symfony Security Component (zalecane dla MVP)
- **Opcja 2 (AJAX):** Fetch/AJAX request do `/api/login`, obsługa odpowiedzi w JavaScript, przekierowanie po sukcesie

**Uwagi:**
- W MVP zalecane jest użycie tradycyjnego formularza HTML z Symfony Security Component (bez AJAX)
- Sesja jest tworzona automatycznie przez Symfony Security po udanym logowaniu
- Cookie sesji jest ustawiane w nagłówku `Set-Cookie` (HTTP-only, secure w produkcji)

---

### 7.2. Endpoint POST /api/logout (dla przyszłości)

**Opis:** Endpoint do wylogowania użytkownika (nie używany w widoku logowania, ale dostępny w dashboardzie).

**Typ żądania:**
- **Method:** POST
- **Authentication:** Wymagana (session-based)

**Typ odpowiedzi:**
- **Success (200 OK):**
  ```json
  {
    "success": true,
    "message": "Logged out successfully"
  }
  ```

---

### 7.3. Obsługa błędów API

**Scenariusze błędów:**
1. **401 Unauthorized:** Nieprawidłowe hasło
   - Wyświetlenie alert-danger z komunikatem "Nieprawidłowe hasło. Spróbuj ponownie."
2. **400 Bad Request:** Brak hasła lub nieprawidłowy format
   - Wyświetlenie alert-danger z komunikatem "Hasło jest wymagane."
3. **500 Internal Server Error:** Błąd serwera
   - Wyświetlenie alert-danger z komunikatem "Wystąpił błąd serwera. Spróbuj ponownie później."

**Implementacja obsługi błędów:**
- Błędy są obsługiwane przez Symfony Security Component i przekazywane do template przez zmienną `error`
- W przypadku użycia AJAX, błędy są obsługiwane w JavaScript i wyświetlane jako alerty Bootstrap

## 8. Interakcje użytkownika

### 8.1. Wejście na stronę logowania

**Akcja użytkownika:** Użytkownik odwiedza `/login` (bezpośrednio lub przez przekierowanie z `/` lub `/dashboard`)

**Oczekiwany wynik:**
- Wyświetlenie formularza logowania z tytułem aplikacji
- Jeśli parametr URL `?expired=1` jest obecny, wyświetlenie alert-info z komunikatem "Sesja wygasła. Zaloguj się ponownie, aby kontynuować."
- Pole hasła jest puste i gotowe do wprowadzenia danych
- Focus automatycznie ustawiony na pole hasła (dla lepszej dostępności)

---

### 8.2. Wprowadzenie hasła

**Akcja użytkownika:** Użytkownik wprowadza hasło w pole input

**Oczekiwany wynik:**
- Hasło jest ukryte (kropki lub gwiazdki)
- Pole input pozostaje aktywne i gotowe do dalszej edycji
- Brak walidacji w czasie rzeczywistym (walidacja tylko po submit)

---

### 8.3. Przesłanie formularza (Enter lub kliknięcie "Zaloguj")

**Akcja użytkownika:** Użytkownik klika przycisk "Zaloguj" lub naciska Enter w polu hasła

**Oczekiwany wynik:**
- Formularz jest przesyłany do endpointu POST `/login` (lub kontrolera Symfony)
- Jeśli hasło jest puste, wyświetlenie komunikatu błędu (frontend lub backend)
- Jeśli hasło jest nieprawidłowe, wyświetlenie alert-danger z komunikatem błędu
- Jeśli hasło jest prawidłowe:
  - Utworzenie sesji przez Symfony Security
  - Przekierowanie do `/dashboard` (lub `/` jako domyślna)
  - Użytkownik jest zalogowany i ma dostęp do dashboardu

---

### 8.4. Obsługa błędów autoryzacji

**Akcja użytkownika:** Użytkownik wprowadza nieprawidłowe hasło i przesyła formularz

**Oczekiwany wynik:**
- Wyświetlenie alert-danger z komunikatem "Nieprawidłowe hasło. Spróbuj ponownie."
- Pole hasła pozostaje puste (ze względów bezpieczeństwa)
- Formularz pozostaje aktywny, gotowy do ponownej próby
- Focus pozostaje na polu hasła lub przycisku submit (dla dostępności)

---

### 8.5. Zamykanie alertu (jeśli alert-dismissible)

**Akcja użytkownika:** Użytkownik klika przycisk zamykający alert (X)

**Oczekiwany wynik:**
- Alert jest ukrywany (animacja fade-out Bootstrap)
- Formularz pozostaje widoczny i aktywny

---

### 8.6. Nawigacja klawiaturą

**Akcja użytkownika:** Użytkownik używa klawiatury do nawigacji (Tab, Enter, Escape)

**Oczekiwany wynik:**
- **Tab:** Przechodzenie między elementami formularza (pole hasła → przycisk "Zaloguj")
- **Enter:** Przesłanie formularza (gdy focus na polu hasła lub przycisku)
- **Escape:** Zamykanie alertu (jeśli alert-dismissible)
- Focus jest wizualnie oznaczony (outline Bootstrap)

## 9. Warunki i walidacja

### 9.1. Warunki frontendowe (opcjonalne)

#### Sprawdzanie pustego hasła

**Komponent:** Password Input Group

**Warunek:** Pole hasła nie może być puste przed przesłaniem formularza

**Walidacja:**
- Sprawdzenie `value.length === 0` przed submit (JavaScript, opcjonalnie)
- Wyświetlenie komunikatu błędu HTML5 (jeśli `required` jest ustawione)
- Wyświetlenie komunikatu błędu Bootstrap (jeśli walidacja JavaScript)

**Wpływ na stan interfejsu:**
- Pole input otrzymuje klasę `is-invalid` (Bootstrap)
- Wyświetlenie `<div class="invalid-feedback">` z komunikatem "Hasło jest wymagane."
- Przycisk submit może być disabled (opcjonalnie)

---

### 9.2. Warunki backendowe (wymagane)

#### Walidacja CSRF token

**Komponent:** Login Form

**Warunek:** CSRF token musi być obecny i poprawny w żądaniu POST

**Walidacja:**
- Automatyczna walidacja przez Symfony Security Component
- Token jest generowany przy renderowaniu formularza i walidowany przy submit

**Wpływ na stan interfejsu:**
- Jeśli token jest nieprawidłowy, formularz zwraca błąd 403 Forbidden
- Wyświetlenie ogólnego komunikatu błędu (bez szczegółów ze względów bezpieczeństwa)

---

#### Walidacja hasła

**Komponent:** Login Form, Password Input

**Warunek:** Hasło musi być wypełnione i zgodne z wartością z `.env` (np. `APP_PASSWORD`)

**Walidacja:**
- Sprawdzenie czy pole `password` nie jest puste (`NotBlank` constraint)
- Porównanie hasła z wartością z `.env` przez Symfony Security
- Porównanie jest case-sensitive

**Wpływ na stan interfejsu:**
- Jeśli hasło jest puste: Wyświetlenie alert-danger z komunikatem "Hasło jest wymagane."
- Jeśli hasło jest nieprawidłowe: Wyświetlenie alert-danger z komunikatem "Nieprawidłowe hasło. Spróbuj ponownie."
- Jeśli hasło jest prawidłowe: Przekierowanie do dashboardu, utworzenie sesji

---

#### Sprawdzanie wygaśnięcia sesji

**Komponent:** Alert Section

**Warunek:** Parametr URL `?expired=1` wskazuje na wygaśnięcie sesji

**Walidacja:**
- Sprawdzenie obecności parametru `expired` w URL (GET request)
- Jeśli `expired=1`, wyświetlenie alert-info

**Wpływ na stan interfejsu:**
- Wyświetlenie alert-info z komunikatem "Sesja wygasła. Zaloguj się ponownie, aby kontynuować."
- Formularz pozostaje aktywny i gotowy do logowania

---

### 9.3. Warunki dostępności

#### Zarządzanie focusem

**Komponent:** Wszystkie interaktywne elementy

**Warunek:** Focus musi być prawidłowo zarządzany dla dostępności klawiaturą

**Walidacja:**
- Po załadowaniu strony, focus automatycznie ustawiony na pole hasła
- Po błędzie walidacji, focus pozostaje na polu hasła lub przycisku submit
- Po zamknięciu alertu, focus wraca do formularza

**Wpływ na stan interfejsu:**
- Wizualny outline na aktywnym elemencie (Bootstrap focus styles)
- Keyboard navigation działa poprawnie

---

#### ARIA attributes

**Komponent:** Password Input, Submit Button, Alert Section

**Warunek:** Wszystkie interaktywne elementy muszą mieć odpowiednie atrybuty ARIA

**Walidacja:**
- `aria-label` dla pól input i przycisków
- `aria-describedby` dla pól z komunikatami błędów
- `aria-invalid="true"` przy błędach walidacji
- `role="alert"` dla komunikatów błędów

**Wpływ na stan interfejsu:**
- Czytniki ekranu poprawnie odczytują etykiety i komunikaty błędów
- Użytkownicy z niepełnosprawnościami mają pełny dostęp do formularza

## 10. Obsługa błędów

### 10.1. Błędy autoryzacji

#### Nieprawidłowe hasło

**Scenariusz:** Użytkownik wprowadza nieprawidłowe hasło i przesyła formularz

**Obsługa:**
- Symfony Security zwraca `AuthenticationException`
- Kontroler przekazuje błąd do template przez zmienną `error`
- Template wyświetla alert-danger z komunikatem "Nieprawidłowe hasło. Spróbuj ponownie."
- Pole hasła jest czyszczone (ze względów bezpieczeństwa)
- Formularz pozostaje aktywny, gotowy do ponownej próby

**Komunikat błędu:** "Nieprawidłowe hasło. Spróbuj ponownie."

---

#### Puste hasło

**Scenariusz:** Użytkownik przesyła formularz bez wprowadzenia hasła

**Obsługa:**
- Walidacja Symfony (`NotBlank` constraint) zwraca błąd
- Template wyświetla alert-danger z komunikatem "Hasło jest wymagane."
- Pole hasła otrzymuje klasę `is-invalid` (Bootstrap)
- Wyświetlenie komunikatu błędu pod polem input

**Komunikat błędu:** "Hasło jest wymagane."

---

### 10.2. Błędy techniczne

#### Błąd CSRF token

**Scenariusz:** CSRF token jest nieprawidłowy lub wygasł

**Obsługa:**
- Symfony Security zwraca błąd 403 Forbidden
- Wyświetlenie ogólnego komunikatu błędu (bez szczegółów ze względów bezpieczeństwa)
- Użytkownik musi odświeżyć stronę i spróbować ponownie

**Komunikat błędu:** "Wystąpił błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie."

---

#### Błąd serwera (500)

**Scenariusz:** Wystąpił błąd serwera podczas przetwarzania formularza

**Obsługa:**
- Symfony zwraca błąd 500 Internal Server Error
- Wyświetlenie alert-danger z komunikatem o błędzie serwera
- Logowanie błędu przez Monolog (poziom Error)
- Użytkownik może spróbować ponownie

**Komunikat błędu:** "Wystąpił błąd serwera. Spróbuj ponownie później."

---

### 10.3. Błędy sesji

#### Wygaśnięcie sesji

**Scenariusz:** Sesja użytkownika wygasła (30 minut bezczynności)

**Obsługa:**
- Symfony Security wykrywa wygaśnięcie sesji
- Automatyczne przekierowanie do `/login?expired=1`
- Template wyświetla alert-info z komunikatem o wygaśnięciu sesji
- Formularz pozostaje aktywny, gotowy do ponownego logowania

**Komunikat:** "Sesja wygasła. Zaloguj się ponownie, aby kontynuować."

---

### 10.4. Przypadki brzegowe

#### Równoczesne przesyłanie formularza (double submit)

**Scenariusz:** Użytkownik klika przycisk "Zaloguj" wielokrotnie szybko

**Obsługa:**
- Przycisk submit jest disabled po pierwszym kliknięciu (opcjonalnie, JavaScript)
- Symfony Security obsługuje tylko pierwsze żądanie
- Kolejne żądania są ignorowane lub zwracają błąd

---

#### Brak połączenia z serwerem

**Scenariusz:** Użytkownik traci połączenie z serwerem podczas logowania

**Obsługa:**
- Wyświetlenie komunikatu błędu sieciowego (jeśli używamy AJAX)
- Dla tradycyjnego formularza HTML, przeglądarka wyświetli własny komunikat błędu
- Użytkownik może spróbować ponownie po przywróceniu połączenia

**Komunikat błędu:** "Brak połączenia z serwerem. Sprawdź połączenie internetowe i spróbuj ponownie."

---

#### Nieprawidłowy format odpowiedzi API

**Scenariusz:** Serwer zwraca nieprawidłowy format JSON (jeśli używamy AJAX)

**Obsługa:**
- Obsługa wyjątku parsowania JSON w JavaScript
- Wyświetlenie ogólnego komunikatu błędu
- Logowanie błędu w konsoli przeglądarki (development)

**Komunikat błędu:** "Wystąpił błąd podczas przetwarzania odpowiedzi serwera. Spróbuj ponownie."

## 11. Kroki implementacji

### Krok 1: Utworzenie kontrolera LoginController

1. Utworzenie pliku `src/Controller/LoginController.php`
2. Implementacja metody `login()` dla GET request (renderowanie formularza)
3. Implementacja metody `loginPost()` dla POST request (przetworzenie formularza) lub użycie Symfony Security form login
4. Konfiguracja routingu w `config/routes.yaml` lub przez atrybuty PHP 8

**Szczegóły:**
- Kontroler powinien używać Symfony Security Component do autoryzacji
- Metoda `login()` przekazuje formularz i opcjonalne błędy do template
- Metoda `loginPost()` może być pominięta jeśli używamy Symfony Security form login (automatyczna obsługa)

---

### Krok 2: Utworzenie formularza LoginFormType

1. Utworzenie pliku `src/Form/LoginFormType.php`
2. Definicja pól formularza:
   - Pole `_token` (CSRF, ukryte)
   - Pole `password` (PasswordType)
3. Konfiguracja opcji formularza (label, required, attr)

**Szczegóły:**
- Formularz powinien rozszerzać `AbstractType`
- Pole `password` powinno mieć `mapped: false` (nie mapowane do encji)
- Atrybuty dostępności (aria-label, autocomplete) powinny być ustawione w opcjach `attr`

---

### Krok 3: Konfiguracja Symfony Security

1. Konfiguracja `config/packages/security.yaml`
2. Definicja firewall dla `/login` i `/api/login`
3. Konfiguracja providera autoryzacji (password z `.env`)
4. Konfiguracja form login (jeśli używamy automatycznej obsługi)

**Szczegóły:**
- Firewall powinien zezwalać na dostęp publiczny do `/login`
- Provider powinien używać hasła z `.env` (np. `APP_PASSWORD`)
- Timeout sesji: 30 minut bezczynności
- Konfiguracja CSRF protection

---

### Krok 4: Utworzenie template Twig

1. Utworzenie pliku `templates/login/index.html.twig`
2. Implementacja struktury HTML z Bootstrap 5:
   - Header section z tytułem "Server Monitor"
   - Alert section (warunkowy) dla komunikatów błędów i informacji
   - Login form z polami i przyciskiem submit
3. Integracja z formularzem Symfony (`form_start`, `form_widget`, `form_end`)
4. Implementacja alertów Bootstrap (alert-danger, alert-info)

**Szczegóły:**
- Template powinien używać base template (`base.html.twig`) jeśli dostępny
- Alert-info powinien być wyświetlany tylko gdy parametr URL `expired=1` jest obecny
- Alert-danger powinien być wyświetlany tylko gdy zmienna `error` jest ustawiona
- Wszystkie elementy powinny mieć odpowiednie atrybuty dostępności (ARIA)

---

### Krok 5: Implementacja stylów CSS (opcjonalnie)

1. Utworzenie pliku `assets/styles/login.css` (jeśli używamy Webpack Encore)
2. Definicja stylów dla centrowanego formularza
3. Definicja stylów dla responsywnego layoutu (mobile, tablet, desktop)

**Szczegóły:**
- Formularz powinien być wyśrodkowany na stronie (vertical i horizontal)
- Responsywność powinna być zapewniona przez Bootstrap 5 grid system
- Opcjonalnie: custom styles dla lepszego UX

---

### Krok 6: Implementacja JavaScript dla dostępności (opcjonalnie)

1. Utworzenie pliku `assets/js/login.js` (jeśli używamy Webpack Encore)
2. Implementacja automatycznego ustawienia focusu na pole hasła po załadowaniu strony
3. Implementacja obsługi klawisza Enter do submit formularza
4. Implementacja zarządzania focusem przy błędach walidacji

**Szczegóły:**
- JavaScript powinien być opcjonalny (formularz działa bez JavaScript)
- Focus management powinien być zgodny z wytycznymi dostępności WCAG 2.1
- Obsługa klawiatury powinna być intuicyjna

---

### Krok 7: Konfiguracja routingu

1. Definicja routingu w `config/routes.yaml` lub przez atrybuty PHP 8 w kontrolerze
2. Konfiguracja routy `app_login` dla GET `/login`
3. Konfiguracja routy `app_login_post` dla POST `/login` (jeśli nie używamy automatycznej obsługi Symfony Security)

**Szczegóły:**
- Routing powinien być zgodny z konwencjami Symfony
- Nazwy rout powinny być opisowe i spójne z resztą aplikacji

---

### Krok 8: Testowanie widoku

1. Testowanie renderowania formularza (GET `/login`)
2. Testowanie logowania z prawidłowym hasłem
3. Testowanie logowania z nieprawidłowym hasłem
4. Testowanie logowania z pustym hasłem
5. Testowanie wygaśnięcia sesji (`/login?expired=1`)
6. Testowanie dostępności (keyboard navigation, ARIA attributes)
7. Testowanie responsywności (mobile, tablet, desktop)

**Szczegóły:**
- Testy powinny obejmować wszystkie scenariusze z sekcji "Obsługa błędów"
- Testy dostępności powinny być wykonane z użyciem czytników ekranu (opcjonalnie)
- Testy responsywności powinny być wykonane na różnych rozdzielczościach ekranu

---

### Krok 9: Integracja z dashboardem

1. Sprawdzenie czy przekierowanie po udanym logowaniu działa poprawnie (`/dashboard` lub `/`)
2. Sprawdzenie czy nieautoryzowani użytkownicy są przekierowywani do `/login`
3. Sprawdzenie czy wygaśnięcie sesji w dashboardzie przekierowuje do `/login?expired=1`

**Szczegóły:**
- Integracja powinna być zgodna z konfiguracją Symfony Security
- Przekierowania powinny zachowywać parametry URL jeśli potrzebne

---

### Krok 10: Dokumentacja i finalizacja

1. Aktualizacja dokumentacji projektu (jeśli potrzebna)
2. Sprawdzenie zgodności z PRD i User Stories
3. Code review i refaktoryzacja jeśli potrzebna
4. Finalne testy end-to-end

**Szczegóły:**
- Dokumentacja powinna zawierać instrukcje konfiguracji hasła w `.env`
- Code review powinien sprawdzić bezpieczeństwo (CSRF, XSS, session management)
- Finalne testy powinny potwierdzić wszystkie wymagania z PRD

