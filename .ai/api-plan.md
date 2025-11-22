# REST API Plan

## 1. Resources

### 1.1. Server Metrics

**Database Table:** `server_metrics`

**Description:** The primary resource representing system metrics collected from a monitored Linux server via SSH. Each record contains a snapshot of server metrics at a specific timestamp.

**Fields:**
- `id` (integer, unsigned): Unique identifier, auto-increment primary key
- `timestamp` (datetime): UTC timestamp when metrics were collected
- `cpu_usage` (decimal 5,2, unsigned): CPU utilization percentage (0.00-100.00)
- `ram_usage` (decimal 10,2, unsigned): RAM usage in GB
- `disk_usage` (decimal 10,2, unsigned): Disk usage in GB
- `io_read_bytes` (bigint, unsigned): Cumulative bytes read from disk
- `io_write_bytes` (bigint, unsigned): Cumulative bytes written to disk
- `network_sent_bytes` (bigint, unsigned): Cumulative bytes sent over network
- `network_received_bytes` (bigint, unsigned): Cumulative bytes received over network

**Notes:**
- I/O and Network metrics are stored as cumulative (absolute) values; differences are calculated client-side
- Duplicate timestamps are allowed (no UNIQUE constraint) to support retries and concurrent cron executions
- All timestamps are stored in UTC
- Data retention: 90 days (older records are automatically deleted)

## 2. Endpoints

### 2.1. Authentication Endpoints

#### POST /api/login

**Description:** Authenticate user with password from `.env` configuration.

**Request Body:**
```json
{
  "password": "string"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Authentication successful"
}
```

**Response Headers:**
- `Set-Cookie`: Session cookie for authenticated requests

**Error Responses:**
- `401 Unauthorized`: Invalid password
  ```json
  {
    "success": false,
    "error": "Invalid password"
  }
  ```
- `400 Bad Request`: Missing or invalid request body
  ```json
  {
    "success": false,
    "error": "Password is required"
  }
  ```

**Business Logic:**
- Password is validated against value from `.env` (e.g., `APP_PASSWORD`)
- On success, Symfony Security creates a session with 30-minute inactivity timeout
- Session cookie is HTTP-only and secure (HTTPS in production)

---

#### POST /api/logout

**Description:** End user session and invalidate authentication.

**Authentication:** Required (session-based)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

**Error Responses:**
- `401 Unauthorized`: No active session
  ```json
  {
    "success": false,
    "error": "Not authenticated"
  }
  ```

**Business Logic:**
- Symfony Security destroys the session
- Session cookie is invalidated

---

### 2.2. Metrics Endpoints

#### GET /api/metrics

**Description:** Retrieve server metrics for a specified time range. Automatically aggregates data for long ranges (7d, 30d) using 10-minute buckets.

**Authentication:** Required (session-based)

**Query Parameters:**
- `range` (string, required): Time range - one of: `1h`, `6h`, `24h`, `7d`, `30d`
  - Default: `24h` if not provided
