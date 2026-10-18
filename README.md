# Teamboard

A project and task management application built with Laravel 12, Blade, Tailwind CSS 4, and vanilla JavaScript.

## Features

- Session authentication, account activation, and password changes.
- Admin management of users, projects, and memberships.
- Tasks with priorities, assignees, due dates, and project references.
- List and Kanban views with protection against conflicting edits.
- Comments, checklists, private attachments, and activity history.
- Personal task lists, project summaries, and assignment notifications.
- Responsive layouts and a collapsible desktop sidebar.

## Initial setup

These instructions are for a fresh checkout. Existing installations should use the Daily development section.

### 1. Prepare your tools

Required:

- Git.
- Docker with Docker Compose.
- Node.js 22 with npm.
- WSL2 when developing on Windows.

On Windows, start Docker Desktop and enable integration for your WSL distribution.

Run these commands from your Linux or WSL terminal:

```bash
git --version
docker --version
docker compose version
docker info --format '{{.ServerVersion}}'
node -v
npm -v
```

Docker must be running before continuing.

PHP and Composer run inside Docker. They do not need to be installed on the host.

### 2. Get the project files

Clone the Teamboard repository into your Linux home directory, then open the checkout:

```bash
cd ~/teamboard
```

Use your actual checkout path if it differs.

### 3. Create the environment file

```bash
cp .env.example .env
```

The example provides these local settings:

```dotenv
APP_NAME=Teamboard
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8001
APP_PORT=8001
FORWARD_DB_PORT=3307

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=teamboard
DB_USERNAME=sail
DB_PASSWORD=password
```

The password above is a local development default.

Check that the application, MySQL, and Vite ports are available:

```bash
ss -ltn '( sport = :8001 or sport = :3307 or sport = :5173 )'
```

If a port is occupied, adjust the corresponding setting before starting Sail:

- `APP_PORT`: application port.
- `FORWARD_DB_PORT`: host MySQL port.
- `VITE_PORT`: Vite port; defaults to 5173.
- `APP_URL`: must match the application address.

Laravel connects to MySQL inside Docker using `mysql:3306`.

### 4. Install PHP dependencies

From the project root:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

This installs the locked dependencies, including Sail, using the current user's file ownership.

The temporary Composer container may report that its cache is unavailable. Installation can still complete without that cache.

### 5. Start the containers

```bash
./vendor/bin/sail up -d --wait
./vendor/bin/sail ps
```

Wait for MySQL to become healthy.

Check the actual application runtime:

```bash
./vendor/bin/sail composer check-platform-reqs
```

Every requirement should report success.

Sail uses PHP 8.2. Composer dependency resolution targets PHP 8.2.29; the validated local runtime was PHP 8.2.34.

### 6. Initialize the application

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:status
```

Generate the application key once during first installation. Preserve the key when maintaining an existing installation.

All migrations should show `Ran`.

### 7. Install and build frontend assets

```bash
npm install
npm run build
```

The build generates `public/build/manifest.json` and the application assets.

### 8. Create the first administrator

```bash
./vendor/bin/sail artisan app:create-admin
```

Enter:

1. Name.
2. Email address.
3. Password containing at least 12 characters.
4. Password confirmation.

Password entry is hidden. Existing email addresses are rejected.

### 9. Verify the installation

```bash
./vendor/bin/sail artisan view:cache
./vendor/bin/sail artisan test

curl -s -o /dev/null -w 'Login HTTP %{http_code}\n' \
    http://localhost:8001/login
```

The login endpoint should return HTTP 200.

Open http://localhost:8001 and sign in with the administrator account. Confirm that the dashboard loads.

Tests use the separate MySQL `testing` database configured in `phpunit.xml`.

## Optional: validate a separate checkout

Use this procedure to test installation alongside an existing local Teamboard installation.

Create a separate clone:

```bash
cd ~

git clone --no-local ~/teamboard ~/teamboard-setup-check &&
    cd ~/teamboard-setup-check &&
    cp .env.example .env
```

In the new checkout's `.env`, replace the application URL and port settings with:

```dotenv
APP_URL=http://localhost:8002
APP_PORT=8002
FORWARD_DB_PORT=3308
VITE_PORT=5174
COMPOSE_PROJECT_NAME=teamboard-setup-check
```

The distinct Compose project name creates separate containers, a network, and a MySQL volume.

Verify the settings and available ports:

```bash
pwd

grep -E '^(APP_URL|APP_PORT|FORWARD_DB_PORT|VITE_PORT|COMPOSE_PROJECT_NAME|DB_HOST|DB_DATABASE)=' .env

ss -ltn '( sport = :8002 or sport = :3308 or sport = :5174 )'
```

Continue from Initial setup, step 4, inside this new checkout.

After starting Sail, verify its volume:

```bash
docker volume ls \
    --filter label=com.docker.compose.project=teamboard-setup-check
```

Expected volume:

```text
teamboard-setup-check_sail-mysql
```

Use http://localhost:8002 for browser checks and the login HTTP check.

Stop this installation when finished:

```bash
cd ~/teamboard-setup-check &&
    ./vendor/bin/sail stop
```

This keeps its database data available for later use.

## Daily development

Start the containers:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail ps
```

Build frontend changes:

```bash
npm run build
```

Alternatively, run Vite with live updates in a separate terminal:

```bash
./vendor/bin/sail npm run dev -- --host 0.0.0.0
```

Run tests:

```bash
./vendor/bin/sail artisan test
```

Format changed PHP files:

```bash
./vendor/bin/sail pint --dirty
```

Clear compiled Blade views when needed:

```bash
./vendor/bin/sail artisan view:clear
```

Stop the containers:

```bash
./vendor/bin/sail stop
```

## Team workflow

1. An administrator creates user accounts.
2. An administrator creates projects and selects their members.
3. Members access their assigned projects and manage tasks.
4. Tasks can be assigned to active project members.
5. My Tasks shows the current user's accessible assignments.

Administrators can access all projects. Members require project membership.

Removing membership revokes access to that project's tasks.

## Conventions

- Dates and deadline calculations use UTC.
- Project keys remain fixed after creation.
- Task numbers are allocated separately within each project.
- Conflicting task and checklist edits require reloading the latest version.
- Attachments are downloaded through authorized application routes.
- Assignment notifications appear inside the application.
- Shared Blade components provide layouts, forms, badges, cards, and summaries.

## Setup validation

A separate local checkout was validated on October 18, 2026:

- Composer dependencies installed from the lock file.
- Application runtime requirements passed on PHP 8.2.34.
- Separate containers and MySQL volume were created.
- All 13 migrations completed.
- Frontend installation and build completed.
- First administrator was created.
- Blade compilation passed.
- All 140 tests passed with 1,213 assertions.
- Login returned HTTP 200.
- Administrator sign-in and dashboard access were confirmed.

## Troubleshooting

### Sail is not running

Start Docker Desktop if applicable, then run:

```bash
./vendor/bin/sail up -d
```

### MySQL is still starting

```bash
./vendor/bin/sail up -d --wait
./vendor/bin/sail ps
```

### A local port is occupied

Change the corresponding port in `.env`. Update `APP_URL` if the application port changes.

Apply the changed container configuration:

```bash
./vendor/bin/sail up -d
```

Laravel's internal database settings remain `DB_HOST=mysql` and `DB_PORT=3306`.
