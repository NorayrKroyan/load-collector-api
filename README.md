# LoadImport API - Final Current-State README

## 1. Purpose of this service

This Laravel service exposes a small authenticated API for two main tasks:

1. Return the list of import jobs that the external collector should process.
2. Accept and archive incoming load payloads, plus an optional BOL image.

This is the final current-state design.

### Current source of truth
- Import job list comes from the physical table `loadimport_jobs`
- Incoming archived payloads are stored in `loadimports`
- Authentication uses Laravel Sanctum personal access tokens
- API user records are stored in `users`
- Issued API tokens are stored in `personal_access_tokens`

There is no dependency in the final design on any legacy jobs table or SQL view.

---

## 2. Endpoints

### GET `/api/importjobs/list`
Returns the list of jobs from `loadimport_jobs`.

#### Auth
Bearer token required.

#### Response shape
```json
{
  "items": [
    {
      "jobname": "Jonah Studhorse Butte",
      "signature": []
    }
  ]
}
```

### POST `/api/loads/push`
Accepts a multipart request with:
- `payload` required
- `bolimage` optional

#### Validation logic
- `payload` is required
- `bolimage` must be a valid image file if present
- `bolimage` max size is 8192 KB

#### Accepted payload modes
1. `payload` uploaded as a JSON file
2. `payload` sent as a raw JSON string or JSON-like form field

#### Success response
```json
{
  "ok": true,
  "id": 123
}
```

#### Error response for bad JSON
```json
{
  "ok": false,
  "error": "Invalid JSON in payload"
}
```

---

## 3. How the service works end to end

### 3.1 Import job list flow
1. External client calls `GET /api/importjobs/list`
2. Request is authenticated with Sanctum Bearer token
3. Controller reads rows from `loadimport_jobs`
4. Rows are ordered by `jobname`
5. Each row is returned in this format:
    - `jobname`
    - `signature`
6. If the database value for `signature` is `NULL`, the API returns an empty array `[]`

### 3.2 Load archive flow
1. External client calls `POST /api/loads/push`
2. Request is authenticated with Sanctum Bearer token
3. Laravel validates input
4. Service builds date-based folder path:
    - `loadimports/YYYY-MM-DD`
5. Payload is stored on the configured Laravel `local` disk
6. Optional image is stored in the same date-based directory
7. Payload JSON is decoded and stored into the `payload_json` column
8. `jobname` is extracted from the JSON payload if present
9. A row is inserted into `loadimports`
10. API returns inserted row ID

---

## 4. Code files involved

### Controllers
- `app/Http/Controllers/ImportJobsController.php`
- `app/Http/Controllers/LoadsController.php`

### Models
- `app/Models/LoadImportJob.php`
- `app/Models/LoadImport.php`
- `app/Models/User.php`

### Console command
- `app/Console/Commands/IssueSanctumToken.php`

### Routes
- `routes/api.php`

### Migrations
- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/2026_02_04_222244_create_personal_access_tokens_table.php`
- `database/migrations/2026_02_05_000001_create_loadimport_jobs_table.php`
- `database/migrations/2026_02_05_000002_create_loadimports_table.php`

### Seeders
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/LoadImportJobsSeeder.php`

---

## 5. Required tables

The service needs these tables to work.

### Required for authentication
- `users`
- `personal_access_tokens`

### Required for business logic
- `loadimport_jobs`
- `loadimports`

### Optional framework tables
Depending on how the Laravel app is used, these may also exist:
- `cache`
- `jobs`

They are not the main business tables for this API.

---

## 6. Table details

### 6.1 `loadimport_jobs`
This table is the source of truth for the import job list.

#### Columns
- `id` bigint unsigned, primary key
- `jobname` varchar(255), required
- `signature` json, nullable
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

#### Purpose
Each row defines one job visible through `GET /api/importjobs/list`.

---

### 6.2 `loadimports`
This table archives all incoming payload submissions.

#### Current structure
Based on your current environment, the table includes:
- `id`
- `jobname`
- `payload_path`
- `payload_original`
- `payload_size`
- `image_path`
- `image_original`
- `image_size`
- `payload_json`
- `created_at`
- `updated_at`
- `is_inserted`
- `id_load`

#### Purpose of the extra current columns
- `is_inserted`: tracks whether the archived row has already been inserted into downstream operational tables or processed by later business flow
- `id_load`: references the resulting operational load ID when applicable

These two columns are part of the current real database state and should be preserved in the setup SQL when matching the environment exactly.

---

## 7. Storage behavior