- `start` (datetime, optional): Custom start time in ISO 8601 format (UTC). If provided, overrides `range` parameter
- `end` (datetime, optional): Custom end time in ISO 8601 format (UTC). If provided, overrides `range` parameter

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 12345,
      "timestamp": "2024-01-15T14:30:00Z",
      "cpu_usage": 45.50,
      "ram_usage": 8.25,
      "disk_usage": 120.75,
      "io_read_bytes": 1024000000,
      "io_write_bytes": 512000000,
      "network_sent_bytes": 2048000000,
      "network_received_bytes": 4096000000
    }
  ],
  "meta": {
    "range": "24h",
    "count": 1440,
    "aggregated": false,
    "start_time": "2024-01-14T14:30:00Z",
    "end_time": "2024-01-15T14:30:00Z"
  }
}
```

**Response for Aggregated Data (7d, 30d):**
```json
{
  "success": true,
  "data": [
    {
      "id": null,
      "timestamp": "2024-01-15T14:30:00Z",
      "cpu_usage": 42.75,
      "ram_usage": 8.10,
      "disk_usage": 120.50,
      "io_read_bytes": 1024000000,
      "io_write_bytes": 512000000,
      "network_sent_bytes": 2048000000,
      "network_received_bytes": 4096000000
    }
  ],
  "meta": {
    "range": "7d",
    "count": 1008,
    "aggregated": true,
    "bucket_size_minutes": 10,
    "start_time": "2024-01-08T14:30:00Z",
    "end_time": "2024-01-15T14:30:00Z"
  }
}
```

**Response for No Data:**
```json
{
  "success": true,
  "data": [],
  "meta": {
    "range": "1h",
    "count": 0,
    "aggregated": false,
    "start_time": "2024-01-15T13:30:00Z",
    "end_time": "2024-01-15T14:30:00Z"
  }
}
```

**Error Responses:**
- `401 Unauthorized`: Not authenticated
  ```json
  {
    "success": false,
    "error": "Authentication required"
  }
  ```
- `400 Bad Request`: Invalid range parameter
  ```json
  {
    "success": false,
    "error": "Invalid range. Must be one of: 1h, 6h, 24h, 7d, 30d"
  }
  ```
- `400 Bad Request`: Invalid datetime format for start/end
  ```json
  {
    "success": false,
    "error": "Invalid datetime format. Use ISO 8601 format (e.g., 2024-01-15T14:30:00Z)"
  }
  ```
- `500 Internal Server Error`: Database or server error
  ```json
  {
    "success": false,
    "error": "Internal server error"
  }
  ```

**Business Logic:**
- For ranges `1h`, `6h`, `24h`: Returns all records within the time range, ordered by timestamp ascending
- For ranges `7d`, `30d`: Automatically aggregates data using SQL `GROUP BY` with 10-minute buckets:
  - `AVG()` for continuous values (CPU, RAM, Disk)
  - `MAX()` for cumulative values (I/O, Network) - last value in bucket represents cumulative total
  - Bucket calculation: `FLOOR(UNIX_TIMESTAMP(timestamp) / 600) * 600` (600 seconds = 10 minutes)
- Uses index on `timestamp` column for optimal query performance
- All timestamps in response are in UTC
- Empty array returned if no data exists for the specified range

**SQL Query Examples:**

For short ranges (1h, 6h, 24h):
```sql
SELECT * FROM server_metrics 
WHERE timestamp BETWEEN :start_time AND :end_time 
ORDER BY timestamp ASC;
```

For long ranges (7d, 30d) with aggregation:
```sql
SELECT 
    FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 600) * 600) AS timestamp,
    AVG(cpu_usage) AS cpu_usage,
    AVG(ram_usage) AS ram_usage,
    AVG(disk_usage) AS disk_usage,
    MAX(io_read_bytes) AS io_read_bytes,
    MAX(io_write_bytes) AS io_write_bytes,
    MAX(network_sent_bytes) AS network_sent_bytes,
    MAX(network_received_bytes) AS network_received_bytes
FROM server_metrics
WHERE timestamp BETWEEN :start_time AND :end_time
GROUP BY FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 600) * 600)
ORDER BY timestamp ASC;
```

---

#### GET /api/metrics/latest

**Description:** Retrieve the most recent server metrics record. Used for displaying last known values when SSH collection fails.

**Authentication:** Required (session-based)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 12345,
    "timestamp": "2024-01-15T14:30:00Z",
    "cpu_usage": 45.50,
    "ram_usage": 8.25,
    "disk_usage": 120.75,
    "io_read_bytes": 1024000000,
    "io_write_bytes": 512000000,
    "network_sent_bytes": 2048000000,
    "network_received_bytes": 4096000000
  }
}
```

**Response for No Data:**
```json
{
  "success": true,
  "data": null
}
```

**Error Responses:**
- `401 Unauthorized`: Not authenticated
  ```json
  {
    "success": false,
    "error": "Authentication required"
  }
  ```
- `500 Internal Server Error`: Database or server error
  ```json
  {
    "success": false,
    "error": "Internal server error"
  }
  ```

**Business Logic:**
- Returns the most recent record based on `timestamp` (ORDER BY timestamp DESC LIMIT 1)
- Used by dashboard to display last known values when SSH collection fails
- Returns `null` if no records exist in database

---

#### GET /api/metrics/stats

**Description:** Retrieve aggregated statistics for a specified time range. Provides summary information for dashboard overview.

**Authentication:** Required (session-based)

