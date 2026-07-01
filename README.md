# WTF Inventory Backend

A production-ready, multi-tenant SaaS Inventory Management System built with Laravel 12. Designed to support multiple independent businesses (tenants) on a single deployment, with complete data isolation, role-based access control, and a full audit trail for all inventory movements.

---

## Table of Contents

- [Features](#features)
- [Technology Stack](#technology-stack)
- [Architecture Overview](#architecture-overview)
- [Folder Structure](#folder-structure)
- [Installation](#installation)
- [Environment Variables](#environment-variables)
- [Local Development](#local-development)
- [Database Setup](#database-setup)
- [Authentication](#authentication)
- [API Overview](#api-overview)
- [Deployment](#deployment)
- [Security](#security)
- [Performance Considerations](#performance-considerations)
- [Known Limitations](#known-limitations)
- [Future Improvements](#future-improvements)
- [Contributing](#contributing)
- [License](#license)

---

## Features

### Core Business Features
- **Multi-tenant architecture** — complete data isolation between companies using a global Eloquent scope
- **Role-based access control** — three roles: `owner`, `admin`, `staff` with clearly defined permissions per route
- **Categories** — tenant-scoped product categorisation with composite uniqueness per company
- **Suppliers** — vendor management with contact details and duplicate-detection on both name and email
- **Products** — full inventory catalogue with SKU management, pricing (cost and sale price), image URL, and cross-tenant-safe foreign key validation
- **Inventory Transactions** — an append-only audit ledger recording every stock movement (purchase, sale, adjustment) with signed quantities and automatic `current_stock` updates
- **Dashboard** — real-time aggregated analytics including inventory value, low-stock alerts, recent activity, and weekly/monthly sales comparisons
- **User Invitations** — owner/admin can invite staff by email; invitations expire after 7 days, can be cancelled, and are protected against duplicate-pending abuse
- **Password Reset** — full forgot/reset flow with time-limited tokens (1 hour) and user-enumeration protection
- **User Management** — deactivate/reactivate users with role-aware permission boundaries; revoke all active sessions for self or managed users
- **Email Delivery** — queued email sending via Resend for both invitations and password resets

### Infrastructure Features
- Token-based API authentication via Laravel Sanctum
- JSON-only API error responses (no HTML error pages)
- Rate limiting on all public endpoints (tiered by risk profile)
- Queue-based background job processing (database driver)
- SSL-encrypted database connections (Aiven CA certificate)
- Production deployment via Docker on Render with Aiven MySQL

---

## Technology Stack

| Layer | Technology | Version |
|---|---|---|
| Framework | Laravel | 12.x |
| Language | PHP | 8.2+ |
| Authentication | Laravel Sanctum | 4.x |
| Database | MySQL | 8.x |
| Queue Driver | Database (MySQL) | — |
| Email Transport | Resend | via `resend/resend-laravel` |
| Web Server | Nginx | 1.24 |
| PHP Process Manager | PHP-FPM | — |
| Container Base | `richarvey/nginx-php-fpm` | latest |
| Container Orchestration | Docker | — |
| Hosting | Render | Free tier web service |
| Managed Database | Aiven for MySQL | Free tier |
| CI/CD | GitHub → Render auto-deploy | — |

---

## Architecture Overview

This is a **pure REST API backend** — there is no frontend bundled with this project. It is designed to be consumed by a React (or any other) frontend, mobile application, or third-party integration.

The architecture follows these core principles:

1. **Tenant isolation at the model layer** — a `TenantScope` global Eloquent scope automatically filters every query for tenant-owned models. Controllers never need to manually add `WHERE company_id = ?` clauses.

2. **Write-protection at the model layer** — a `creating` event on every tenant-owned model auto-stamps `company_id` from the authenticated user. Clients can never supply or override `company_id`.

3. **Authorization at the route layer** — `auth:sanctum` and `EnsureUserHasRole` middleware gate access before controllers are reached.

4. **Validation at the Form Request layer** — every write operation uses a dedicated Form Request class with business-rule-aware validation (tenant-scoped uniqueness, tenant-scoped existence checks, cross-field constraints).

5. **Business logic at the model layer** — lifecycle hooks (`creating`, `created`) handle automatic field population and cross-model side effects (e.g., updating `current_stock` when a transaction is created).

---

## Folder Structure

```
inventory-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── LogoutController.php
│   │   │   │   └── RegisterController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── InventoryTransactionController.php
│   │   │   ├── InvitationController.php
│   │   │   ├── PasswordResetController.php
│   │   │   ├── ProductController.php
│   │   │   ├── SupplierController.php
│   │   │   └── UserController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureUserHasRole.php
│   │   │   └── EnsureUserIsActive.php
│   │   └── Requests/
│   │       ├── AcceptInvitationRequest.php
│   │       ├── LoginRequest.php
│   │       ├── RegisterCompanyRequest.php
│   │       ├── ResetPasswordRequest.php
│   │       ├── StoreCategoryRequest.php
│   │       ├── StoreInvitationRequest.php
│   │       ├── StoreProductRequest.php
│   │       ├── StoreSupplierRequest.php
│   │       ├── StoreTransactionRequest.php
│   │       ├── UpdateCategoryRequest.php
│   │       ├── UpdateProductRequest.php
│   │       └── UpdateSupplierRequest.php
│   ├── Mail/
│   │   ├── InvitationMail.php
│   │   └── PasswordResetMail.php
│   └── Models/
│       ├── Scopes/
│       │   └── TenantScope.php
│       ├── Category.php
│       ├── Company.php
│       ├── InventoryTransaction.php
│       ├── Invitation.php
│       ├── PasswordReset.php
│       ├── Product.php
│       ├── Supplier.php
│       └── User.php
├── bootstrap/
│   └── app.php                    # Middleware aliases and redirectGuestsTo
├── conf/
│   └── nginx/
│       └── nginx-site.conf        # Custom Nginx config for Docker deployment
├── config/
│   ├── database.php               # MySQL SSL options for Aiven
│   ├── mail.php
│   └── services.php               # Resend API key mapping
├── database/
│   └── migrations/                # 16 migration files
├── resources/
│   └── views/
│       └── emails/
│           ├── invitation.blade.php
│           └── password-reset.blade.php
├── routes/
│   └── api.php                    # All API routes
├── scripts/
│   └── 00-laravel-deploy.sh       # Docker startup script (composer, cache, migrate, queue)
├── storage/
│   └── certs/
│       └── aiven-ca.pem           # Aiven SSL CA certificate (safe to commit — public material)
├── .dockerignore
├── .env.example
├── .gitignore
├── Dockerfile
└── composer.json
```

---

## Installation

### Prerequisites

- PHP 8.2+
- Composer 2.x
- MySQL 8.x (local) or Aiven MySQL (production)
- Node.js (only needed if modifying frontend assets — not required for API-only development)

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/Olami2596/inventory-backend.git
cd inventory-backend

# 2. Install PHP dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Configure your .env file (see Environment Variables section)

# 6. Run migrations
php artisan migrate

# 7. Start the development server
php artisan serve
```

---

## Environment Variables

All variables below must be present in `.env` for local development. For production, set them as environment variables in Render's dashboard — never commit a production `.env` file.

```env
# Application
APP_NAME="Inventory Backend"
APP_ENV=local                          # Set to "production" in production
APP_KEY=base64:...                     # Generated by php artisan key:generate
APP_DEBUG=true                         # Set to false in production
APP_URL=http://localhost:8000          # Set to your Render URL in production

# Database (local XAMPP/MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory_db
DB_USERNAME=root
DB_PASSWORD=

# Database SSL (only needed in production, pointing to Aiven)
# MYSQL_ATTR_SSL_CA=/var/www/html/storage/certs/aiven-ca.pem

# Queue
QUEUE_CONNECTION=database

# Mail
MAIL_MAILER=resend
MAIL_FROM_ADDRESS="onboarding@resend.dev"   # Replace with verified domain in production
MAIL_FROM_NAME="${APP_NAME}"

# Resend
RESEND_API_KEY=re_...                  # From Aiven dashboard

# Session (set to cookie for API-only usage)
SESSION_DRIVER=cookie

# Logging
LOG_CHANNEL=stack                      # Use "stderr" in production for Render log capture
```

---

## Local Development

```bash
# Start the development server
php artisan serve

# Start the queue worker (required for email sending)
php artisan queue:work

# Clear all caches during development
php artisan config:clear && php artisan route:clear && php artisan cache:clear

# Run migrations fresh (drops all tables)
php artisan migrate:fresh

# Interactive PHP shell
php artisan tinker
```

> **Important:** Email sending is queued. You must run `php artisan queue:work` in a separate terminal window, or emails will silently queue but never be dispatched.

---

## Database Setup

### Local

1. Create a MySQL database named `inventory_db` via phpMyAdmin or MySQL CLI:
   ```sql
   CREATE DATABASE inventory_db;
   ```
2. Ensure your `.env` `DB_*` variables match your local MySQL credentials.
3. Run `php artisan migrate` to create all tables.

### Production (Aiven)

1. Create a free MySQL service at [aiven.io](https://aiven.io).
2. Download the CA certificate from the Aiven dashboard.
3. Place the certificate at `storage/certs/aiven-ca.pem` and commit it (it is public material — safe to version-control).
4. Set all `DB_*` environment variables in Render's dashboard using Aiven's connection details.
5. Set `MYSQL_ATTR_SSL_CA=/var/www/html/storage/certs/aiven-ca.pem` in Render's environment variables.
6. Migrations run automatically on each deploy via `scripts/00-laravel-deploy.sh`.

---

## Authentication

This API uses **Laravel Sanctum** token-based authentication.

### Flow

1. **Register** — `POST /api/v1/register` creates a company and an owner user, returns a plain-text token.
2. **Login** — `POST /api/v1/login` validates credentials and returns a token.
3. **All protected routes** — include the token as a Bearer token in the `Authorization` header:
   ```
   Authorization: Bearer 1|abc123...
   ```
4. **Logout** — `POST /api/v1/logout` deletes the current token.
5. **Deactivated users** — blocked at login (`LoginController`) and for existing tokens (`EnsureUserIsActive` middleware).

### Headers Required

For all requests:
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>   (for protected routes)
```

---

## API Overview

Base URL (production): `https://inventory-backend-3ktz.onrender.com`
Base URL (local): `http://127.0.0.1:8000`

All endpoints are prefixed with `/api/v1/`.

| Category | Endpoints |
|---|---|
| Auth | Register, Login, Logout |
| Password Reset | Forgot Password, Reset Password |
| Invitations | List, Send, Accept, Cancel |
| Users | List, Deactivate, Reactivate, Revoke Tokens (self + admin) |
| Categories | Full CRUD |
| Suppliers | Full CRUD |
| Products | Full CRUD |
| Transactions | List, Show, Create (no update/delete by design) |
| Dashboard | Summary analytics |

See `API_REFERENCE.md` for complete documentation of every endpoint.

---

## Deployment

### Current Setup

- **Platform:** Render (Free tier web service)
- **Runtime:** Docker (`richarvey/nginx-php-fpm:latest`)
- **Database:** Aiven for MySQL (Free tier)
- **Email:** Resend
- **CI/CD:** Auto-deploy on push to `main` branch

### Deploy Process

1. Push to `main` branch on GitHub.
2. Render detects the push and rebuilds the Docker image.
3. The `scripts/00-laravel-deploy.sh` script runs automatically (via `RUN_SCRIPTS=1`):
   - `composer install --no-dev`
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan migrate --force`
   - `php artisan queue:work --tries=3 --timeout=90 &` (background process)
4. Supervisord starts Nginx and PHP-FPM.
5. Service goes live.

### Key Docker Files

| File | Purpose |
|---|---|
| `Dockerfile` | Builds the production image using `richarvey/nginx-php-fpm` |
| `.dockerignore` | Excludes `.env`, `vendor/`, and other non-essential files |
| `conf/nginx/nginx-site.conf` | Custom Nginx config; sets `fastcgi_pass unix:/var/run/php-fpm.sock` |
| `scripts/00-laravel-deploy.sh` | Runs on container start before Supervisord launches services |

> **Critical:** The Nginx config must use `fastcgi_pass unix:/var/run/php-fpm.sock` — NOT `127.0.0.1:9000`. The `richarvey/nginx-php-fpm` image configures PHP-FPM to listen on a Unix socket, not a TCP port.

---

## Security

- **Tenant isolation:** `TenantScope` global scope prevents cross-tenant data access at the database query level.
- **Write-path protection:** `company_id` is never in `$fillable` on tenant-owned models; it is auto-stamped via a `creating` hook.
- **Cross-tenant relationship validation:** `Rule::exists()->where('company_id', ...)` prevents products referencing categories/suppliers from other tenants.
- **Token authentication:** Sanctum Bearer tokens; no sessions for API routes.
- **Deactivated user blocking:** Blocked at both login and on every subsequent authenticated request.
- **Rate limiting:** Public endpoints are throttled (3–10 req/min); authenticated endpoints are throttled at 60 req/min.
- **User enumeration protection:** Password reset always returns the same response regardless of whether the email exists.
- **`APP_DEBUG=false` in production:** Baked into the Dockerfile `ENV` — cannot be accidentally left `true`.
- **SSL database connection:** Aiven CA certificate enforced via PDO SSL options.
- **Invite-only user creation:** No open registration for staff/admin; only owners/admins can invite new users.

---

## Performance Considerations

- **Route and config caching** run on every deploy (`artisan config:cache`, `artisan route:cache`).
- **Queued email** — Resend API calls are dispatched to the database queue, preventing HTTP timeouts on invitation/reset endpoints.
- **`TenantScope`** adds a single indexed `WHERE company_id = ?` clause to every query — negligible overhead, guaranteed safety.
- **Eager loading** — `index()` and `show()` methods on Products and Transactions eager-load relationships (`with(['category', 'supplier'])`) to avoid N+1 queries.
- **Dashboard aggregates** — computed with single SQL aggregate queries (`SUM`, `COUNT`, `AVG`) rather than fetching collections and computing in PHP.
- **Free-tier caveats** — Render's free tier spins down after inactivity. First request after sleep may take 30–60 seconds. Aiven's free MySQL is in a shared environment with connection limits.

---

## Known Limitations

1. **Queue worker is co-located with the web process** — runs as a background process inside the same container via `& `. If the container restarts, queued jobs are not automatically re-picked-up until the next restart. No supervisor-level process management for the worker.
2. **No file upload** — product images are stored as external URLs, not uploaded files. No S3 or CDN integration.
3. **No frontend** — this is a backend-only API. Invitation and password-reset email links point to a placeholder URL (`https://yourapp.com/...`).
4. **Email sending domain** — uses Resend's `onboarding@resend.dev` shared domain. In production with real users, a verified custom domain should be configured.
5. **Free-tier Render** — Pre-Deploy Command is not available on the free tier; migrations run via the deploy script instead.
6. **No automated test suite** — all testing was done manually via Thunder Client throughout development. PHPUnit/Pest tests should be added before the project serves real production traffic.
7. **No soft-deletes on business models** — deleting a Category that still has Products will fail with a database constraint error (RESTRICT). There is no "archive" or "discontinue" feature.

---

## Future Improvements

- React frontend application
- Real file upload (S3/Cloudinary) for product images
- Top/worst-moving products analytics
- Supplier purchase activity analytics
- Automated test suite (PHPUnit/Pest)
- Soft deletes and "discontinue product" feature
- Proper queue worker as a separate Render Background Worker service
- Custom verified email domain via Resend
- API versioning strategy
- Webhook support for inventory events

---

## Contributing

This project was built as a learning project. If you are extending it:

1. Read `DEVELOPMENT_GUIDE.md` before making any changes.
2. Follow the existing patterns — every new tenant-owned model needs `TenantScope` + `creating` hook + `company_id` excluded from `$fillable`.
3. Every new write endpoint needs a Form Request with tenant-scoped validation.
4. Do not add `company_id` to any Form Request `rules()` — it must never come from the client.
5. Run `php artisan route:list -v` after adding routes to verify middleware stacking.
6. Test all three user roles and cross-tenant scenarios before considering a feature complete.

---

## License

This project is open source for learning and portfolio purposes. No formal license is currently applied.
