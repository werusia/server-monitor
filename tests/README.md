# Testing Guide

This document provides information about the testing setup and how to run tests in this project.

## Test Structure

The project uses two main testing frameworks:

- **PHPUnit 12.4** - For unit and integration tests (PHP/Backend)
- **Cypress** - For end-to-end tests (E2E)

## Directory Structure

```
tests/
├── Unit/              # Unit tests (isolated, no database/network)
│   └── Service/       # Service unit tests
├── Integration/       # Integration tests (with database)
└── bootstrap.php      # PHPUnit bootstrap file

cypress/
├── e2e/              # End-to-end test files
├── fixtures/         # Test data fixtures
├── support/          # Custom commands and utilities
│   ├── commands.js   # Custom Cypress commands
│   └── e2e.js        # Global test configuration
├── downloads/        # Downloaded files (gitignored)
├── screenshots/      # Test failure screenshots (gitignored)
└── videos/           # Test execution videos (gitignored)
```

## PHPUnit Tests

### Running Tests

```bash
# Run all tests
ddev composer test

# Run only unit tests
ddev composer test:unit

# Run only integration tests
ddev composer test:integration

# Run with coverage report
ddev composer test:coverage
# Coverage report will be available in var/coverage/
```

### Writing Unit Tests

Unit tests should:
- Be isolated (no database, no network calls)
- Mock all external dependencies
- Test one class at a time
- Use `declare(strict_types=1)` at the top
- Follow Arrange-Act-Assert (AAA) pattern

**Example:**
```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MyService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    #[Test]
    public function testMethodWithValidInputReturnsExpectedResult(): void
    {
        // Arrange
        $service = new MyService($this->createMock(Dependency::class));
        
        // Act
        $result = $service->method('input');
        
        // Assert
        $this->assertSame('expected', $result);
    }
}
```

### Writing Integration Tests

Integration tests should:
- Use `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` as base class
- Test interactions between components
- May use a test database

## Cypress E2E Tests

### Prerequisites

1. Install Node.js dependencies:
```bash
npm install
```

2. Make sure your Symfony application is running:
```bash
ddev start
```

   The application should be available at `https://server-monitor.ddev.site:33003`
   (or check your DDEV configuration for the correct URL).

### Running Tests

```bash
# Run all E2E tests in headless mode
npm run test:e2e

# Open Cypress Test Runner (interactive)
npm run test:e2e:open

# Run tests in specific browser
npm run test:e2e:chrome
npm run test:e2e:firefox
```

### Writing E2E Tests

E2E tests should:
- Test from user's perspective
- Use `data-cy` attributes for element selection
- Use fixtures for test data
- Use custom commands for common actions

**Example:**
```javascript
describe('Login', () => {
  it('should log in successfully', () => {
    cy.visit('/login');
    cy.getByDataCy('email').type('user@example.com');
    cy.getByDataCy('password').type('password');
    cy.getByDataCy('submit').click();
    cy.url().should('include', '/dashboard');
  });
});
```

### Custom Commands

Custom commands are available in `cypress/support/commands.js`:
- `cy.login(email, password)` - Login via API
- `cy.visitAndWait(url)` - Visit page and wait for load
- `cy.getByDataCy(value)` - Get element by data-cy attribute
- `cy.waitForApi(method, url)` - Wait for API call

## Best Practices

### PHPUnit

1. **One assertion per test** when possible
2. **Use specific assertions**: `assertSame()` over `assertEquals()`
3. **Test edge cases**: null, empty, boundary values
4. **Mock interfaces** when possible
5. **Keep tests fast**: unit tests should run in < 1 second

### Cypress

1. **Use data-cy attributes** for element selection
2. **Avoid fixed timeouts**: use `cy.intercept()` for API calls
3. **Keep tests independent**: each test should set up its own state
4. **Use fixtures** instead of hardcoding data
5. **Test user flows**, not implementation details

## CI/CD Integration

Tests should be run automatically in CI/CD pipeline:

```yaml
# Example GitHub Actions
- name: Run PHPUnit tests
  run: composer test

- name: Run Cypress tests
  run: npm run test:e2e
```

## Troubleshooting

### PHPUnit Issues

- **Tests failing with database errors**: Make sure test environment is configured correctly
- **Autoload issues**: Run `composer dump-autoload`

### Cypress Issues

- **Tests timing out**: Check if application is running on correct port
- **Element not found**: Verify `data-cy` attributes are present in HTML
- **Base URL issues**: Update `baseUrl` in `cypress.config.js` to match your DDEV URL
- **SSL certificate errors**: The config has `chromeWebSecurity: false` to handle DDEV's self-signed certificates
- **Server not running**: Make sure DDEV is running with `ddev start` and verify the URL in `cypress.config.js`

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Cypress Documentation](https://docs.cypress.io/)
- [Symfony Testing Guide](https://symfony.com/doc/current/testing.html)