**Query Parameters:**
- `range` (string, required): Time range - one of: `1h`, `6h`, `24h`, `7d`, `30d`
  - Default: `24h` if not provided

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "cpu": {
      "min": 10.25,
      "max": 95.50,
      "avg": 45.75,
      "current": 42.30
    },
    "ram": {
      "min": 5.10,
      "max": 12.50,
      "avg": 8.25,
      "current": 8.15
    },
    "disk": {
      "min": 115.00,
      "max": 125.00,
      "avg": 120.50,
      "current": 120.75
    },
    "io": {
      "read_total": 1024000000,
      "write_total": 512000000,
      "read_avg_per_minute": 10240000,
      "write_avg_per_minute": 5120000
    },
    "network": {
      "sent_total": 2048000000,
      "received_total": 4096000000,
      "sent_avg_per_minute": 20480000,
      "received_avg_per_minute": 40960000
    },
    "last_update": "2024-01-15T14:30:00Z",
    "record_count": 1440
  }
}
```

**Error Responses:**
- `401 Unauthorized`: Not authenticated
- `400 Bad Request`: Invalid range parameter
- `500 Internal Server Error`: Database or server error

**Business Logic:**
- Calculates min, max, average, and current values for CPU, RAM, and Disk
- For I/O and Network: calculates total bytes and average per minute (difference between first and last record divided by time span)
- `current` values are from the most recent record
- `last_update` is the timestamp of the most recent record
- Uses SQL aggregation functions (MIN, MAX, AVG) for efficient calculation

---

### 2.3. System Status Endpoints

#### GET /api/status

**Description:** Retrieve system status information including last successful metric collection timestamp and SSH connection status.

**Authentication:** Required (session-based)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "last_collection": "2024-01-15T14:30:00Z",
    "last_collection_status": "success",
    "ssh_connected": true,
    "data_available": true,
    "oldest_record": "2024-01-08T14:30:00Z",
    "newest_record": "2024-01-15T14:30:00Z",
    "total_records": 129600
  }
}
```

**Response when No Data:**
```json
{
  "success": true,
  "data": {
    "last_collection": null,
    "last_collection_status": "unknown",
    "ssh_connected": false,
    "data_available": false,
    "oldest_record": null,
    "newest_record": null,
    "total_records": 0
  }
}
```

**Error Responses:**
- `401 Unauthorized`: Not authenticated
- `500 Internal Server Error`: Database or server error

**Business Logic:**
- `last_collection`: Timestamp of the most recent record in database
- `last_collection_status`: Determined by checking if last collection was within expected interval (e.g., within last 2 minutes for 1-minute collection)
- `ssh_connected`: Status determined by checking if last collection is recent (within last 5 minutes)
- `data_available`: Boolean indicating if any records exist in database
- `oldest_record` and `newest_record`: Timestamps of oldest and newest records
- `total_records`: Total count of records in database

---

## 3. Authentication and Authorization

### 3.1. Authentication Mechanism

**Type:** Session-based authentication using Symfony Security Component

**Implementation Details:**
- Password stored in `.env` file (e.g., `APP_PASSWORD`)
- Login endpoint (`POST /api/login`) validates password against `.env` value
- On successful authentication, Symfony Security creates a session
- Session cookie is HTTP-only and secure (HTTPS in production)
- Session timeout: 30 minutes of inactivity
- All API endpoints (except `/api/login`) require valid session

**Session Management:**
- Symfony uses native PHP sessions stored on server
- Session ID stored in cookie (`PHPSESSID` by default)
- Session destroyed on logout (`POST /api/logout`) or timeout
- Expired sessions result in `401 Unauthorized` responses

### 3.2. Authorization

**Scope:** Single-user application (MVP)

**Authorization Rules:**
- All authenticated users have full access to all endpoints
- No role-based access control (single password for all users)
- No user management (no user registration, no user profiles)

**Security Considerations:**
- Password should be strong (minimum 12 characters recommended)
- Password stored in `.env` file (gitignored)
- HTTPS required in production to protect session cookies
- CSRF protection for login form (handled by Symfony Security)
- Rate limiting recommended for login endpoint (e.g., 5 attempts per minute)

---

## 4. Validation and Business Logic

### 4.1. Request Validation

#### POST /api/login

**Validation Rules:**
- `password` field is required (non-empty string)
- Password must match value from `.env` (case-sensitive)
- Request body must be valid JSON

**Error Handling:**
- Missing password: `400 Bad Request` with error message
- Invalid password: `401 Unauthorized` with error message
- Invalid JSON: `400 Bad Request` with error message

---

#### GET /api/metrics

**Validation Rules:**
- `range` parameter: Must be one of: `1h`, `6h`, `24h`, `7d`, `30d`
- `start` parameter (if provided): Must be valid ISO 8601 datetime in UTC
- `end` parameter (if provided): Must be valid ISO 8601 datetime in UTC
- If both `start` and `end` provided: `start` must be before `end`
- If custom range provided: Maximum range is 30 days (to prevent excessive data retrieval)

