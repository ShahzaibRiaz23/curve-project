# Curve Dental Reference Data Sync: Implementation Guide

Instructions for GitHub Copilot. Build the sync layer described below in this Laravel project.
Follow the rules exactly. Do not add features that are not listed.

## 1. Goal

Pull four sets of reference data from the Curve Dental API and store them in local MySQL tables:

1. Clinics
2. Operatories (per clinic)
3. Providers (Curve calls them "staff")
4. Appointment tags

These are needed later to create and update appointments and to map Curve clinics to GHL locations.
Patients and appointments are NOT part of this task.

## 2. Rules

- Read-only. Only `GET` requests. Never POST, PUT or DELETE to Curve.
- All HTTP calls go through `App\Services\CurveAuth` (already written, do not rewrite it).
- Never log or return the access token, client secret, or full API responses.
- Syncs must be idempotent. Running a sync twice must not create duplicates.
- Never delete local rows because a record is missing from the API.
- Never overwrite `curve_clinics.ghl_location_id`. It is filled in manually.
- Keep the code simple and readable. Use typed methods and short comments.

## 3. Already in the project (do not recreate)

| File | Purpose |
|---|---|
| `app/Services/CurveAuth.php` | `CurveAuth::token()`, `forgetToken()`, `baseUrl()`, `http()`, `get($pathOrUrl, $query = [])` |
| `app/Models/CurveClinic.php` | table `curve_clinics` |
| `app/Models/CurveOperatory.php` | table `curve_operatories` |
| `app/Models/CurveProvider.php` | table `curve_providers` |
| `app/Models/CurveAppointmentTag.php` | table `curve_appointment_tags` |
| 4 migrations | the tables above |

`CurveAuth::get()` already refreshes the token once on HTTP 401 and retries on HTTP 429.
Use `CurveAuth::get('/clinic')` for relative paths. The base URL already contains the instance name.

Config (`config/services.php`) must contain:

```php
'curve' => [
    'client_id'     => env('CURVE_CLIENT_ID'),
    'client_secret' => env('CURVE_CLIENT_SECRET'),
    'instance'      => env('CURVE_INSTANCE'),
    'token_url'     => env('CURVE_TOKEN_URL', 'https://sso.curvehero.com/connect/token'),
    'api_base'      => env('CURVE_API_BASE', 'https://api.curvehero.com/v1'),
    'scope'         => env('CURVE_SCOPE'),
    'sync_key'      => env('CURVE_SYNC_KEY'),
],
```

## 4. Files to create

1. `app/Services/CurveSyncService.php`: all sync logic
2. `app/Http/Controllers/CurveSyncController.php`: thin controller that calls the service
3. Routes in `routes/web.php` (see section 8)
4. `app/Console/Commands/CurveSyncCommand.php`: artisan command `curve:sync {entity=all}` that calls the same service

## 5. API endpoints and response shapes

| Entity | Path (after `.../v1/{instance}`) | Response shape |
|---|---|---|
| Clinics | `/clinic` | Collection: `{ kind, meta, objects: [...] }` |
| Operatories | `/clinic/{clinicId}/operatory` | Collection: `{ kind, meta, objects: [...] }` |
| Providers | `/staff` | Plain array `[...]` expected |
| Appointment tags | `/appointment_tag` | Plain array `[...]` expected |

Collections can be paginated. `meta.next` holds the next page link and is null or missing on the last page.

## 6. Field mapping

Use `updateOrCreate` keyed on the unique column. Do not include unlisted columns in the update array.

### Clinics: `CurveClinic`, key: `curve_id`

| Column | API field |
|---|---|
| curve_id | `id` |
| name | `name` |
| email | `email` |
| phone | `officePhone.number` |
| time_zone | `timeZone` |
| is_active | `isActive` |
| curve_updated_at | `updatedAt` (parse safely) |
| raw | full record, with `tin`, `npi`, `billingNumber`, `taxonomyNumber`, `payconexGroupName` removed |

`ghl_location_id` is NOT in the update array.

### Operatories: `CurveOperatory`, key: `curve_clinic_id` + `curve_id`

| Column | API field |
|---|---|
| curve_id | `id` |
| curve_clinic_id | `clinicId` (fall back to the clinic being synced) |
| name | `name` |
| short_name | `shortName` |
| provider_curve_id | `providerId` cast to string, or null |
| is_bookable | `isBookable` |
| is_active | `isActive` |
| curve_updated_at | `updatedAt` (parse safely) |
| raw | full record |

### Providers: `CurveProvider`, key: `curve_id`

| Column | API field |
|---|---|
| curve_id | `id` cast to string |
| kind | `kind` |
| first_name | `firstName` |
| last_name | `lastName` |
| email | `email` |
| status | `status` |
| clinic_ids | `clinics` (array of clinic ids) |
| raw | full record, with `birthDate`, `username`, `homePhone`, `workPhone`, `cellPhone`, `fax`, `isAccountLocked`, `hasPassword` removed |

