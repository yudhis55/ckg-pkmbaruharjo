# AGENTS.md

## Project Overview

CKG PKM Baruharjo ("MONICA") - internal dashboard for a Puskesmas (community health center). Manages patient data, staff claims, and syncs records from the national health platform (Sehat Indonesiaku / kemkes.go.id).

## Stack

- **Laravel 12** (PHP 8.2+), **Livewire 4** with single-file components (not Volt)
- **Flux UI** (Livewire component library by the Livewire team)
- **Tailwind CSS 4** via `@tailwindcss/vite`
- **Vite 7** for frontend build
- **ECharts** for dashboard charts
- **MySQL** (Laragon default, DB: `ckg-pkmbaruharjo`)
- **php-flasher** for flash notifications
- **spatie/livewire-filepond** for file uploads
- Runs on **Laragon** (Windows local dev)

## Developer Commands

```bash
# Full setup (install deps, generate key, migrate, build assets)
composer setup

# Dev server (artisan serve + queue + pail logs + vite, all concurrent)
composer dev

# Run tests (clears config cache first, uses in-memory SQLite)
composer test

# Lint/format PHP
./vendor/bin/pint

# Single artisan test file
php artisan test --filter=ExampleTest

# Fresh database with seeders
php artisan migrate:fresh --seed
```

## Architecture

### Routing and Pages (Livewire 4 single-file components)

All pages are **Livewire 4 single-file components** in `resources/views/pages/`.
The lightning-bolt prefix in filenames is Livewire 4's native convention for embedding
an anonymous PHP class directly in the blade template. This is NOT Volt - it is
Livewire 4's built-in single-file component feature.

Routes use `Route::livewire()` pointing to page paths:
- `pages::auth.login` -> `resources/views/pages/auth/` login blade
- `pages::dashboard.dashboard` -> `resources/views/pages/dashboard/` dashboard blade

**There are no traditional controllers for page rendering.** All logic lives in the
anonymous `new class extends Component {}` block at the top of each blade file.

### Layouts

- `resources/views/layouts/app.blade.php` - main authenticated layout (Flux sidebar)
- `resources/views/layouts/auth.blade.php` - guest/login layout

### Auth and Roles

- Simple role-based auth: `User.role` is either `admin` or `user`
- Middleware: `EnsureUserHasRole` (in `app/Http/Middleware/`)
- Admin-only pages: sinkronisasi, sinkronisasi-sekolah, pengaturan
- Default admin login (from seeder): `admin@baruharjo` / `admin123`

### Models and Database

| Model | Table | Notes |
|-------|-------|-------|
| Pasien | pasien | Custom table name (not pluralized). Uses `$guarded = ['id']`. |
| Pegawai | pegawai | Custom table name. Staff members. |
| User | users | Has `role` and `pegawai_id` columns. |
| Tahun | tahuns | Year/period reference data. |
| RiwayatSinkron | riwayat_sinkrons | Sync history log. |

**Important:** Pasien and Pegawai use non-standard singular table names. Always set
`$table` explicitly on new models if following this convention.

### Data Sync (External Service)

The sinkronisasi page calls a **local Python scraping service** at
`http://127.0.0.1:9999/scrape` that fetches patient data from
sehatindonesiaku.kemkes.go.id. This service must be running separately for sync
features to work. The Laravel app sends cookie headers and date ranges, receives
patient JSON, and upserts into the pasien table.

## Conventions

- **Indonesian language** for domain terms: pasien (patient), pegawai (staff), desa (village), kecamatan (district), tahun (year), sinkronisasi (sync)
- **Session-based year filter**: Dashboard components use `#[Session(key: 'tahun_session')]` to persist the selected year across pages
- **No API routes** - web-only app with Livewire for all interactivity
- **Queue connection**: database (requires `php artisan queue:listen` - handled by `composer dev`)

## Testing

- PHPUnit with in-memory SQLite (configured in `phpunit.xml`) - tests do NOT need MySQL
- Test suites: `tests/Unit` and `tests/Feature`
- Tests override DB_CONNECTION=sqlite and DB_DATABASE=:memory:

## Gotchas

- The lightning-bolt character in single-file component filenames can cause issues with some tools. When referencing these files in shell commands, quote or escape the path.
- `composer dev` uses `concurrently` (npm) to run 4 processes. If one crashes, all stop (`--kill-others`).
- MySQL must be running (Laragon handles this). DB: `ckg-pkmbaruharjo`, user: `root`, no password.
- Vite ignores `storage/framework/views/**` to avoid infinite reload loops from compiled Blade cache.
- App URL is `http://ckg-pkmbaruharjo.test:8080` (Laragon virtual host).