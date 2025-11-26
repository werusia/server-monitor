# Server Monitor

A lightweight MVP application for real-time monitoring of a single Linux server (Ubuntu/Debian) via SSH. The application collects system metrics and presents them through an interactive web dashboard with charts.

## Project Description

Server Monitor is designed for system administrators who need a simple and reliable tool to monitor Linux servers without installing agents on the monitored server. The application uses SSH connections to collect metrics and stores them in a MySQL database, providing a web-based dashboard for visualization.

### Key Features

- **Agentless Monitoring**: No need to install agents on the monitored server - uses SSH connections
- **Real-time Metrics**: Collects CPU, RAM, Disk, I/O, and Network metrics every minute
- **Interactive Dashboard**: Web-based dashboard with Chart.js visualizations
- **Responsive Design**: Full support for mobile and desktop devices
- **Data Retention**: Automatic cleanup of metrics older than 90 days
- **Password Authentication**: Simple password-based access control
- **Error Handling**: Robust SSH error handling with retry logic and logging

### Architecture

The application consists of two main components:

1. **Backend (Symfony 7.3)**: Console commands that collect metrics via SSH and store them in MySQL
2. **Frontend**: Web dashboard with interactive charts displaying metrics in various time ranges

## Tech Stack

- **Backend Framework**: Symfony 7.3
- **PHP**: 8.3 (minimum 8.2)
- **Database**: MySQL 8.0+
- **Template Engine**: Twig
- **Charts**: Chart.js
- **CSS Framework**: Bootstrap 5
- **SSH Library**: phpseclib/phpseclib ^3.0
- **Logging**: Monolog
- **Testing**: 
  - **Unit & Integration Tests**: PHPUnit ^12.4
  - **E2E Tests**: Cypress
  - **Static Analysis**: PHPStan / Psalm
  - **Code Style**: PHP CS Fixer
- **ORM**: Doctrine ORM ^3.5

## Getting Started Locally

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL 8.0+
- DDEV (for local development)
- SSH access to the server you want to monitor

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd server-monitor
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment variables**
   
   Create a `.env.local` file based on `.env.example` (if available) with the following required variables:
   
   ```env
   # Database Configuration
   DATABASE_URL="mysql://user:password@127.0.0.1:3306/server_monitor?serverVersion=8.0"
   
   # SSH Configuration
   SSH_HOST=your-server-ip-or-hostname
   SSH_PORT=22
   SSH_USERNAME=your-ssh-username
   SSH_PRIVATE_KEY=base64-encoded-private-key
   
   # Dashboard Authentication
   DASHBOARD_PASSWORD=your-secure-password
   
   # Application Environment
   APP_ENV=dev
   APP_SECRET=your-app-secret
   ```

   **Note**: The SSH private key must be base64-encoded. You can encode it using:
   ```bash
   base64 -i ~/.ssh/id_rsa
   ```

4. **Set up the database**
   ```bash
   ddev exec php bin/console doctrine:database:create
   ddev exec php bin/console doctrine:migrations:migrate
   ```

5. **Configure cron jobs**
   
   Add the following cron jobs to collect metrics and clean up old data:
   
   ```cron
   # Collect metrics every minute
   * * * * * cd /path/to/project && ddev exec php bin/console app:collect-metrics
   
   # Clean up old metrics daily at 3:00 AM
   0 3 * * * cd /path/to/project && ddev exec php bin/console app:cleanup-old-metrics
   ```

6. **Start the development server**
   ```bash
   ddev start
   ```

   Access the application at the URL provided by DDEV (typically `https://server-monitor.ddev.site`)

### Using DDEV

This project uses DDEV for local development. All commands should be executed through DDEV:

```bash
# Run Symfony console commands
ddev exec php bin/console <command>

# Access the application
ddev launch

# View logs
ddev logs
```

## Available Scripts

### Symfony Console Commands