### Disk used
The code uses Laravel storage disk:
- `local`

### File path logic
Files are stored in a folder shaped like:
```text
loadimports/YYYY-MM-DD/
```

### Example relative paths
```text
loadimports/2026-03-09/uuid__payload.json
loadimports/2026-03-09/uuid__image.jpg
```

### What is stored on disk
- raw payload file
- optional image file

### What is stored in DB
- relative storage path
- original filename
- size
- decoded JSON copy

---

## 8. Authentication and token flow

Authentication is done with Laravel Sanctum.

### Required pieces
- `User` model must use `HasApiTokens`
- `personal_access_tokens` table must exist
- API routes must be wrapped in `auth:sanctum`

### Token command
The project includes a console command:
- `php artisan app:issue-token`

### Example usage
```bash
php artisan app:issue-token --email=api@example.com --name=load-collector-prod --revoke-old
```

### What the command does
1. Reads CLI options
2. Looks up user by email
3. Creates the user if missing
4. Optionally deletes old tokens
5. Creates a new personal access token
6. Prints the plain text token once

### Important note
The printed token must be copied immediately. The full plain text token cannot be retrieved again later.

---

## 9. Environment and configuration

### Minimum `.env` values to verify
```env
APP_NAME="LoadImport API"
APP_ENV=local
APP_KEY=base64:CHANGE_ME
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=voldhaul
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=local
```

### Important checks
- database points to the correct schema
- `APP_KEY` exists
- storage disk root is understood on this environment
- local web server can write to storage path
- Bearer token is issued from the same database used by the app

---

## 10. Final route file logic

The active route design is:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/importjobs/list', [ImportJobsController::class, 'list']);
    Route::post('/loads/push', [LoadsController::class, 'push']);
});
```

This means both endpoints require a valid Sanctum token.

---

## 11. Migration-based setup

Use this path when setting up a clean environment from Laravel migrations.

### Step 1: verify migration files
Expected important migration files:
- `create_users_table`
- `create_personal_access_tokens_table`
- `create_loadimport_jobs_table`
- `create_loadimports_table`

### Step 2: run migrations
```bash
php artisan migrate
```

### Step 3: seed job list
```bash
php artisan db:seed --class=LoadImportJobsSeeder
```

### Step 4: create token
```bash
php artisan app:issue-token --email=api@example.com --name=load-collector-local --revoke-old
```

### Step 5: clear caches
```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### Step 6: start app
```bash
php artisan serve
```

---

## 12. Direct SQL setup

Use this path when you want to create the required tables directly in MySQL or phpMyAdmin.