**Error Handling:**
- Invalid range: `400 Bad Request` with error message
- Invalid datetime format: `400 Bad Request` with error message
- Start after end: `400 Bad Request` with error message
- Range too large: `400 Bad Request` with error message

---

### 4.2. Data Validation

#### Server Metrics Entity Validation

**Validation Rules (from database schema):**
- `cpu_usage`: Decimal 5,2, unsigned, range 0.00-100.00
- `ram_usage`: Decimal 10,2, unsigned, non-negative
- `disk_usage`: Decimal 10,2, unsigned, non-negative
- `io_read_bytes`: Bigint unsigned, non-negative
- `io_write_bytes`: Bigint unsigned, non-negative
- `network_sent_bytes`: Bigint unsigned, non-negative
- `network_received_bytes`: Bigint unsigned, non-negative
- `timestamp`: Datetime, NOT NULL, UTC timezone

**Validation Implementation:**
- Validation performed in Symfony Entity using Doctrine constraints
- Additional validation in Service/Command layer before database insert
- Default values (0) applied if parsing fails (prevents NULL values)

---

### 4.3. Business Logic Implementation

#### Time Range Calculation

**Logic:**
- `1h`: Start time = current time - 1 hour, End time = current time
- `6h`: Start time = current time - 6 hours, End time = current time
- `24h`: Start time = current time - 24 hours, End time = current time
- `7d`: Start time = current time - 7 days, End time = current time
- `30d`: Start time = current time - 30 days, End time = current time

**Implementation:**
- All time calculations use UTC timezone
- Current time obtained from server clock (not client)
- Time ranges are inclusive (start <= timestamp <= end)

---

#### Data Aggregation for Long Ranges

**Logic:**
- For ranges `7d` and `30d`: Data is aggregated into 10-minute buckets
- Aggregation performed in SQL using `GROUP BY` with time bucket calculation
- Bucket calculation: `FLOOR(UNIX_TIMESTAMP(timestamp) / 600) * 600` (600 seconds = 10 minutes)

**Aggregation Functions:**
- `AVG()` for continuous values: `cpu_usage`, `ram_usage`, `disk_usage`
- `MAX()` for cumulative values: `io_read_bytes`, `io_write_bytes`, `network_sent_bytes`, `network_received_bytes`

**Rationale:**
- Prevents rendering thousands of data points on charts
- Maintains chart readability and performance
- Cumulative values use MAX() because last value in bucket represents total cumulative bytes

---

#### Error Handling and Edge Cases

**No Data Available:**
- Returns empty array `[]` with `count: 0` in meta
- Dashboard displays "No data" message per section
- No error response (200 OK with empty data)

**SSH Collection Failure:**
- Last known values retrieved via `/api/metrics/latest`
- Dashboard displays warning banner with last update timestamp
- Status endpoint indicates `ssh_connected: false` if last collection is stale

**Database Errors:**
- All database errors return `500 Internal Server Error`
- Error details logged via Monolog (not exposed to client)
- Generic error message returned to client

**Session Expiration:**
- Expired sessions return `401 Unauthorized`
- Frontend redirects to login page with expired session message
- Session timeout: 30 minutes of inactivity

---

### 4.4. Performance Considerations

**Query Optimization:**
- Index on `timestamp` column used for all range queries
- Aggregation performed in SQL (not in application layer)
- LIMIT clauses not needed (data retention limits data volume)

**Response Size:**
- Short ranges (1h, 6h, 24h): Maximum ~1440 records (24h * 60 minutes)
- Long ranges (7d, 30d): Aggregated to ~1008 records (7d) or ~4320 records (30d) with 10-minute buckets
- Estimated response size: <500 KB for 24h range, <2 MB for 30d range

**Caching:**
- No caching implemented in MVP (all queries hit database directly)
- Future enhancement: Cache aggregated data for long ranges

---

## 5. API Versioning and Future Considerations

### 5.1. Versioning Strategy

**Current:** No versioning (MVP)

**Future Considerations:**
- API versioning via URL prefix (e.g., `/api/v1/metrics`) if breaking changes needed
- Version negotiation via Accept header (e.g., `Accept: application/vnd.servermonitor.v1+json`)

### 5.2. Rate Limiting

**Current:** No rate limiting (MVP)

**Future Considerations:**
- Rate limiting for login endpoint (e.g., 5 attempts per minute per IP)
- Rate limiting for metrics endpoints (e.g., 100 requests per minute per session)
- Implementation: Symfony Rate Limiter component or external service (e.g., Redis)

