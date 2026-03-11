# WisePlay

WisePlay is a Docker-first streaming platform built with plain PHP, MySQL, jQuery, and a split UI strategy:
- public app at `/` with a Netflix-like onboarding and browsing experience
- admin panel at `/admin` using Materialize Admin assets integrated into the PHP app

The codebase is intentionally framework-free and organized in a simple controller/model/view/core structure.

## Current Status

This repository already includes a working base application with:
- Docker runtime with `php-apache`, `mysql`, and `phpmyadmin`
- public auth flow with email registration, verification code, password creation, login, and first-login onboarding redirects
- plan selection with FREE / STANDARD / PREMIUM rules and payment gating scaffold
- public profile selection and profile CRUD with plan limit enforcement
- public browse, title detail, and watch routes reading from MySQL
- basic watch progress persistence and seeded continue-watching data
- admin CRUD modules for users, profiles, titles, media, billing, and settings
- OneDrive OAuth scaffold and admin media upload foundation
- billing/payment abstraction with mock provider support
- CSRF protection, PDO prepared statements, session auth, output escaping, and audit logging

## Product Direction

WisePlay is being developed as a complete Netflix-like onboarding and playback flow for movies, series, and courses.

Target journey:
1. Signup with email
2. Verify 6-digit code
3. Create password
4. Login
5. Select plan
6. Complete payment step when required
7. Create at least one profile
8. Choose viewing preferences
9. Enter personalized home page
10. Start playback with resume / restart / continue watching

## Stack

- PHP 8.2
- Apache
- MySQL 8.4
- jQuery
- Materialize Admin theme assets for `/admin`
- SweetAlert2
- Microsoft Graph OAuth for OneDrive Personal
- Docker Compose

## Project Structure

```text
app/
  config/
  controllers/
  core/
  helpers/
  models/
  services/
  views/
database/
  migrations/
  schema.sql
  seed.sql
docker/
  php/
public/
  assets/
storage/
```

## Main Routes

### Public App
- `/`
- `/register`
- `/register/verify`
- `/register/password`
- `/login`
- `/onboarding`
- `/onboarding/plan`
- `/onboarding/payment`
- `/profiles`
- `/browse`
- `/title/{id}`
- `/watch/{id}`

### Admin Panel
- `/admin/login`
- `/admin`
- `/admin/users`
- `/admin/profiles`
- `/admin/titles`
- `/admin/media`
- `/admin/billing`
- `/admin/settings`
- `/admin/me`

## Docker Quick Start

### 1. Copy environment file

```bash
cp .env.example .env
```

### 2. Start containers

```bash
docker compose up -d --build
```

### 3. Import schema and seed

```bash
docker compose exec -T mysql mysql -uroot -proot wiseplay_php < database/schema.sql
docker compose exec -T mysql mysql -uroot -proot wiseplay_php < database/seed.sql
```

### 4. Open the app

- Public app: `http://localhost:8080`
- Admin login: `http://localhost:8080/admin/login`
- phpMyAdmin: `http://localhost:8081`

## Default Local Credentials

Seed password for all seed users:
- `123456`

Recommended local admin account:
- email: `admin@wiseplay.local`
- password: `123456`

Sample viewer accounts also exist in `database/seed.sql`.

## Environment Notes

Important `.env` values for Docker execution:

```env
APP_URL=http://localhost:8080
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=wiseplay_php
DB_USERNAME=wiseplay_user
DB_PASSWORD=wiseplay_pass
ONEDRIVE_UPLOAD_CHUNK_MB=10
ONEDRIVE_UPLOAD_MAX_VIDEO_GB=10
```

The PHP container serves the project through Apache, and MySQL is reached by container hostname `mysql`.

## Useful Docker Commands

### Start / stop

```bash
docker compose up -d
docker compose down
```

### Reset database volume

```bash
docker compose down -v
docker compose up -d
```

### Logs

```bash
docker compose logs -f app
docker compose logs -f mysql
```

### PHP lint inside container

```bash
docker compose exec app sh -lc "find app public -name '*.php' -print0 | xargs -0 -n1 php -l"
```

## Database Model Highlights

Core tables already present in `database/schema.sql`:
- `users`
- `email_verification_codes`
- `profiles`
- `profile_preferences`
- `titles`
- `media_items`
- `media_materials`
- `billing_plans`
- `subscriptions`
- `payment_methods`
- `payment_transactions`
- `payment_events`
- `settings`
- `watch_history`
- `audit_logs`