### 12.1 Create `loadimport_jobs`
```sql
CREATE TABLE IF NOT EXISTS `loadimport_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jobname` VARCHAR(255) NOT NULL,
  `signature` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_loadimport_jobs_jobname` (`jobname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 12.2 Insert initial `loadimport_jobs` rows
```sql
INSERT INTO `loadimport_jobs` (`id`, `jobname`, `signature`, `created_at`, `updated_at`) VALUES
(3, 'Spectre-Crescent-SIMUL Washburn', JSON_OBJECT(), '2026-02-08 11:25:43', '2026-02-08 11:25:43'),
(7, '(OLYMPUS) Petro Hunt - WC West C', NULL, NULL, NULL),
(9, 'Jonah Studhorse Butte', NULL, NULL, NULL),
(10, 'Apache-Warwick-Kopecki', NULL, NULL, NULL),
(11, 'Frac 94 - Murphy - ERB-King PSA 1H / 2H / 3H / 4H', NULL, NULL, NULL);

ALTER TABLE `loadimport_jobs` AUTO_INCREMENT = 12;
```

### 12.3 Create `loadimports`
This version matches the current real database state, including `is_inserted` and `id_load`.

```sql
CREATE TABLE IF NOT EXISTS `loadimports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jobname` VARCHAR(255) NULL,
  `payload_path` VARCHAR(255) NOT NULL,
  `payload_original` VARCHAR(255) NULL,
  `payload_size` BIGINT UNSIGNED NULL,
  `image_path` VARCHAR(255) NULL,
  `image_original` VARCHAR(255) NULL,
  `image_size` BIGINT UNSIGNED NULL,
  `payload_json` LONGTEXT NULL COLLATE utf8mb4_bin,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `is_inserted` TINYINT(1) NOT NULL DEFAULT 0,
  `id_load` INT(10) UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_loadimports_jobname` (`jobname`),
  KEY `idx_loadimports_created_at` (`created_at`),
  KEY `idx_loadimports_is_inserted` (`is_inserted`),
  KEY `idx_loadimports_id_load` (`id_load`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 12.4 Create `users`
Use your Laravel migration if possible. If you must create manually:

```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 12.5 Create `personal_access_tokens`
```sql
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 13. Seeder-based setup

If the tables exist and you only want to load the known job rows:

```bash
php artisan db:seed --class=LoadImportJobsSeeder
```

The seeder uses `updateOrInsert`, so it can be run multiple times safely.

---

## 14. Example token creation flow

### Create token
```bash
php artisan app:issue-token --email=api@example.com --name=load-collector-local --revoke-old
```

### Expected output
```text
Using existing user: api@example.com (id=1)
Revoked old tokens: 1

NEW TOKEN (copy now):
1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Postman setup
- Method: `GET`
- URL: `http://127.0.0.1:8000/api/importjobs/list`
- Authorization type: Bearer Token
- Token: paste the printed token
- Header: `Accept: application/json`

---

## 15. Example requests

### 15.1 Test import job list
```bash
curl -X GET "http://127.0.0.1:8000/api/importjobs/list" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### 15.2 Test load push
```bash
curl -X POST "http://127.0.0.1:8000/api/loads/push" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "payload=@C:/path/to/order.json" \
  -F "bolimage=@C:/path/to/image.jpg"
```

### 15.3 Test raw JSON payload mode
```bash
curl -X POST "http://127.0.0.1:8000/api/loads/push" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F 'payload={"jobname":"Jonah Studhorse Butte","po":"12345"}'
```

---

## 16. Detailed behavior of `LoadsController`

### Payload handling
If `payload` is uploaded as a file:
- original file name is captured
- file size is captured
- file is stored under date-based folder
- file contents are read back from disk
- file contents are decoded to JSON

If `payload` is sent as raw text or array:
- value is normalized to raw JSON string
- JSON is decoded
- generated file name is created
- raw JSON string is saved to disk
- string length is saved as payload size

### JSON validation rule
If decoding fails and JSON is invalid:
- request is rejected with 422
- no row should be considered valid business input

### Jobname extraction
If payload JSON is an object and includes `jobname`, it is copied into the `jobname` column for easier browsing.

### Image handling
If `bolimage` is present:
- image original name is captured
- image size is captured
- image file is stored in the same date-based folder as the payload

### Final DB insert
One row is created in `loadimports` with:
- `jobname`
- payload metadata
- image metadata
- decoded `payload_json`

---

## 17. Troubleshooting

### 17.1 Namespace error in PHP file
Cause:
- file saved with UTF-8 BOM in Windows PowerShell

Fix:
- rewrite file as UTF-8 without BOM

### 17.2 500 error on route
Check:
```bash
php artisan route:list
Get-Content .\storage\logs\laravel.log -Tail 100
```

### 17.3 Token does not work
Check:
- request uses `Authorization: Bearer ...`
- token belongs to same app database
- `personal_access_tokens` contains the token hash row
- `User` model uses `HasApiTokens`

### 17.4 No files appear on disk
Check:
- writable storage path
- local disk root in `config/filesystems.php`
- Laravel process permissions

### 17.5 JSON payload rejected
Check:
- uploaded file contains valid JSON
- raw text mode uses proper JSON syntax

---

## 18. Suggested deployment order on a new environment

### Option A: Laravel way
1. configure `.env`
2. run `php artisan migrate`
3. run `php artisan db:seed --class=LoadImportJobsSeeder`
4. run token issue command
5. clear caches
6. test endpoints

### Option B: Direct SQL way
1. create required tables with SQL from this README
2. insert `loadimport_jobs` seed data
3. verify app `.env`
4. issue token with artisan command
5. clear caches
6. test endpoints

---

## 19. Final checklist

- `loadimport_jobs` exists
- `loadimports` exists
- `users` exists
- `personal_access_tokens` exists
- `LoadImportJob` model points to `loadimport_jobs`
- `ImportJobsController` reads from `loadimport_jobs`
- `routes/api.php` is protected with `auth:sanctum`
- token can be issued successfully
- `GET /api/importjobs/list` works
- `POST /api/loads/push` works
- files appear under the configured local storage path

---

## 20. Current-state summary

This service is a small authenticated Laravel ingestion API.

- `loadimport_jobs` defines which jobs the external process should work with
- `loadimports` archives incoming payloads and optional images
- Laravel Sanctum secures the API
- token issuance is handled with a custom artisan command
- setup can be done either through Laravel migrations/seeders or direct SQL creation

