# Project Guidelines

## Build, test, and style checks

- Install PHP dependencies with `composer install` and frontend dependencies with `npm install`.
- Build frontend assets with `npm run build`; use `npm run dev` while developing.
- Run the PHPUnit suite with `php artisan test` (or `composer test`, which clears config first).
- Run one test file with `php artisan test tests/Feature/KasirReservationTest.php`, or select a test with `php artisan test --filter=test_ubah_status_reservasi`.
- There is no dedicated lint script. Laravel Pint is a dev dependency; check formatting with `composer exec pint -- --test`.

## Architecture

- This is a Laravel 12 app. `routes/web.php` separates public customer flows from the `/kasir` staff area; named routes and nested middleware groups define access.
- Customer sign-in uses WhatsApp verification and a `customer_id` session value. Staff use Laravel authentication and role middleware (`role:owner`, `role:kasir`, `role:operator`, `role:kurir`). Keep these two identity flows distinct.
- Controllers handle HTTP workflows, Eloquent models hold shared business relationships and state, and `app/Services` / `app/Support` contain integrations and reusable business helpers. The public and staff Blade pages use separate layouts under `resources/views/layouts`.
- Owner-editable business settings are stored in `storage/app/private/settings.json` via `App\Support\Settings`, not in a database table.
- The production laundry schema is not wholly represented by Laravel migrations: core tables are provisioned with SQL under `database/sql`, while migrations cover Laravel tables and selected newer features. Check both the relevant SQL and migrations before changing schema-dependent behavior.
- Frontend assets are built by Vite from `resources/css/app.css` and `resources/js/app.js`; Blade templates are included in Tailwind's source scan.

## Repository conventions

- Authorization is enforced on routes with role middleware, not only by hiding staff navigation items in Blade.
- Workflow states and display labels are centralized as constants and methods on their models (for example, `Order` and `Reservation`); use those definitions rather than duplicating state lists.
- Order totals and line prices are recalculated from database service prices on the server. Keep multi-record order creation and related payment/status records inside a database transaction.
- Feature tests use SQLite in memory (`phpunit.xml`). For tests that need the core laundry tables, use `Tests\Concerns\CreatesLaundryTables`; it mirrors the subset of the production schema used by tests.
- `Tests\TestCase` prevents unmocked outbound HTTP requests. Fake integrations with `Http::fake()`; use `Storage::fake('local')` for settings or other local-disk behavior.
