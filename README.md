# Teamboard

Teamboard is a project and task management application built with Laravel 12, Blade, Tailwind CSS 4, and vanilla JavaScript.

## Features

- Session authentication, inactive account protection, and password changes.
- Admin management of users, projects, and project memberships.
- Tasks with priorities, assignees, due dates, and project references.
- List and Kanban views with protection against conflicting edits.
- Task comments, checklists, private attachments, and activity history.
- Personal task lists, progress summaries, and assignment notifications.
- Responsive layouts and a desktop sidebar toggle.

## Local requirements

- Linux, macOS, or Windows with WSL2.
- Docker with Docker Compose.
- Docker Desktop WSL integration enabled when using Windows.
- Node.js 22 with npm.

PHP and Composer run through Docker and Laravel Sail.

The application uses PHP 8.2 in Sail. Composer dependency resolution targets PHP 8.2.29.

## First installation

Run these steps from a fresh checkout's root directory.

### 1. Configure the environment

```bash
cp .env.example .env
```

The example configuration provides these local defaults:

| Setting | Value |
| --- | --- |
| Application | http://localhost:8001 |
| MySQL from the host | 127.0.0.1:3307 |
| MySQL from Laravel | mysql:3306 |
| Development database | teamboard |
| Database username | sail |
| Database password | password |

These credentials are for local development.

### 2. Install PHP dependencies

Bootstrap dependencies without installing PHP or Composer on the host:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

### 3. Start Sail and check the runtime

```bash
./vendor/bin/sail up -d --wait
./vendor/bin/sail composer check-platform-reqs
```

The platform check verifies PHP and extension requirements inside the application container.

### 4. Initialize the application

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

Generate the application key once during first installation. Preserve an existing installation's key.

### 5. Build frontend assets

```bash
npm install
npm run build
```

### 6. Create an administrator

```bash
./vendor/bin/sail artisan app:create-admin
```

The command prompts for a name, email address, password, and password confirmation.

Passwords require at least 12 characters. Password entry is hidden, and existing email addresses are rejected.

Open http://localhost:8001 and sign in with the new account.

## Daily development

Start the containers:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail ps
```

Build assets after frontend changes:

```bash
npm run build
```

Alternatively, run Vite with live updates in a separate terminal:

```bash
./vendor/bin/sail npm run dev -- --host 0.0.0.0
```

Stop the containers while keeping their data:

```bash
./vendor/bin/sail stop
```

## Team workflow

1. An administrator creates user accounts.
2. An administrator creates a project and selects its members.
3. Members access their assigned projects and manage tasks.
4. Tasks can be assigned to active project members.
5. My Tasks shows the current user's accessible assignments.

Administrators can access all projects. Members require project membership.

Removing membership revokes access to that project's tasks.

## Verification

Check PHP dependency requirements:

```bash
./vendor/bin/sail composer check-platform-reqs
```

Run the existing test suite:

```bash
./vendor/bin/sail artisan test
```

Tests use the dedicated MySQL `testing` database configured in `phpunit.xml`.

Check frontend assets and Blade compilation:

```bash
npm run build
./vendor/bin/sail artisan view:cache
```

Clear compiled Blade views when needed:

```bash
./vendor/bin/sail artisan view:clear
```

Format changed PHP files:

```bash
./vendor/bin/sail pint --dirty
```

Check whitespace errors:

```bash
git diff --check
```

## Conventions

- Dates and deadline calculations use UTC.
- Project keys remain fixed after creation.
- Task numbers are allocated separately within each project.
- Conflicting task and checklist edits require reloading the latest version.
- Attachments are downloaded through authorized application routes.
- Assignment notifications appear inside the application.
- Shared Blade components provide layouts, forms, badges, cards, and summaries.

## Troubleshooting

### Sail is not running

Start Docker Desktop if applicable, then run:

```bash
./vendor/bin/sail up -d
```

### Database is still starting

Wait for MySQL to become healthy:

```bash
./vendor/bin/sail up -d --wait
./vendor/bin/sail ps
```

### Local port is occupied

Adjust `APP_PORT` or `FORWARD_DB_PORT` in `.env`, then restart Sail.

Update `APP_URL` when changing the application port.

Laravel's internal database settings remain `DB_HOST=mysql` and `DB_PORT=3306`.
