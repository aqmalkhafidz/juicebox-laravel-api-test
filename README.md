# Juicebox Laravel API Code Test

REST API for users, posts, and current weather in Perth, Australia. Built with Laravel 13, PHP 8.5, MySQL, and Laravel Sanctum.

## Features

- Register, log in, and revoke the current API token on logout.
- Read and paginate posts; create posts and update/delete only your own posts.
- Read and paginate user profiles; email addresses are visible only to their owner.
- Retrieve Perth weather from OpenWeatherMap, cached for 15 minutes.
- Refresh weather with an hourly queued job.
- Send welcome emails with a queued job and a manual Artisan command.
- Request validation, policies, API resources, and PHPUnit feature tests.

## Requirements

- PHP 8.5 with Laravel's required extensions, including `pdo_mysql`.
- Composer 2.
- MySQL 8 or newer.
- An OpenWeatherMap API key for live weather requests.

The API has no frontend build step. Queue and cache use MySQL by default; Redis is optional.

## Setup

From the project directory:

```bash
php -v
composer install
cp .env.example .env
php artisan key:generate
```

If you use Laravel Herd on macOS and the CLI selects an older PHP, put Herd first in the current shell's PATH:

```bash
export PATH="$HOME/Library/Application Support/Herd/bin:$PATH"
php -v
```

Make sure the selected version is PHP 8.5. Start MySQL in DBngin or your preferred local service, then create the application and test databases using your SQL client:

```sql
CREATE DATABASE juicebox_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE juicebox_api_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Configure your connection in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=juicebox_api
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
```

Run migrations and optional demo data:

```bash
php artisan migrate --seed
php artisan serve
```

The default base URL is `http://localhost:8000/api`. With Herd, you can use `http://juicebox-laravel-api-test.test/api` instead.

The seeder creates `demo@example.com` with password `password123` and five posts. It can be rerun without duplicating the demo account or its posts.

## Weather setup

1. Create an account at [OpenWeatherMap](https://home.openweathermap.org/users/sign_up).
2. Get an API key from the account's **API keys** page and enable access to [Current Weather Data](https://openweathermap.org/api/current). A new key may need time to become active.
3. Set `WEATHER_API_KEY` in `.env`. Keep the key outside source control.
4. Clear cached configuration after changing environment values:

```bash
php artisan config:clear
```

Weather settings in `.env.example`:

| Variable | Default | Purpose |
| --- | --- | --- |
| `WEATHER_API_KEY` | Empty | OpenWeatherMap API key; required for live data |
| `WEATHER_BASE_URL` | `https://api.openweathermap.org/data/2.5` | Provider endpoint |
| `WEATHER_LATITUDE` | `-31.9523` | Perth latitude |
| `WEATHER_LONGITUDE` | `115.8613` | Perth longitude |
| `WEATHER_CACHE_TTL` | `900` | Cache lifetime in seconds |

The API returns current conditions in Celsius, humidity as a percentage, and wind speed in metres per second. Observation and retrieval timestamps use `Australia/Perth`.

Requests use cached data while it is valid. On a cache miss, the API calls the provider and caches a successful, validated response. The hourly job forces a refresh independently of incoming requests. A request after the 15-minute cache expiry can refresh immediately without waiting for that job.

Provider requests have a two-second connection timeout and a five-second request timeout. Connection failures, HTTP 429, and HTTP 5xx are retried once. Invalid credentials and other HTTP 4xx are not retried. Failures and malformed responses return HTTP 503 and are not cached. A failed background refresh leaves any existing cache entry intact until it expires.

## Queue worker and scheduler

Run the worker and local scheduler in separate terminals:

```bash
php artisan queue:work --tries=3 --timeout=60
```

```bash
php artisan schedule:work
```

Registration dispatches `SendWelcomeEmail` after the user/token transaction commits. The `RefreshWeather` job is scheduled hourly. Both jobs have three attempts with backoff. Weather jobs have a 30-second timeout.

For a deployed application, run the queue worker under a process manager and add a cron entry with the deployment's actual project path:

```cron
* * * * * cd /path/to/juicebox-laravel-api-test && php artisan schedule:run >> /dev/null 2>&1
```

Inspect the schedule and failed jobs:

```bash
php artisan schedule:list
php artisan queue:failed
```

Restart long-running workers after changing application code or configuration:

```bash
php artisan queue:restart
```

### Manual welcome email

Queue an email for an existing user, for example the seeded user with ID 1:

```bash
php artisan welcome-email:send 1
```

An unknown user produces `User not found.` and a nonzero exit status. The command queues the email; a worker must process it.

`MAIL_MAILER=log` writes email content to `storage/logs/laravel.log` without sending external mail. For delivery through an SMTP provider, set `MAIL_MAILER=smtp` and the provider's `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` values.

## API documentation

- [Endpoint reference and response examples](docs/api.md)
- [Postman collection](docs/juicebox-api.postman_collection.json)

Import the Postman collection and set its `base_url`. Register or log in to automatically save `token` and `user_id`; creating a post saves `post_id`. You can log in with the seeded demo account.

Except for registration and login, all API routes require:

```http
Accept: application/json
Authorization: Bearer <token>
```

Tokens expire after 24 hours by default (`SANCTUM_EXPIRATION=1440`, in minutes). Logout revokes only the token used for that request. Registration is limited to 10 requests per minute per IP; login is limited to five.

## Tests and formatting

Tests use the separate MySQL database `juicebox_api_testing`, configured in `phpunit.xml`. `RefreshDatabase` rebuilds that database when needed; reserve it exclusively for tests. No test calls the real weather API or sends external email.

```bash
php artisan test
composer format:check
```

If the test database uses a different port or account, pass those connection settings through the shell environment or update the relevant defaults in `phpunit.xml`:

```bash
DB_PORT=3307 php artisan test
```

Apply the Laravel formatting preset with:

```bash
composer format
```

Tests cover authentication and token revocation, post ownership and CRUD, pagination, private user fields, input validation, Unicode content, bcrypt password constraints, welcome email dispatch/rendering, the manual command, weather responses/cache expiry/retries/failures, and hourly scheduling.

## Structure and assumptions

Controllers handle HTTP requests, Form Requests validate input, resources shape output, and `PostPolicy` controls writes. `WeatherService` contains provider and cache logic shared by the endpoint and job. Eloquent handles persistence and relationships directly.

- The brief asks for pagination of users but omits a user list endpoint. `GET /api/users` is included to satisfy that requirement.
- Post fields are `title` and `content`. Reads are available to authenticated users; writes belong to the author.
- Other users' profiles expose ID, name, and creation timestamp. Only the account owner sees their email. Passwords and token hashes are never returned.
- Weather is current conditions for Perth, rather than a daily forecast.
- Users and posts are persisted; weather is stored in the database-backed cache rather than a separate weather history table.
