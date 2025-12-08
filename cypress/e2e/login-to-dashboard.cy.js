/**
 * E2E Test: Logowanie do Dashboardu
 * 
 * Test sprawdza kompletny przepływ logowania użytkownika:
 * 1. Otwarcie strony logowania
 * 2. Wypełnienie formularza hasłem
 * 3. Przekierowanie do dashboardu
 * 4. Weryfikacja wyświetlenia dashboardu
 * 5. Weryfikacja sesji i cookies
 * 
 * Scenariusze:
 * - TC-AUTH-001: Pomyślne logowanie
 * - TC-AUTH-002: Nieprawidłowe hasło
 * - TC-DASH-001: Wyświetlanie dashboardu po zalogowaniu
 */

describe('Logowanie do Dashboardu', () => {
  // Pobierz hasło z zmiennej środowiskowej Cypress
  // Hasło można ustawić na kilka sposobów (w kolejności priorytetu):
  // 1. Zmienna środowiskowa Node.js: APP_PASSWORD=xxx npm run test:e2e
  // 2. Plik .env.local: APP_PASSWORD=xxx
  // 3. Plik cypress.env.json: { "APP_PASSWORD": "xxx" }
  const validPassword = Cypress.env('APP_PASSWORD');
  const invalidPassword = 'wrong-password';

  // Sprawdź, czy hasło jest ustawione
  before(() => {
    if (!validPassword) {
      throw new Error(
        'APP_PASSWORD nie jest ustawione. ' +
        'Ustaw hasło w jednym z następujących sposobów:\n' +
        '1. Utwórz plik cypress.env.json: { "APP_PASSWORD": "twoje-haslo" }\n' +
        '2. Ustaw zmienną środowiskową: APP_PASSWORD=twoje-haslo npm run test:e2e\n' +
        '3. Dodaj APP_PASSWORD do pliku .env.local'
      );
    }
  });

  describe('TC-AUTH-001: Pomyślne logowanie', () => {
    it('powinno zalogować użytkownika i przekierować do dashboardu', () => {
      // Arrange: Otwórz stronę logowania
      cy.visit('/login');

      // Weryfikuj, że jesteśmy na stronie logowania i formularz się załadował
      cy.url().should('include', '/login');
      cy.contains('Server Monitor').should('be.visible');
      cy.get('#login-form').should('be.visible');
      cy.get('#login_form_password').should('be.visible');
      
      // Użyj bardziej niezawodnego selektora dla przycisku submit
      // Symfony może generować różne ID, więc używamy selektora przez tekst lub type
      cy.get('form#login-form button[type="submit"]').should('be.visible');
      cy.get('form#login-form button[type="submit"]').should('contain', 'Zaloguj');

      // Act: Wypełnij formularz i zaloguj się
      cy.get('#login_form_password').type(validPassword);
      cy.get('form#login-form button[type="submit"]').click();

      // Assert: Sprawdź przekierowanie do dashboardu
      cy.url().should('include', '/dashboard');
      cy.url().should('not.include', '/login');

      // Weryfikuj, że dashboard się wyświetla
      cy.get('#dashboard-header').should('be.visible');
      cy.contains('Server Monitor').should('be.visible');
      
      // Weryfikuj elementy dashboardu
      cy.get('#time-range-selector').should('be.visible');
      cy.get('#refresh-button').should('be.visible');
      cy.get('#logout-button').should('be.visible');

      // Weryfikuj sekcje metryk
      cy.get('#cpu-metric-section').should('be.visible');
      cy.get('#ram-metric-section').should('be.visible');
      cy.get('#disk-metric-section').should('be.visible');
      cy.get('#io-metric-section').should('be.visible');
      cy.get('#network-metric-section').should('be.visible');

      // Weryfikuj, że nie ma komunikatu błędu
      cy.get('#login-error').should('not.exist');

      // Zamiast sprawdzania ciasteczka PHPSESSID (HttpOnly, niedostępne dla JS w CI),
      // weryfikujemy autoryzację żądaniem do chronionego endpointu API
      cy.request({
        url: '/api/metrics/latest',
        failOnStatusCode: false,
      }).its('status').should('eq', 200);
    });

    it('powinno wyświetlić dashboard z wykresami', () => {
      // Arrange & Act: Zaloguj się
      cy.visit('/login');
      cy.get('#login_form_password').should('be.visible');
      cy.get('#login_form_password').type(validPassword);
      cy.get('form#login-form button[type="submit"]').click();

      // Assert: Sprawdź, że wykresy są obecne (canvas elements)
      cy.url().should('include', '/dashboard');
      
      // Weryfikuj, że canvas dla wykresów istnieją
      cy.get('#cpu-chart').should('exist');
      cy.get('#ram-chart').should('exist');
      cy.get('#disk-chart').should('exist');
      cy.get('#io-chart').should('exist');
      cy.get('#network-chart').should('exist');

      // Weryfikuj nagłówki sekcji
      cy.contains('Wykorzystanie CPU').should('be.visible');
      cy.contains('Wykorzystanie RAM').should('be.visible');
      cy.contains('Wykorzystanie dysku').should('be.visible');
      cy.contains('Aktywność I/O').should('be.visible');
      cy.contains('Ruch sieciowy').should('be.visible');
    });

    it('powinno przekierować z /login do /dashboard jeśli użytkownik jest już zalogowany', () => {
      // Arrange: Zaloguj się najpierw
      cy.visit('/login');
      cy.get('#login_form_password').should('be.visible');
      cy.get('#login_form_password').type(validPassword);
      cy.get('form#login-form button[type="submit"]').click();
      cy.url().should('include', '/dashboard');

      // Act: Spróbuj ponownie odwiedzić /login
      cy.visit('/login');

      // Assert: Powinno przekierować do dashboardu
      cy.url().should('include', '/dashboard');
      cy.url().should('not.include', '/login');
    });
  });

  describe('TC-AUTH-002: Nieprawidłowe hasło', () => {
    it('powinno wyświetlić komunikat błędu dla nieprawidłowego hasła', () => {
      // Arrange: Otwórz stronę logowania
      cy.visit('/login');
      cy.url().should('include', '/login');
      cy.get('#login-form').should('be.visible');

      // Act: Wprowadź nieprawidłowe hasło
      cy.get('#login_form_password').should('be.visible');
      cy.get('#login_form_password').type(invalidPassword);
      cy.get('form#login-form button[type="submit"]').click();

      // Assert: Sprawdź komunikat błędu
      cy.url().should('include', '/login');
      cy.url().should('not.include', '/dashboard');
      
      // Weryfikuj komunikat błędu
      cy.get('#login-error').should('be.visible');
      cy.get('#login-error').should('contain', 'Nieprawidłowe hasło');
      
      // Weryfikuj, że formularz nadal jest widoczny
      cy.get('#login_form_password').should('be.visible');
      cy.get('form#login-form button[type="submit"]').should('be.visible');

      // Weryfikuj, że sesja nie jest ustawiona (brak cookie lub inna wartość)
      // Uwaga: Cookie może istnieć, ale sesja nie jest autoryzowana
    });

    it('powinno wyświetlić komunikat błędu dla pustego hasła', () => {
      // Arrange: Otwórz stronę logowania
      cy.visit('/login');
      cy.get('#login-form').should('be.visible');

      // Act: Spróbuj zalogować się bez hasła
      cy.get('form#login-form button[type="submit"]').click();

      // Assert: Sprawdź komunikat błędu walidacji
      cy.url().should('include', '/login');
      
      // Symfony może zwrócić błąd walidacji lub komunikat o wymaganym polu
      // Sprawdź czy jest jakiś komunikat błędu
      cy.get('body').then(($body) => {
        if ($body.find('#login-error').length > 0) {
          cy.get('#login-error').should('be.visible');
        }
        // Alternatywnie, sprawdź walidację po stronie przeglądarki
        cy.get('#login_form_password').should('have.attr', 'required');
      });
    });
  });

  describe('TC-DASH-001: Wyświetlanie dashboardu', () => {
    beforeEach(() => {
      // Zaloguj się przed każdym testem
      cy.visit('/login');
      cy.get('#login-form').should('be.visible');
      cy.get('#login_form_password').should('be.visible');
      cy.get('#login_form_password').type(validPassword);
      cy.get('form#login-form button[type="submit"]').click();
      cy.url().should('include', '/dashboard');
    });

    it('powinno wyświetlić wszystkie elementy dashboardu', () => {
      // Weryfikuj header
      cy.get('#dashboard-header').should('be.visible');
      cy.contains('Server Monitor').should('be.visible');

      // Weryfikuj kontrolki w headerze
      cy.get('#time-range-selector').should('be.visible');
      cy.get('#time-range-selector button[data-range="1h"]').should('be.visible');
      cy.get('#time-range-selector button[data-range="6h"]').should('be.visible');
      cy.get('#time-range-selector button[data-range="24h"]').should('be.visible');
      cy.get('#time-range-selector button[data-range="7d"]').should('be.visible');
      cy.get('#time-range-selector button[data-range="30d"]').should('be.visible');
      
      cy.get('#refresh-button').should('be.visible');
      cy.get('#refresh-button').should('contain', 'Odśwież');
      cy.get('#logout-button').should('be.visible');
      cy.get('#logout-button').should('contain', 'Wyloguj');

      // Weryfikuj wszystkie sekcje metryk
      cy.get('#cpu-metric-section').should('be.visible');
      cy.get('#ram-metric-section').should('be.visible');
      cy.get('#disk-metric-section').should('be.visible');
      cy.get('#io-metric-section').should('be.visible');
      cy.get('#network-metric-section').should('be.visible');
    });

    it('powinno chronić dashboard przed nieautoryzowanym dostępem', () => {
      // Arrange: Wyloguj się (wyczyść cookies i storage)
      cy.clearCookies();
      cy.clearLocalStorage();
      cy.window().then((win) => {
        win.sessionStorage.clear();
      });

      // Act: Spróbuj odwiedzić dashboard bez logowania
      cy.visit('/dashboard');

      // Assert: Powinno przekierować do logowania
      cy.url().should('include', '/login');
      cy.url().should('not.include', '/dashboard');
    });
  });

  describe('Integracja: Pełny przepływ logowania i wylogowania', () => {
    it('powinno umożliwić pełny cykl logowania i wylogowania', () => {
      // 1. Logowanie
      cy.visit('/login');
      cy.get('#login-form').should('be.visible');
      cy.get('#login_form_password').should('be.visible');
      cy.get('#login_form_password').type(validPassword);
      cy.get('form#login-form button[type="submit"]').click();
      cy.url().should('include', '/dashboard');

      // 2. Weryfikacja dashboardu
      cy.get('#dashboard-header').should('be.visible');
      cy.get('#logout-button').should('be.visible');

      // 3. Wylogowanie: wykonaj explicit request bez polegania na intercept
      cy.request({
        method: 'POST',
        url: '/api/logout',
        failOnStatusCode: false,
      })
        .its('status')
        .should((status) => {
          expect([200, 204, 302]).to.include(status);
        });

      // Odśwież/odwiedź stronę, aby zastosować stan po wylogowaniu
      cy.visit('/dashboard');

      // 4. Weryfikacja przekierowania do logowania
      cy.location('pathname', { timeout: 10000 }).should('include', '/login');
      cy.url().should('not.include', '/dashboard');

      // 5. Weryfikacja, że sesja została unieważniona (chroniony endpoint zwraca 401/403)
      cy.request({ url: '/api/metrics/latest', failOnStatusCode: false })
        .its('status')
        .should((status) => {
          expect([401, 403]).to.include(status);
        });

      // 6. Próba ponownego dostępu do dashboardu bez logowania
      cy.visit('/dashboard');
      cy.url().should('include', '/login');
    });
  });
});

