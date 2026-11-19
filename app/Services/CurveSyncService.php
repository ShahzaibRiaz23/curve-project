<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;
use App\Models\CurveClinic;
use Illuminate\Support\Arr;
use App\Models\CurveProvider;
use App\Models\CurveOperatory;
use App\Models\CurveAppointmentTag;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class CurveSyncService
{
    /** Tracks whether the first meta.next value has been logged. */
    protected static bool $loggedFirstMetaNext = false;

    /**
     * Sync clinics from Curve API.
     */
    public function syncClinics(): array
    {
        $fetched = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            $records = $this->fetchAll('/clinic');
        } catch (\Throwable $e) {
            return [
                'entity' => 'clinics',
                'fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [['id' => null, 'message' => $e->getMessage()]],
            ];
        }

        $fetched = count($records);

        foreach ($records as $record) {
            try {
                if (! isset($record['id'])) {
                    throw new RuntimeException('Clinic record missing id.');
                }

                $keys = ['curve_id' => $record['id']];
                $values = [
                    'name' => (string) ($record['name'] ?? ''),
                    'email' => $record['email'] ?? null,
                    'phone' => data_get($record, 'officePhone.number'),
                    'time_zone' => $record['timeZone'] ?? null,
                    'is_active' => (bool) ($record['isActive'] ?? true),
                    'curve_updated_at' => $this->parseDate($record['updatedAt'] ?? null),
                    'raw' => Arr::except($record, [
                        'tin',
                        'npi',
                        'billingNumber',
                        'taxonomyNumber',
                        'payconexGroupName',
                    ]),
                ];

                $status = $this->upsert(CurveClinic::class, $keys, $values);
                ${$status}++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'id' => $record['id'] ?? null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'entity' => 'clinics',
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Sync operatories for all existing clinics in the DB.
     */
    public function syncOperatories(): array
    {
        $clinics = CurveClinic::all();

        if ($clinics->isEmpty()) {
            return [
                'entity' => 'operatories',
                'fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['No clinics found in database. Run clinics sync first.'],
            ];
        }

        $fetched = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($clinics as $clinic) {
            try {
                $records = $this->fetchAll("/clinic/{$clinic->curve_id}/operatory");
            } catch (\Throwable $e) {
                $errors[] = [
                    'id' => (string) $clinic->curve_id,
                    'message' => "Failed to fetch operatories for clinic {$clinic->curve_id}: {$e->getMessage()}",
                ];

                continue;
            }

            $fetched += count($records);

            foreach ($records as $record) {
                try {
                    if (! isset($record['id'])) {
                        throw new RuntimeException('Operatory record missing id.');
                    }

                    $curveClinicId = isset($record['clinicId']) && $record['clinicId'] !== null && $record['clinicId'] !== ''
                        ? (int) $record['clinicId']
                        : (int) $clinic->curve_id;

                    $keys = [
                        'curve_clinic_id' => $curveClinicId,
                        'curve_id' => $record['id'],
                    ];

                    $providerCurveId = isset($record['providerId']) && $record['providerId'] !== null && $record['providerId'] !== ''
                        ? (string) $record['providerId']
                        : null;

                    $values = [
                        'name' => (string) ($record['name'] ?? ''),
                        'short_name' => $record['shortName'] ?? null,
                        'provider_curve_id' => $providerCurveId,
                        'is_bookable' => (bool) ($record['isBookable'] ?? true),
                        'is_active' => (bool) ($record['isActive'] ?? true),
                        'curve_updated_at' => $this->parseDate($record['updatedAt'] ?? null),
                        'raw' => $record,
                    ];

                    $status = $this->upsert(CurveOperatory::class, $keys, $values);
                    ${$status}++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'id' => $record['id'] ?? null,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'entity' => 'operatories',
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Sync staff / providers from Curve API.
     */
    public function syncProviders(): array
    {
        $fetched = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            $records = $this->fetchAll('/staff');
        } catch (\Throwable $e) {
            return [
                'entity' => 'providers',
                'fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [['id' => null, 'message' => $e->getMessage()]],
            ];
        }

        $fetched = count($records);

        foreach ($records as $record) {
            try {
                if (! isset($record['id'])) {
                    throw new RuntimeException('Provider record missing id.');
                }

                $keys = ['curve_id' => (string) $record['id']];
                $values = [
                    'kind' => $record['kind'] ?? null,
                    'first_name' => $record['firstName'] ?? null,
                    'last_name' => $record['lastName'] ?? null,
                    'email' => $record['email'] ?? null,
                    'status' => $record['status'] ?? null,
                    'clinic_ids' => isset($record['clinics']) && is_array($record['clinics']) ? array_values($record['clinics']) : null,
                    'raw' => Arr::except($record, [
                        'birthDate',
                        'username',
                        'homePhone',
                        'workPhone',
                        'cellPhone',
                        'fax',
                        'isAccountLocked',
                        'hasPassword',
                    ]),
                ];

                $status = $this->upsert(CurveProvider::class, $keys, $values);
                
                ${$status}++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'id' => $record['id'] ?? null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'entity' => 'providers',
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Sync appointment tags from Curve API.
     */
    public function syncAppointmentTags(): array
    {
        $fetched = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            $records = $this->fetchAll('/appointment_tag');
        } catch (\Throwable $e) {
            return [
                'entity' => 'appointment_tags',
                'fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [['id' => null, 'message' => $e->getMessage()]],
            ];
        }

        $fetched = count($records);

        foreach ($records as $record) {
            try {
                if (! isset($record['id'])) {
                    throw new RuntimeException('Appointment tag record missing id.');
                }

                $keys = ['curve_id' => $record['id']];
                $values = [
                    'name' => (string) ($record['name'] ?? ''),
                    'color' => $record['color'] ?? null,
                    'is_active' => (bool) ($record['active'] ?? true),
                    'curve_updated_at' => $this->parseDate($record['updatedAt'] ?? null),
                    'raw' => $record,
                ];

                $status = $this->upsert(CurveAppointmentTag::class, $keys, $values);
                ${$status}++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'id' => $record['id'] ?? null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'entity' => 'appointment_tags',
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Sync patients (Handled on-demand via PatientService / GHL webhooks).
     */
    public function syncPatients(): array
    {
        return [
            'entity' => 'patients',
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
    }

    /**
     * Sync appointments (Handled on-demand via AppointmentService / webhooks).
     */
    public function syncAppointments(): array
    {
        return [
            'entity' => 'appointments',
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
    }

    /**
     * Run all syncs in order: clinics, operatories, providers, tags.
     */
    public function syncAll(): array
    {
        return [
            'clinics' => $this->syncClinics(),
            'operatories' => $this->syncOperatories(),
            'providers' => $this->syncProviders(),
            'appointment_tags' => $this->syncAppointmentTags(),
            'patients' => $this->syncPatients(),
            'appointments' => $this->syncAppointments(),
        ];
    }

    /**
     * Fetch all records across paginated endpoints.
     */
    protected function fetchAll(string $path): array
    {
        $records = [];
        $currentUrl = $path;
        $pages = 0;

        $apiBase = config('services.curve.api_base', 'https://api.curvehero.com/v1');
        $parsedScheme = parse_url($apiBase, PHP_URL_SCHEME) ?: 'https';
        $parsedHost = parse_url($apiBase, PHP_URL_HOST) ?: 'api.curvehero.com';
        $baseHostUrl = "{$parsedScheme}://{$parsedHost}";

        while ($currentUrl && $pages < 200) {
            $pages++;
            $response = CurveAuth::get($currentUrl);

            if ($response->failed()) {
                $status = $response->status();
                $data = $response->json();
                $errorMessage = $this->extractErrorMessage($data, $response->reason());

                throw new RuntimeException("Curve API request failed with HTTP {$status}: {$errorMessage}");
            }

            $data = $response->json();

            // Extract records: objects if present, otherwise top-level array
            if (is_array($data)) {
                if (isset($data['objects']) && is_array($data['objects'])) {
                    $pageRecords = $data['objects'];
                } else {
                    $pageRecords = $data;
                }

                foreach ($pageRecords as $record) {
                    if (is_array($record)) {
                        $records[] = $record;
                    }
                }
            }

            // Follow meta.next if present
            $next = null;
            if (is_array($data) && isset($data['meta']['next']) && is_string($data['meta']['next']) && trim($data['meta']['next']) !== '') {
                $next = trim($data['meta']['next']);
            }

            if ($next) {
                if (! static::$loggedFirstMetaNext) {
                    Log::info("Curve API first meta.next: {$next}");
                    static::$loggedFirstMetaNext = true;
                }

                if (str_starts_with($next, '/')) {
                    $currentUrl = $baseHostUrl.$next;
                } elseif (str_starts_with($next, 'http')) {
                    $currentUrl = $next;
                } else {
                    $currentUrl = $baseHostUrl.'/'.$next;
                }
            } else {
                break;
            }
        }

        return $records;
    }

    /**
     * Extract error message from API response body safely without leaking sensitive information.
     */
    protected function extractErrorMessage(mixed $data, string $defaultMessage): string
    {
        if (is_array($data)) {
            if (isset($data['error'])) {
                if (is_array($data['error'])) {
                    return (string) ($data['error']['message'] ?? $data['error']['error'] ?? json_encode($data['error']));
                }
                if (is_string($data['error'])) {
                    return $data['error'];
                }
            }

            if (! empty($data['errors'])) {
                if (is_array($data['errors'])) {
                    $messages = [];
                    foreach ($data['errors'] as $err) {
                        if (is_array($err)) {
                            $messages[] = (string) ($err['error'] ?? $err['message'] ?? json_encode($err));
                        } elseif (is_string($err)) {
                            $messages[] = $err;
                        }
                    }
                    if (! empty($messages)) {
                        return implode('; ', $messages);
                    }
                } elseif (is_string($data['errors'])) {
                    return $data['errors'];
                }
            }

            if (isset($data['message']) && is_string($data['message'])) {
                return $data['message'];
            }
        }

        return $defaultMessage ?: 'Unknown API error';
    }

    /**
     * Parse date safely. Returns null on invalid or empty value without throwing.
     */
    protected function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Run updateOrCreate and return 'created', 'updated', or 'skipped'.
     */
    protected function upsert(Model|string $model, array $keys, array $values): string
    {
        /** @var Model $instance */
        $instance = (is_string($model) ? new $model : $model)->newQuery()->firstOrNew($keys);

        if (! $instance->exists) {
            $instance->fill($values)->save();

            return 'created';
        }

        if (! $this->hasChanges($instance, $values)) {
            return 'skipped';
        }

        $instance->fill($values)->save();

        return 'updated';
    }

    /**
     * Determine whether any incoming values differ from the current model state.
     */
    protected function hasChanges(Model $instance, array $values): bool
    {
        foreach ($values as $key => $newValue) {
            $currentValue = $instance->getAttribute($key);

            if (in_array($key, ['raw', 'clinic_ids'], true)) {
                if ($currentValue === null && $newValue === null) {
                    continue;
                }
                if ($currentValue === null || $newValue === null) {
                    return true;
                }
                if ($this->normalizeForComparison($currentValue) !== $this->normalizeForComparison($newValue)) {
                    return true;
                }

                continue;
            }

            if ($instance->hasCast($key, ['datetime', 'date', 'timestamp', 'custom_datetime'])) {
                $currentFormatted = $currentValue ? Carbon::parse($currentValue)->format('Y-m-d H:i:s') : null;
                $newFormatted = $newValue ? Carbon::parse($newValue)->format('Y-m-d H:i:s') : null;
                if ($currentFormatted !== $newFormatted) {
                    return true;
                }

                continue;
            }

            if ($instance->hasCast($key, ['bool', 'boolean'])) {
                if ((bool) $currentValue !== (bool) $newValue) {
                    return true;
                }

                continue;
            }

            if ($currentValue === null && $newValue === null) {
                continue;
            }
            if ($currentValue === null || $newValue === null) {
                return true;
            }

            if ((string) $currentValue !== (string) $newValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize arrays for key-order-agnostic comparison.
     */
    protected function normalizeForComparison(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        if (Arr::isAssoc($data)) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->normalizeForComparison($value);
        }

        return $data;
    }
}