#### Collect Metrics
Collects server metrics via SSH and stores them in the database. Designed to run every minute via cron.

```bash
ddev exec php bin/console app:collect-metrics
```

**What it does:**
- Connects to the server via SSH using credentials from `.env`
- Collects metrics: CPU, RAM, Disk, I/O, Network
- Stores metrics in the database with timestamp
- Handles SSH errors with retry logic (2-3 attempts with exponential backoff)
- Logs errors to `var/log/` using Monolog

#### Cleanup Old Metrics
Deletes server metrics older than 90 days. Designed to run daily via cron.

```bash
ddev exec php bin/console app:cleanup-old-metrics
```

**Options:**
- `--retention-days` or `-r`: Number of days to retain (default: 90)

**Example:**
```bash
ddev exec php bin/console app:cleanup-old-metrics --retention-days=60
```

**What it does:**
- Deletes records older than the specified retention period
- Processes deletions in batches (1000 records at a time) to minimize database locking
- Logs cleanup operations

### Composer Scripts

The following scripts are automatically executed after `composer install` and `composer update`:

- `cache:clear`: Clears Symfony cache
- `assets:install`: Installs assets to the public directory
- `importmap:install`: Installs JavaScript dependencies via importmap

### Testing

The project includes comprehensive test coverage using multiple testing tools:

#### Unit and Integration Tests (PHPUnit)

Run all PHPUnit tests:
```bash
ddev exec php bin/phpunit
```

**Test Coverage:**
- Unit tests for services, controllers, repositories, and commands
- Integration tests for database operations and SSH connections
- Functional tests for API endpoints and authentication
- Uses Symfony Test Client for HTTP testing
- Separate test database for isolated testing

#### End-to-End Tests (Cypress)

Run E2E tests:
```bash
# Run tests in headless mode
npx cypress run

# Open Cypress Test Runner (interactive)
npx cypress open
```

**E2E Test Coverage:**
- User login and authentication flows
- Dashboard interactions (time range switching, data refresh)
- Error handling scenarios
- Responsive design verification

#### Code Quality Tools

- **PHPStan / Psalm**: Static code analysis for detecting potential bugs
- **PHP CS Fixer**: Automated code style formatting to maintain consistency

### Other Useful Commands

```bash
# Run database migrations
ddev exec php bin/console doctrine:migrations:migrate

# Create a new migration
ddev exec php bin/console make:migration

# Clear cache
ddev exec php bin/console cache:clear

# Run tests
ddev exec php bin/phpunit

# Run E2E tests (requires Node.js and Cypress)
npx cypress run
npx cypress open
```

### Technical Limitations

- Requires SSH access to the monitored server
- Requires MySQL 8.0+ as the database
- Only works with Linux servers that have `/proc` filesystem
- Framework: Symfony 7.3 with minimal bundles (console, doctrine/orm, security, twig)

### Current Implementation Status

- ✅ SSH metric collection
- ✅ Database storage with Doctrine ORM
- ✅ Web dashboard with Chart.js
- ✅ Password authentication
- ✅ Automated data cleanup
- ✅ Error handling and logging
- ✅ Responsive design

### Future Considerations

Potential enhancements for future versions (not in current scope):
- Multi-server support
- Alerting system
- Data export capabilities
- REST API
- Advanced user management
- Caching for improved performance
- Auto-refresh dashboard option

## License

This project is proprietary. See `composer.json` for license information.

## Additional Documentation

For detailed product requirements and specifications, refer to:
- `.ai/prd.md` - Product Requirements Document (in Polish)
- `.ai/tech-stack.md` - Technology stack details
- `.ai/api-plan.md` - API planning documentation
- `.ai/test-plan.md` - Comprehensive testing plan (in Polish)

## Support

For issues, questions, or contributions, please refer to the project's issue tracker or contact the development team.

---

**Note**: Make sure to keep your `.env.local` file secure and never commit it to version control. All sensitive credentials should be stored in environment variables.