Store every staff member. Do not filter by `kind` yet. We will decide which kinds count as bookable providers after seeing real data.

### Appointment tags: `CurveAppointmentTag`, key: `curve_id`

| Column | API field |
|---|---|
| curve_id | `id` |
| name | `name` |
| color | `color` |
| is_active | `active` |
| curve_updated_at | `updatedAt` (parse safely) |
| raw | full record |

## 7. CurveSyncService behaviour

Create these public methods. Each returns the summary array shown below.

```php
syncClinics(): array
syncOperatories(): array      // loops over CurveClinic rows already in the DB
syncProviders(): array
syncAppointmentTags(): array
syncAll(): array              // runs in this order: clinics, operatories, providers, tags
```

Summary format (one per entity):

```json
{ "entity": "clinics", "fetched": 3, "created": 3, "updated": 0, "skipped": 0, "errors": [] }
```

Helper methods to include (protected):

- `fetchAll(string $path): array`
  - Calls `CurveAuth::get($path)`.
  - Throws a `RuntimeException` with the HTTP status and the API error code or message if the response failed. Never include the token.
  - Returns the records: use `objects` if present, otherwise the top-level array.
  - Follows `meta.next` until it is empty. If `meta.next` starts with `/`, prefix it with the scheme and host of `config('services.curve.api_base')` (for example `https://api.curvehero.com`) and pass the full URL to `CurveAuth::get()`. If it starts with `http`, use it as is.
  - Stops after 200 pages as a safety limit.
  - Logs the first `meta.next` value it sees once, so we can check its real format.
- `parseDate(?string $value): ?Carbon`: returns null on empty or invalid input and never throws. Dates arrive as both `2019-08-24T14:15:22Z` and `2019-08-14 13:23:00`.
- `upsert(Model|string $model, array $keys, array $values): string`: runs `updateOrCreate` and returns `'created'`, `'updated'` or `'skipped'` (skipped when nothing changed).

Error handling:

- Wrap each record in try/catch. A bad record is added to `errors` (record id and message only) and the loop continues.
- `syncOperatories()` must continue with the next clinic if one clinic fails.
- If `syncOperatories()` finds no clinics in the DB, return a summary with an error message telling the user to sync clinics first.

## 8. Controller and routes

Controller methods: `clinics`, `operatories`, `providers`, `appointmentTags`, `all`. Each calls the matching service method and returns `response()->json($summary)`.

Routes (`routes/web.php`):

```php
Route::prefix('curve/sync')->middleware('throttle:10,1')->group(function () {
    Route::post('all', [CurveSyncController::class, 'all']);
    Route::post('clinics', [CurveSyncController::class, 'clinics']);
    Route::post('operatories', [CurveSyncController::class, 'operatories']);
    Route::post('providers', [CurveSyncController::class, 'providers']);
    Route::post('appointment-tags', [CurveSyncController::class, 'appointmentTags']);
});
```

Protection: reject any request unless the `X-Sync-Key` header or the `key` query parameter matches `config('services.curve.sync_key')`, compared with `hash_equals`. Return 403 otherwise. Put this check in a small middleware class `VerifyCurveSyncKey`.
Exclude these routes from CSRF verification, since they are called with a key and not from a browser form.

## 9. Artisan command

`php artisan curve:sync {entity=all}` accepts `all`, `clinics`, `operatories`, `providers`, `tags`. It prints one line per summary (entity, fetched, created, updated, errors count). It returns a non-zero exit code if any summary has errors.

## 10. Manual test checklist

1. Add the `.env` values, run `php artisan config:clear` and `php artisan cache:clear`, then `php artisan migrate`.
2. In tinker, run `App\Services\CurveAuth::token()` and confirm a token string is returned.
3. Run `php artisan curve:sync clinics` and confirm 3 clinic rows (Pembroke Pines, Boynton Beach, Royal Palm Beach).
4. Run `php artisan curve:sync operatories` and confirm rows exist for every clinic.
5. Run `php artisan curve:sync providers` and `php artisan curve:sync tags`.
6. Run `php artisan curve:sync all` again and confirm `created` is 0 everywhere (idempotent).
7. Manually set `ghl_location_id` on one clinic, run the sync again, and confirm the value is kept.
8. Check the `raw` column of a provider and confirm `birthDate` and phone numbers are not stored.

## 11. Troubleshooting notes

- HTTP 403 `insufficient_permissions` on one endpoint: the API credentials lack the scope or role for it. Clear the cached token with `CurveAuth::forgetToken()` after changing `CURVE_SCOPE`. Do not change code.
- HTTP 400: read the `errors` or `error.message` field in the response. Some endpoints use `errors[].error` and others use a single `error` object. Handle both shapes when extracting the message.
- HTTP 429: already handled with backoff in `CurveAuth::get()`.

## 12. Out of scope

Patients, appointments, treatment plans, GHL calls, Google Ads, queues and scheduling. Do not add them.
