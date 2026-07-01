# DEVELOPMENT_GUIDE.md
## WTF Inventory Backend — Developer Guide

---

## Table of Contents

1. [Local Setup](#1-local-setup)
2. [Coding Standards](#2-coding-standards)
3. [Naming Conventions](#3-naming-conventions)
4. [Folder Conventions](#4-folder-conventions)
5. [Git Workflow](#5-git-workflow)
6. [Adding a New Tenant-Scoped Resource](#6-adding-a-new-tenant-scoped-resource)
7. [Adding a New Non-Tenant Resource](#7-adding-a-new-non-tenant-resource)
8. [Adding a New Route](#8-adding-a-new-route)
9. [Adding a New Email](#9-adding-a-new-email)
10. [Testing Strategy](#10-testing-strategy)
11. [Debugging](#11-debugging)
12. [Logging](#12-logging)
13. [Dependency Management](#13-dependency-management)
14. [Common Mistakes to Avoid](#14-common-mistakes-to-avoid)
15. [Pre-Deployment Checklist](#15-pre-deployment-checklist)

---

## 1. Local Setup

### Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.2+ | Tested on 8.2.12 |
| Composer | 2.x | |
| MySQL | 8.x | Via XAMPP, Homebrew, or Docker |
| Git | Any recent | |

### Step-by-Step Setup

```bash
# 1. Clone the repository
git clone https://github.com/Olami2596/inventory-backend.git
cd inventory-backend

# 2. Install PHP dependencies
composer install

# 3. Copy .env and configure it
cp .env.example .env

# 4. Generate APP_KEY
php artisan key:generate

# 5. Create the database (in MySQL)
mysql -u root -e "CREATE DATABASE inventory_db;"

# 6. Configure .env database settings
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=inventory_db
# DB_USERNAME=root
# DB_PASSWORD=

# 7. Run migrations
php artisan migrate

# 8. Start the development server (Terminal 1)
php artisan serve

# 9. Start the queue worker (Terminal 2 — required for emails)
php artisan queue:work
```

### Verify Setup

Send a test request:
```
POST http://127.0.0.1:8000/api/v1/register
Content-Type: application/json
{
  "company_name": "Test Co",
  "company_email": "test@test.com",
  "company_phone": "555-0000",
  "owner_name": "Test Owner",
  "owner_email": "owner@test.com",
  "owner_password": "password123",
  "owner_password_confirmation": "password123"
}
```

Expected: `201 Created` with user, company, and token.

---

## 2. Coding Standards

### PHP Style
- Follow PSR-12 coding standards.
- Use `strict_types=1` is not currently enabled — do not add it retroactively without testing.
- Type hints on all method parameters and return types where possible.
- No inline business logic in controllers. Controllers must remain thin.

### Laravel Conventions
- Controllers are thin: one call per action, no loops, no conditionals beyond simple permission checks.
- All validation in Form Request classes — never `$request->validate()` inside controllers (exception: single-field inline validation in PasswordResetController is acceptable).
- All business logic in models: relationships, casts, lifecycle hooks.
- All route-level protection in middleware.

### Code Organisation
- One class per file.
- No static utility classes (use Laravel helpers or Eloquent methods).
- No raw SQL unless using `DB::raw()` inside a query builder chain for aggregate expressions.

---

## 3. Naming Conventions

### Files and Classes

| Type | Convention | Example |
|---|---|---|
| Model | PascalCase singular | `InventoryTransaction` |
| Controller | PascalCase singular + Controller | `CategoryController` |
| Form Request | Store/Update + PascalCase + Request | `StoreCategoryRequest` |
| Middleware | EnsureXxx or descriptive | `EnsureUserHasRole` |
| Mailable | PascalCase + Mail | `InvitationMail` |
| Migration | snake_case descriptive | `create_categories_table` |
| Scope | PascalCase + Scope | `TenantScope` |

### Database

| Type | Convention | Example |
|---|---|---|
| Table | snake_case plural | `inventory_transactions` |
| Column | snake_case | `company_id`, `created_at` |
| Foreign key column | singular_table + _id | `company_id`, `category_id` |
| Foreign key constraint | table_column_foreign | `products_company_id_foreign` |
| Unique index | table_col1_col2_unique | `categories_company_id_name_unique` |

### Routes

```php
// Resources use plural kebab-case
/api/v1/categories
/api/v1/inventory-transactions   // hypothetical — currently /transactions

// Actions on a resource use {id}/verb pattern
/api/v1/users/{user}/deactivate
/api/v1/invitations/{invitation}/cancel
```

### Variables

| Type | Convention |
|---|---|
| Standard variables | camelCase |
| Models/Collections | camelCase matching model name |
| Magic strings (types, statuses) | avoid — use constants or Rule::in() |

---

## 4. Folder Conventions

```
app/Http/Controllers/Auth/     Auth-specific controllers only
app/Http/Controllers/          Resource controllers (one per resource)
app/Http/Middleware/            Custom middleware (registered as aliases)
app/Http/Requests/             Form Requests (Store + Update per resource)
app/Mail/                      Mailable classes
app/Models/                    Eloquent models
app/Models/Scopes/             Eloquent global scope classes
conf/nginx/                    Docker/production nginx configuration
database/migrations/           Migration files (one file per schema change)
resources/views/emails/        Email Blade templates only (no web views)
routes/api.php                 All routes (single file, no sub-files)
scripts/                       Docker container startup scripts
storage/certs/                 SSL certificates (CA certs only, not private keys)
```

---

## 5. Git Workflow

### Branches
- `main` — production-ready code. Auto-deploys to Render on every push.
- Feature branches — `feature/description-of-feature`
- Bug fix branches — `fix/description-of-fix`

### Commit Messages
Use descriptive, present-tense messages:
```
✓ Add image_url field to products
✓ Fix cancelled_at not excluded from duplicate invitation check
✓ Refactor DashboardController to split purchase/sale averages
✗ stuff
✗ fix bug
✗ WIP
```

### Before Every Commit
1. Run `php artisan route:list` to confirm no route conflicts.
2. Run `php artisan config:clear && php artisan route:clear` to clear caches.
3. Test any new or modified endpoint manually via Thunder Client.
4. If you modified a model, test cross-tenant isolation: create data as Company A, confirm Company B cannot see it.

### Before Pushing to `main`
1. Complete all items in Section 15 (Pre-Deployment Checklist).
2. Ensure `.env` is not staged (it should never be).
3. Verify `storage/certs/aiven-ca.pem` IS staged if it was created or updated.

---

## 6. Adding a New Tenant-Scoped Resource

This is the most common development task. Follow this checklist in order.

### Step 1: Migration

```bash
php artisan make:migration create_xxx_table
```

Required columns every tenant table must have:
```php
$table->id();
$table->foreignId('company_id')->constrained(); // ON DELETE RESTRICT
// ... resource-specific columns
$table->timestamps();
```

For columns that must be unique within a company:
```php
$table->unique(['company_id', 'name']); // composite, not single-column
```

**Before running the migration:** Paste the file contents back for review. Confirm the `up()` method has real column definitions before executing.

```bash
php artisan migrate
```

Verify in phpMyAdmin: confirm both the column structure AND the Indexes tab shows the correct composite unique indexes and foreign key relationships.

### Step 2: Model

```bash
php artisan make:model XxxYyy
```

Required structure for every tenant-scoped model:

```php
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class XxxYyy extends Model
{
    protected $fillable = [
        // Resource-specific fillable fields ONLY
        // NEVER include company_id
        'name',
        'description',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $model->company_id = auth()->user()->company_id;
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Add other relationships as needed
}
```

**Verify in Tinker:**
```php
auth()->login(App\Models\User::find(1));
App\Models\XxxYyy::create(['name' => 'Test']);
// Should show company_id auto-populated
App\Models\XxxYyy::all();
// Should return records for company 1 only
```

### Step 3: Form Requests

```bash
php artisan make:request StoreXxxYyyRequest
php artisan make:request UpdateXxxYyyRequest
```

Key patterns:
```php
// Tenant-scoped uniqueness (Store)
Rule::unique('xxx_yyys')->where('company_id', auth()->user()->company_id)

// Tenant-scoped uniqueness (Update — must ignore self)
Rule::unique('xxx_yyys')
    ->where('company_id', auth()->user()->company_id)
    ->ignore($this->route('xxx_yyy')->id)

// Tenant-scoped existence check (for foreign keys)
Rule::exists('categories', 'id')->where('company_id', auth()->user()->company_id)

// Optional-on-update field that is required-on-create
// Store: 'required'
// Update: ['sometimes', 'required', ...]

// Nullable fields that can be cleared on update
// Store: 'nullable'
// Update: 'nullable' (same — nullable handles both absent and explicit null)
```

### Step 4: Controller

```bash
php artisan make:controller XxxYyyController
```

Template:
```php
class XxxYyyController extends Controller
{
    public function index()
    {
        return XxxYyy::all(); // TenantScope handles filtering
    }

    public function store(StoreXxxYyyRequest $request)
    {
        $validated = $request->validated();
        $xxxYyy = XxxYyy::create($validated);
        return response()->json($xxxYyy, 201);
    }

    public function show(XxxYyy $xxxYyy)
    {
        return response()->json($xxxYyy, 200);
    }

    public function update(UpdateXxxYyyRequest $request, XxxYyy $xxxYyy)
    {
        $validated = $request->validated();
        $xxxYyy->update($validated);
        return response()->json($xxxYyy, 200);
    }

    public function destroy(XxxYyy $xxxYyy)
    {
        $xxxYyy->delete();
        return response()->json(null, 204);
    }
}
```

If the resource has relationships to eager-load on index/show:
```php
public function index()
{
    return XxxYyy::with(['relationship'])->get();
}

public function show(XxxYyy $xxxYyy)
{
    $xxxYyy->load(['relationship']);
    return response()->json($xxxYyy, 200);
}
// Note: use load() in show(), not with() — route model binding already ran
```

### Step 5: Routes

Add to `routes/api.php` inside the appropriate middleware group:

```php
// Inside Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(...)
Route::apiResource('xxx-yyys', XxxYyyController::class)->only(['index', 'show']);

// Inside Route::middleware('role:owner,admin')->group(...)
Route::apiResource('xxx-yyys', XxxYyyController::class)->only(['store', 'update', 'destroy']);
```

**Add the use import** at the top of api.php:
```php
use App\Http\Controllers\XxxYyyController;
```

**Verify with:**
```bash
php artisan route:list --path=xxx-yyys -v
```

Confirm:
- `index` and `show` have `auth:sanctum` + `active` but NOT role middleware
- `store`, `update`, `destroy` have `auth:sanctum` + `active` + `EnsureUserHasRole:owner,admin`

---

## 7. Adding a New Non-Tenant Resource

For resources that are not scoped to a company (e.g., system-wide configuration):

1. Do NOT add `TenantScope` or the `creating` company_id hook.
2. Do NOT add `company_id` to the table.
3. Manually scope any necessary queries in the controller.
4. Be very careful about what data is accessible — without TenantScope, all rows are visible unless explicitly filtered.

Currently the only non-tenant models are: `User` (has company_id but no scope — scoped manually), `Company`, `PasswordReset`, and the Sanctum/queue/cache tables.

---

## 8. Adding a New Route

### Public Route (no authentication)
Place OUTSIDE the `auth:sanctum` group, at the same level as `register`, `login`:
```php
Route::post('/your-route', [YourController::class, 'method'])->middleware('throttle:5,1');
```

### Authenticated Route (all roles)
Place INSIDE the `auth:sanctum` group but OUTSIDE the `role:owner,admin` group:
```php
Route::get('/your-route', [YourController::class, 'method']);
```

### Owner/Admin Only Route
Place INSIDE the nested `role:owner,admin` group:
```php
Route::post('/your-route', [YourController::class, 'method']);
```

### Rule: Never remove middleware from an existing route
Only add middleware to routes, never remove. If a route seems over-restricted, evaluate whether the restriction is correct before removing it.

---

## 9. Adding a New Email

```bash
php artisan make:mail YourMail
php artisan make:view emails.your-email
```

**Mailable class requirements:**
```php
class YourMail extends Mailable implements ShouldQueue // MUST implement ShouldQueue
{
    use Queueable, SerializesModels;

    public YourModel $model; // MUST be public for auto-injection into view

    public function __construct(YourModel $model)
    {
        $this->model = $model;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Subject');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.your-email'); // view path, not class
    }

    public function attachments(): array
    {
        return [];
    }
}
```

**Critical:** `ShouldQueue` is mandatory. Without it, the email is sent synchronously, blocking the HTTP response for the duration of the Resend API call. This caused 60-second timeouts during development.

**Blade template:** Access the model via `$model->property` since `public` properties are auto-injected.

**Dispatch:**
```php
Mail::to($email)->send(new YourMail($model));
// Queued automatically because ShouldQueue is implemented
```

**If adding email to a controller**, ensure the queue worker is running locally:
```bash
php artisan queue:work
```

---

## 10. Testing Strategy

Currently there is no automated test suite. All testing is manual via Thunder Client (VS Code extension).

### Manual Test Protocol for Any New Feature

**1. Happy path** — the expected success case with valid data

**2. Validation** — test each validation rule:
- Missing required field → expect 422 with the field name in `errors`
- Invalid value → expect 422
- Cross-tenant existence check → submit a valid-looking ID from another company → expect 422

**3. Role permissions**:
- Test with a role that should NOT have access → expect 403
- Test with a role that SHOULD have access → expect success

**4. Tenant isolation** (critical for every new resource):
- Create data as Company A
- Log in as Company B
- Attempt to list, view, update, delete Company A's data
- Expect: 404 on targeted access (route model binding filtered it), empty array on list

**5. Unauthenticated access**:
- Remove the Authorization header
- Expect 401

**6. Deactivated user**:
- Deactivate the user
- Attempt any authenticated request with their token
- Expect 403

### Future: Automated Tests

When adding automated tests, use Pest (preferred in modern Laravel). Test files should live in `tests/Feature/` for endpoint tests.

Example test structure:
```php
test('owner can create category', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    Sanctum::actingAs($owner);
    $response = $this->postJson('/api/v1/categories', ['name' => 'Test']);
    $response->assertStatus(201);
});

test('staff cannot create category', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    Sanctum::actingAs($staff);
    $response = $this->postJson('/api/v1/categories', ['name' => 'Test']);
    $response->assertStatus(403);
});
```

---

## 11. Debugging

### Artisan Commands

```bash
# See all registered routes with middleware
php artisan route:list -v

# See all registered routes for a specific path
php artisan route:list --path=categories -v

# Interactive shell (test models, queries, events)
php artisan tinker

# Clear all caches (use when config/routes seem stale)
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Check migration status
php artisan migrate:status

# Check queue status
php artisan queue:failed
php artisan queue:work --verbose  # see jobs as they process
```

### Tinker for Debugging

```php
// Simulate authentication for testing models
auth()->login(App\Models\User::find(1));

// Test that TenantScope is working
App\Models\Category::all();  // Should only return company 1's categories

// Test as a different company
auth()->login(App\Models\User::find(4)); // Company 3 user
App\Models\Category::all();  // Should only return company 3's categories (likely empty)

// Inspect a specific model
App\Models\Invitation::find(1);

// Test creating with auto-stamp
auth()->login(App\Models\User::find(1));
$inv = App\Models\Invitation::create(['email' => 'test@test.com', 'role' => 'staff']);
// Should show company_id and token auto-populated
```

### Reading Error Messages

When APP_DEBUG=true (local):
- Full stack traces are returned in JSON
- Look for `"exception"` and `"message"` fields at the top of the error response

Common errors and their causes:

| Error | Likely Cause |
|---|---|
| `RouteNotFoundException: Route [login] not defined` | Missing `redirectGuestsTo(fn() => null)` in bootstrap/app.php |
| `Call to a member function isPast() on string` | Missing `'datetime'` cast on a timestamp column in the model |
| `Call to a member function on null` | Middleware or check calling a method on `auth()->user()` when no user is authenticated |
| `SQLSTATE[42S22]: Column not found` | Migration was empty/incomplete when it ran; migration file wasn't saved before executing |
| `SQLSTATE[HY000] [2002] Connection refused` | Database server not running, or wrong DB_HOST/PORT in .env |
| `failed loading cafile stream` | `MYSQL_ATTR_SSL_CA` path is relative; must be absolute in production |
| `connect() failed (111: Connection refused) fastcgi` | Nginx config uses `127.0.0.1:9000` instead of `unix:/var/run/php-fpm.sock` |

---

## 12. Logging

### Local Development

```env
LOG_CHANNEL=stack
```

Logs are written to `storage/logs/laravel.log`. Tail this file during development:
```bash
tail -f storage/logs/laravel.log
```

### Production (Render)

```env
LOG_CHANNEL=stderr
```

All Laravel log output goes to stderr, captured by Render's logging infrastructure and visible in the Render dashboard Logs tab. This is the standard approach for containerised PHP applications.

### Adding Log Statements

Use Laravel's `Log` facade for debugging:
```php
use Illuminate\Support\Facades\Log;

Log::info('Invitation created', ['invitation_id' => $invitation->id]);
Log::error('Failed to process transaction', ['error' => $e->getMessage()]);
```

Log levels: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`.

Do not leave debug log statements in committed code. Remove them after debugging.

---

## 13. Dependency Management

### Adding a Package

```bash
composer require vendor/package-name
```

After adding:
1. Check `config/app.php` — some packages auto-discover but may need configuration.
2. Check if a `config/` file needs publishing: `php artisan vendor:publish --provider="VendorName\ServiceProvider"`
3. Check if a migration needs running.
4. Update `.env.example` if new environment variables are required.

### Updating Packages

```bash
composer update  # Updates all packages within version constraints
```

After updating, run:
```bash
php artisan config:clear
php artisan route:clear
php artisan migrate  # In case any package added migrations
```

### Never Update in Production Directly

All dependency updates should be tested locally and committed to `main`. Render rebuilds the image with `composer install` on every deploy — the `composer.lock` file is what pins exact versions.

---

## 14. Common Mistakes to Avoid

### 1. Putting company_id in $fillable

```php
// WRONG
protected $fillable = ['name', 'description', 'company_id'];

// CORRECT
protected $fillable = ['name', 'description'];
// company_id is stamped by the creating hook
```

### 2. Forgetting TenantScope or the creating hook

Every new tenant-owned model MUST have both in `booted()`. Missing either creates a security gap.

### 3. Using with() after route model binding in show()

```php
// WRONG — runs a second query after binding already ran one
public function show(Category $category)
{
    return response()->json(Category::with(['relationship'])->find($category->id));
}

// CORRECT — use load() on the already-resolved instance
public function show(Category $category)
{
    $category->load(['relationship']);
    return response()->json($category);
}
```

### 4. Using a relative path for MYSQL_ATTR_SSL_CA in production

```
# WRONG (fails in container)
MYSQL_ATTR_SSL_CA=storage/certs/aiven-ca.pem

# CORRECT (absolute path inside container)
MYSQL_ATTR_SSL_CA=/var/www/html/storage/certs/aiven-ca.pem
```

### 5. Not running php artisan queue:work locally

Without the queue worker running, emails are queued but never sent. Always run the queue worker in a second terminal during local development when testing invitation or password reset flows.

### 6. Running migrations without verifying the file was saved

Multiple bugs during this project occurred because a migration file was written in conversation but not saved to disk before running `php artisan migrate`. The migration ran successfully (because the file existed, just empty) but created no columns. Always paste the file contents back for review before running a migration.

### 7. Mixing up 401, 403, 404 semantics

- `401` — I don't know who you are (no valid token)
- `403` — I know who you are, but no (wrong role, deactivated)
- `404` — This doesn't exist (including cross-tenant access — always 404, never 403, to avoid leaking resource existence)

### 8. Adding business logic to controllers

Controllers are transport layer. If you find yourself writing a conditional, a loop, or a calculation in a controller method, it belongs in a model, a scope, or a Form Request.

### 9. Not testing with a staff token after adding a new write route

Always verify that staff cannot access write endpoints. After adding a new route to the `role:owner,admin` group, test with a staff Bearer token to confirm 403 is returned.

### 10. Forgetting that email sending requires the queue worker

`InvitationMail` and `PasswordResetMail` implement `ShouldQueue`. When you call `Mail::to()->send()`, the job is pushed to the database queue. Nothing sends until `php artisan queue:work` processes it. In production, the worker runs automatically via the deploy script.

---

## 15. Pre-Deployment Checklist

Before pushing to `main` (which triggers an auto-deploy):

- [ ] `APP_DEBUG=false` is set in Render's environment variables (baked into Dockerfile ENV but verify the Render env var is not overriding it to `true`)
- [ ] `SESSION_DRIVER=cookie` is set in Render's environment variables
- [ ] `LOG_CHANNEL=stderr` is set in Render's environment variables
- [ ] `MYSQL_ATTR_SSL_CA=/var/www/html/storage/certs/aiven-ca.pem` (absolute path) is set
- [ ] `storage/certs/aiven-ca.pem` is committed (git status shows it tracked)
- [ ] `.env` is NOT committed (git status shows it NOT tracked)
- [ ] All new migrations are tested locally and confirmed to have real column definitions
- [ ] `conf/nginx/nginx-site.conf` uses `fastcgi_pass unix:/var/run/php-fpm.sock;` (not TCP port)
- [ ] New endpoints have been tested for all three roles + cross-tenant + unauthenticated scenarios
- [ ] No `dd()` or debug `Log::debug()` statements left in committed code
- [ ] `php artisan route:list -v` shows no unexpected middleware gaps
- [ ] Commit message is descriptive and present-tense
