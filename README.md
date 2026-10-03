# Task Management REST API

A Laravel REST API for managing personal projects and tasks. It demonstrates Sanctum authentication, Eloquent relationships and query scopes, API Resources, Form Request validation, database indexes, feature tests, Docker, and API documentation.

## Features

- Register, log in, log out, and retrieve the authenticated user
- Project and task CRUD with per-user authorization policies
- Consistent API error responses and field-level validation details
- Explicit User, Project, Task, and authentication token Resources
- Paginated lists with validated search, filters, and sorting
- Task filters for project, status, priority, and due-date range
- Database indexes for owner-scoped status, priority, project, due-date, and ordering queries
- OpenAPI documentation, a Postman collection, and PHPUnit feature tests

## Stack

Laravel, PHP 8.3+, MySQL 8, Laravel Sanctum, Eloquent ORM, PHPUnit, Docker Compose, and Postman.

## Run with Docker

From the project root:

```sh
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The API runs at `http://localhost:8000`. MySQL is exposed on port `3307`; the Compose app connects to it over the Docker network. Stop the services with `docker compose down`.

The optional seeder creates a local demo account (`demo@example.com`, password `password`) and sample projects and tasks. These credentials are for local development only. The seeder can be run repeatedly without duplicating its sample records.

## Run locally

Install dependencies, configure MySQL in `.env`, and start the development server:

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

When using the database started by Docker from the host, set `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, and `DB_PORT=3307` in `.env`, along with the database name and credentials from `compose.yml`.

## API documentation and requests

- [OpenAPI specification](docs/openapi.yaml)
- [Postman collection](<postman/Task Management API.postman_collection.json>)

All routes use the `/api` prefix. Protected routes require a Sanctum bearer token:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

Register or log in to receive a token. User responses contain public profile fields only; passwords and password hashes are never returned.

## Endpoints

| Method | Endpoint | Description | Auth |
| --- | --- | --- | --- |
| POST | `/api/register` | Create an account and issue a token | No |
| POST | `/api/login` | Authenticate and issue a token | No |
| POST | `/api/logout` | Revoke the current token | Yes |
| GET | `/api/me` | Return the authenticated user | Yes |
| GET, POST | `/api/projects` | List or create projects | Yes |
| GET, PUT/PATCH, DELETE | `/api/projects/{project}` | Read, update, or delete a project | Yes |
| GET, POST | `/api/tasks` | List or create tasks | Yes |
| GET, PUT/PATCH, DELETE | `/api/tasks/{task}` | Read, update, or delete a task | Yes |

Creates return `201`; deletes return `204`. Project and task updates accept partial fields.

### Project fields and filters

Projects have a required `name` and `status` (`active`, `completed`, or `archived`), plus an optional `description`. Project list responses include each project's `task_count`.

```http
GET /api/projects?search=portfolio&status=active&sort_by=name&sort_direction=asc&per_page=20&page=1
```

Project list parameters: `search` (matches name and description), `status`, `sort_by` (`created_at`, `updated_at`, `name`, `status`), `sort_direction` (`asc`, `desc`), `per_page` (1-100, default 10), and `page` (default 1).

### Task fields and filters

Tasks require `project_id` and `title`. Optional fields are `description`, `due_date`, `status` (`pending`, `in_progress`, `completed`, `cancelled`; default `pending`), and `priority` (`low`, `medium`, `high`, `urgent`; default `medium`). A task can only be assigned to a project owned by the authenticated user.

```http
GET /api/tasks?search=portfolio&project_id=1&status=pending&priority=high&due_date_from=2026-10-01&due_date_to=2026-10-31&sort_by=due_date&sort_direction=asc&per_page=20&page=1
```

Task list parameters: `search` (matches title and description), `project_id`, `status`, `priority`, `due_date_from`, `due_date_to` (inclusive; must be on or after `due_date_from`), `sort_by` (`created_at`, `updated_at`, `due_date`, `title`, `priority`), `sort_direction` (`asc`, `desc`), `per_page` (1-100, default 10), and `page` (default 1). List responses include Laravel pagination links and metadata.

### Error responses

Errors use a consistent JSON shape. Validation failures include field messages; other errors use an empty `errors` object.

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "error": { "code": "VALIDATION_ERROR" },
  "errors": { "email": ["The email field is required."] }
}
```

## Tests

The PHPUnit feature suite covers authentication, ownership, CRUD, validation, filtering, response Resources, and password omission. Tests use an in-memory SQLite database:

```sh
docker compose exec app php artisan test
```

For host PHP, enable `mbstring`, `pdo_sqlite`, and `sqlite3` before running `php artisan test`.
