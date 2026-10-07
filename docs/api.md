# API reference

Base URL: `http://localhost:8000/api`

Send `Accept: application/json` and `Content-Type: application/json` for JSON bodies. All routes except register and login require a Sanctum Bearer token.

## Endpoints

| Method | Path | Successful status | Description |
| --- | --- | --- | --- |
| POST | `/register` | 201 | Register, return a token, queue a welcome email |
| POST | `/login` | 200 | Authenticate and return a token |
| POST | `/logout` | 204 | Revoke the current token |
| GET | `/users` | 200 | Paginate user profiles |
| GET | `/users/{id}` | 200 | Retrieve a user profile |
| GET | `/posts` | 200 | Paginate posts, newest first |
| GET | `/posts/{id}` | 200 | Retrieve a post |
| POST | `/posts` | 201 | Create a post owned by the authenticated user |
| PATCH | `/posts/{id}` | 200 | Partially update your own post |
| DELETE | `/posts/{id}` | 204 | Delete your own post |
| GET | `/weather` | 200 | Retrieve current Perth weather |

## Authentication

Registration body:

```json
{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

Name is required and at most 255 characters. Email must be valid, unique, and at most 255 characters. Password must contain at least eight characters, no NUL bytes, and at most 72 bytes because bcrypt has a byte limit. Confirmation must match.

Login body:

```json
{
    "email": "jane@example.com",
    "password": "password123"
}
```

Register/login response:

```json
{
    "data": {
        "user": {
            "id": 1,
            "name": "Jane Doe",
            "created_at": "2026-10-06T00:00:00.000000Z"
        },
        "token": "1|example-plain-text-token",
        "token_type": "Bearer"
    }
}
```

Use the returned token in `Authorization: Bearer <token>`. Logout has no request body and returns no response body. Other tokens remain valid. The default token lifetime is 24 hours. Incorrect credentials return HTTP 401 with `Invalid credentials.`.

Registration is limited to 10 requests per minute per IP and login to five. HTTP 429 includes the framework's retry headers.

Example login:

```bash
curl -X POST http://localhost:8000/api/login \
    -H 'Accept: application/json' \
    -H 'Content-Type: application/json' \
    -d '{"email":"demo@example.com","password":"password123"}'
```

## Posts

Create body:

```json
{
    "title": "A day in Perth",
    "content": "Clear skies today."
}
```

Title is required and at most 255 characters. Content is required and at most 60,000 characters, including Unicode. The authenticated user becomes the author. Supplying a nonempty `user_id` is prohibited.

PATCH accepts either or both fields. Omitted fields keep their current values; supplied fields cannot be empty or null. An empty object performs no changes. Authors cannot transfer a post to another user.

Post response:

```json
{
    "data": {
        "id": 1,
        "title": "A day in Perth",
        "content": "Clear skies today.",
        "user": {
            "id": 1,
            "name": "Jane Doe",
            "created_at": "2026-10-06T00:00:00.000000Z"
        },
        "created_at": "2026-10-06T00:00:00.000000Z",
        "updated_at": "2026-10-06T00:00:00.000000Z"
    }
}
```

The author resource includes `email` only when viewed by that same user. Updating/deleting another user's post returns HTTP 403. Unknown IDs return HTTP 404. DELETE returns an empty HTTP 204 response.

## Users

User resources contain `id`, `name`, and `created_at`. Reading your own profile also includes `email`. Other users' email addresses, password hashes, and token hashes are hidden.

```json
{
    "data": {
        "id": 1,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "created_at": "2026-10-06T00:00:00.000000Z"
    }
}
```

## Pagination

`GET /posts` and `GET /users` accept `page` (integer, at least 1) and `per_page` (integer, 1–100; default 15). Users are ordered by ID ascending and posts by ID descending. Invalid pagination values return HTTP 422.

Example: `/posts?page=2&per_page=10`.

List responses contain:

```json
{
    "data": [],
    "links": {
        "first": "http://localhost:8000/api/posts?per_page=10&page=1",
        "last": "http://localhost:8000/api/posts?per_page=10&page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": null,
        "last_page": 1,
        "links": [],
        "path": "http://localhost:8000/api/posts",
        "per_page": 10,
        "to": null,
        "total": 0
    }
}
```

## Weather

`GET /weather` takes no parameters; the configured location is Perth, Australia.

```json
{
    "data": {
        "city": "Perth",
        "country": "AU",
        "temperature": 21.5,
        "feels_like": 20,
        "humidity": 60,
        "description": "clear sky",
        "wind_speed": 3.2,
        "units": "metric",
        "timezone": "Australia/Perth",
        "observed_at": "2026-10-06T08:00:00+08:00",
        "fetched_at": "2026-10-06T08:05:00+08:00"
    }
}
```

Temperature is in Celsius, humidity in percent, and wind speed in metres per second. `observed_at` comes from the provider; `fetched_at` is when the application retrieved it. Both use Perth's timezone.

A valid response is cached for 900 seconds by default. The hourly queued job refreshes it proactively, and cache misses refresh it on request. A missing API key, provider failure, or malformed provider response returns:

```json
{
    "message": "Weather data is temporarily unavailable."
}
```

Status: HTTP 503. Provider error bodies, API keys, and internal exception details are not exposed by this response.

## Errors

| Status | Meaning |
| --- | --- |
| 401 | Missing/invalid/expired token, or incorrect login credentials |
| 403 | User does not own the post being changed |
| 404 | Requested post/user does not exist |
| 422 | Request validation failed |
| 429 | Login/registration rate limit reached |
| 503 | Weather is unavailable |

Validation errors use Laravel's JSON format:

```json
{
    "message": "The title field is required.",
    "errors": {
        "title": ["The title field is required."]
    }
}
```

Other expected errors include a `message` string. Set `APP_DEBUG=false` in deployed environments.