### 5.3. Pagination

**Current:** Not implemented (all data returned in single response)

**Future Considerations:**
- Pagination for very large datasets (if data retention increases)
- Cursor-based pagination for time-series data
- Page size limit (e.g., 1000 records per page)

### 5.4. Filtering and Sorting

**Current:** Basic filtering by time range only

**Future Considerations:**
- Filter by specific metric type (e.g., only CPU data)
- Sort by different fields (currently only by timestamp)
- Filter by metric value ranges (e.g., CPU > 80%)

---

## 6. Error Response Format

All error responses follow a consistent format:

```json
{
  "success": false,
  "error": "Error message describing what went wrong",
  "code": "ERROR_CODE" // Optional: machine-readable error code
}
```

**Standard HTTP Status Codes:**
- `200 OK`: Successful request
- `400 Bad Request`: Invalid request parameters or body
- `401 Unauthorized`: Authentication required or invalid
- `404 Not Found`: Resource not found (not used in MVP)
- `500 Internal Server Error`: Server or database error

---

## 7. Response Format

All successful responses follow a consistent format:

```json
{
  "success": true,
  "data": { /* response data */ },
  "meta": { /* optional metadata */ }
}
```

**Metadata Fields:**
- `range`: Time range used for query
- `count`: Number of records returned
- `aggregated`: Boolean indicating if data was aggregated
- `start_time`: Start time of query range (ISO 8601 UTC)
- `end_time`: End time of query range (ISO 8601 UTC)
- `bucket_size_minutes`: Size of aggregation buckets (if aggregated)

---

## 8. Assumptions and Notes

### 8.1. Assumptions

1. **Single Server:** API assumes single server monitoring (no `server_id` in database schema)
2. **Internal Use:** API is for internal frontend use only (not public REST API for external integrations)
3. **Session Management:** Symfony handles session management (no JWT tokens)
4. **Timezone:** All timestamps in UTC (conversion to user timezone handled client-side)
5. **Data Retention:** 90-day retention policy (older data automatically deleted)

### 8.2. Implementation Notes

1. **Symfony Routing:** All endpoints should be defined in `config/routes.yaml` or controller annotations
2. **Doctrine ORM:** Entity `ServerMetric` maps to `server_metrics` table
3. **Repository Pattern:** Use Doctrine Repository for database queries
4. **Service Layer:** Business logic (aggregation, validation) should be in Service classes
5. **Error Logging:** All errors logged via Monolog with appropriate log levels

### 8.3. Testing Considerations

1. **Unit Tests:** Test validation rules, business logic, aggregation functions
2. **Integration Tests:** Test API endpoints with database fixtures
3. **Authentication Tests:** Test login, logout, session expiration
4. **Edge Cases:** Test empty data, invalid ranges, expired sessions

---

## 9. Example API Usage

### 9.1. Authentication Flow

```bash
# 1. Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"password": "your-password"}' \
  -c cookies.txt

# 2. Get metrics (uses session cookie)
curl -X GET "http://localhost:8000/api/metrics?range=24h" \
  -b cookies.txt

# 3. Logout
curl -X POST http://localhost:8000/api/logout \
  -b cookies.txt
```

### 9.2. Fetching Metrics

```bash
# Get 24h metrics
curl -X GET "http://localhost:8000/api/metrics?range=24h" \
  -b cookies.txt

# Get 7d aggregated metrics
curl -X GET "http://localhost:8000/api/metrics?range=7d" \
  -b cookies.txt

# Get latest metrics
curl -X GET "http://localhost:8000/api/metrics/latest" \
  -b cookies.txt

# Get statistics
curl -X GET "http://localhost:8000/api/metrics/stats?range=24h" \
  -b cookies.txt
```

### 9.3. Checking System Status

```bash
curl -X GET "http://localhost:8000/api/status" \
  -b cookies.txt
```

---

## 10. Summary

This REST API plan provides a comprehensive foundation for the Server Monitor MVP dashboard. The API supports:

- **Authentication:** Session-based authentication with password from `.env`
- **Metrics Retrieval:** Time-range queries with automatic aggregation for long ranges
- **Status Monitoring:** System status and last collection information
- **Error Handling:** Consistent error responses with appropriate HTTP status codes
- **Performance:** Optimized queries using database indexes and SQL aggregation

The API is designed for internal use by the Symfony frontend application and follows RESTful principles while respecting MVP constraints (single server, no external integrations, no caching).