Business rules already reflected in schema and seed:
- FREE => max 1 profile
- STANDARD => max 3 profiles
- PREMIUM => max 5 profiles
- every user has a subscription/plan context
- first profile exists in seeded sample accounts
- courses use module/lesson semantics through season/episode style media structure

## OneDrive Notes

Current OneDrive integration is OAuth-based only.

Implemented/scaffolded areas:
- Microsoft Graph OAuth settings in admin
- token storage in settings
- quota cache settings
- upload chunk configuration
- media upload session foundation for OneDrive

Environment and settings already support:
- chunk size tuning
- retry count / backoff
- upload debug mode
- root folder definition
- quota refresh actions in admin settings

## Public Onboarding Roadmap

The public product roadmap is being implemented in phases.

### Phase A — Registration and Verification
Status: implemented base flow
- email signup
- 6-digit verification code flow
- resend and expiration rules
- password creation
- resume onboarding for incomplete accounts

### Phase B — First Login, Plan Selection, Payment Gating
Status: implemented base flow
- login redirects to next pending onboarding step
- plan selection for FREE / STANDARD / PREMIUM
- admin-controlled plan availability
- payment abstraction with mock provider
- paid plans can be blocked when gateway is disabled

### Phase C — Profiles
Status: implemented base flow, subject to UX refinement
- profile selection
- profile create/update/delete
- plan profile limit enforcement
- kids flag support

### Phase D — Preferences Onboarding
Status: schema and personalization basis present, UX flow still to be finalized
- `profile_preferences` table exists
- seeded preferences already influence content direction
- dedicated first-run taste selection flow still needs completion/polish

### Phase E — Personalized Home
Status: partially implemented
- browse page and featured content exist
- seeded continue-watching data exists
- recommendation ordering and onboarding coupling still need refinement

### Phase F — Playback Continuity
Status: basic implementation present
- watch route exists
- watch progress persistence endpoint exists
- continue/resume behavior needs further Netflix-like polish
- subtle thumbnail progress bars and richer next-up flows still need refinement

### Phase G — Course Experience
Status: partial
- courses are modeled as titles with lesson-like media items
- `media_materials` table exists
- downloadable lesson materials are seeded
- public lesson materials UX still needs completion/polish

### Phase H — Admin Support for Onboarding Lifecycle
Status: partial to strong foundation
- users, billing, settings, and profiles modules already exist
- more onboarding-specific admin controls and visibility can still be expanded

## Implementation Priorities

Next delivery priority for the public app:
1. registration + email verification + password creation polish
2. first login plan selection + plan gating polish
3. profile creation + preference onboarding completion
4. playback progress + continue/restart + visual progress bars
5. course lesson materials download button + admin support refinements

## Manual Smoke Tests

### Public auth and onboarding
```bash
curl -I http://localhost:8080/login
curl -I http://localhost:8080/register
curl -I http://localhost:8080/onboarding/plan
```

### Admin
```bash
curl -I http://localhost:8080/admin/login
curl -I http://localhost:8080/admin
```

### Media and settings pages
```bash
curl -I http://localhost:8080/admin/media
curl -I http://localhost:8080/admin/settings
```

## Security Baseline

Implemented baseline rules in the current codebase:
- PDO prepared statements only
- CSRF checks for mutating routes
- session auth and admin route protection
- password hashing with `password_hash`
- output escaping in views
- secret masking in settings
- audit logging for critical flows
- no username/password login for OneDrive integration
- no raw payment card storage

## Repository Notes

The runtime application uses only the PHP code under `app/`, the public assets under `public/`, and the SQL bootstrap files under `database/`.
Legacy migration references remain only when they still help explain business rules that were already rebuilt in PHP.

## Development Notes

- Runtime is Docker-first. Do not assume host-installed PHP or MySQL.
- Use `database/schema.sql` and `database/seed.sql` as the primary bootstrap source.
- Use `database/migrations/` for incremental phase patches.
- Public UI and admin UI are intentionally separate visual systems.

## License / Asset Notes

- WisePlay branding and public UI are original implementation targets inspired by streaming UX patterns.
- Do not copy proprietary Netflix assets.
- OneDrive integration must use OAuth 2.0 / Microsoft Graph only.
