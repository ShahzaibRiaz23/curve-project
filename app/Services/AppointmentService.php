<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;
use Illuminate\Support\Arr;
use App\Models\CurveAppointment;

class AppointmentService
{
    /**
     * Create an appointment in Curve Dental and save to local database.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAppointment(array $data): CurveAppointment
    {
        $payload = $this->formatAppointmentPayload($data);

        $response = CurveAuth::post('/appointment', $payload);

        if ($response->failed()) {
            $status = $response->status();
            $errorMessage = $this->extractErrorMessage($response->json(), $response->reason());

            throw new RuntimeException("Curve API appointment creation failed with HTTP {$status}: {$errorMessage}");
        }

        $responseData = $response->json();
        $appointmentData = $responseData['appointment'] ?? $responseData;

        if (! isset($appointmentData['id'])) {
            throw new RuntimeException('Curve API appointment creation succeeded but no appointment ID was returned.');
        }

        return $this->saveAppointmentToDatabase($appointmentData);
    }

    /**
     * Update an existing appointment in Curve Dental and local database.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAppointment(string|int $curveAppointmentId, array $data): CurveAppointment
    {
        $payload = $this->formatAppointmentPayload($data);

        $response = CurveAuth::put("/appointment/{$curveAppointmentId}", $payload);

        if ($response->failed()) {
            $status = $response->status();
            $errorMessage = $this->extractErrorMessage($response->json(), $response->reason());

            throw new RuntimeException("Curve API appointment update failed with HTTP {$status}: {$errorMessage}");
        }

        $responseData = $response->json();
        $appointmentData = $responseData['appointment'] ?? $responseData;

        return $this->saveAppointmentToDatabase($appointmentData);
    }

    /**
     * Cancel an appointment in Curve Dental and update local database.
     *
     * @param  array<string, mixed>  $data
     */
    public function cancelAppointment(string|int $curveAppointmentId, array $data = []): CurveAppointment
    {
        $payload = [
            'reason' => $data['reason'] ?? 'Patient needed to reschedule',
            'addToList' => (bool) ($data['addToList'] ?? false),
        ];

        $response = CurveAuth::post("/appointment/{$curveAppointmentId}/cancel", $payload);

        if ($response->failed()) {
            $status = $response->status();
            $errorJson = $response->json();
            $errorMessage = $this->extractErrorMessage($errorJson, $response->reason());

            // If Curve returns 409 because the appointment is already in state 'Cancel',
            // sync local status cleanly rather than failing.
            if ($status === 409 && (str_contains(strtolower($errorMessage), 'cancel') || str_contains(strtolower($errorMessage), 'already'))) {
                $fresh = $this->getAppointment($curveAppointmentId, forceFresh: true);
                if ($fresh) {
                    $fresh->update(['status' => 'Cancel']);
                    return $fresh;
                }

                $local = CurveAppointment::where('curve_id', (string) $curveAppointmentId)->first();
                if ($local) {
                    $local->update(['status' => 'Cancel']);
                    return $local;
                }
            }

            throw new RuntimeException("Curve API appointment cancellation failed with HTTP {$status}: {$errorMessage}");
        }

        $responseData = $response->json();
        $appointmentData = $responseData['appointments'][0]
            ?? $responseData['appointment']
            ?? $responseData;

        if (is_array($appointmentData) && isset($appointmentData['id'])) {
            return $this->saveAppointmentToDatabase($appointmentData);
        }

        $local = CurveAppointment::where('curve_id', (string) $curveAppointmentId)->first();
        if ($local) {
            $local->update(['status' => 'Cancel']);
            return $local->fresh();
        }

        throw new RuntimeException('Unable to locate cancelled appointment record in local database.');
    }

    /**
     * Retrieve an appointment by ID from API or local database.
     */
    public function getAppointment(string|int $curveAppointmentId, bool $forceFresh = false): ?CurveAppointment
    {
        if (! $forceFresh) {
            $existing = CurveAppointment::where('curve_id', (string) $curveAppointmentId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $response = CurveAuth::get("/appointment/{$curveAppointmentId}");

        if ($response->failed()) {
            return null;
        }

        $responseData = $response->json();
        $appointmentData = $responseData['appointment'] ?? $responseData;

        if (! isset($appointmentData['id'])) {
            return null;
        }

        return $this->saveAppointmentToDatabase($appointmentData);
    }

    /**
     * Save/upsert API appointment record to local database table.
     *
     * @param  array<string, mixed>  $appointmentData
     */
    public function saveAppointmentToDatabase(array $appointmentData): CurveAppointment
    {
        $curveId = (string) $appointmentData['id'];
        $startTime = $this->parseDate($appointmentData['startTime'] ?? null) ?? Carbon::now();

        $tagIds = $this->extractTagIds($appointmentData['appointmentTags'] ?? []);
        $rawFiltered = Arr::except($appointmentData, CurveAppointment::RAW_EXCLUDE);

        $attributes = [
            'curve_patient_id' => (int) ($appointmentData['patientId'] ?? 0),
            'curve_clinic_id' => isset($appointmentData['clinicId']) ? (int) $appointmentData['clinicId'] : null,
            'curve_operatory_id' => isset($appointmentData['operatoryId']) ? (int) $appointmentData['operatoryId'] : null,
            'curve_provider_id' => isset($appointmentData['providerId']) ? (string) $appointmentData['providerId'] : null,
            'status' => $appointmentData['appointmentStatus'] ?? 'Active',
            'confirmation' => $appointmentData['confirmation'] ?? null,
            'timezone' => $appointmentData['timezone'] ?? null,
            'length_minutes' => isset($appointmentData['length']) ? (int) $appointmentData['length'] : null,
            'visit_id' => isset($appointmentData['visitId']) ? (int) $appointmentData['visitId'] : null,
            'tag_ids' => $tagIds,
            'is_recurring' => (bool) ($appointmentData['isRecurring'] ?? false),
            'is_booked_online' => (bool) ($appointmentData['isBookedOnline'] ?? false),
            'curve_created_at' => $this->parseDate($appointmentData['createdAt'] ?? null),
            'curve_updated_at' => $this->parseDate($appointmentData['updatedAt'] ?? null),
            'raw' => $rawFiltered,
        ];

        $formattedStartTime = $startTime->format('Y-m-d H:i:s');

        // 1. Try finding existing appointment with same curve_id and start_time
        $existing = CurveAppointment::where('curve_id', $curveId)
            ->where('start_time', $formattedStartTime)
            ->first();

        // 2. If not found, check if a single appointment exists with this curve_id (rescheduled)
        if (! $existing && CurveAppointment::where('curve_id', $curveId)->count() === 1) {
            $existing = CurveAppointment::where('curve_id', $curveId)->first();
        }

        if ($existing) {
            $attributes['start_time'] = $formattedStartTime;
            $existing->update($attributes);

            return $existing->fresh();
        }

        return CurveAppointment::create(array_merge($attributes, [
            'curve_id' => $curveId,
            'start_time' => $formattedStartTime,
        ]));
    }

    /**
     * Format payload for Curve Dental appointment API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function formatAppointmentPayload(array $data): array
    {
        $startTime = $data['startTime'] ?? $data['start_time'] ?? null;
        if ($startTime instanceof Carbon) {
            $formattedStartTime = $startTime->format('Y-m-d H:i:s');
        } elseif (is_string($startTime)) {
            $formattedStartTime = Carbon::parse($startTime)->format('Y-m-d H:i:s');
        } else {
            $formattedStartTime = Carbon::now()->format('Y-m-d H:i:s');
        }

        return [
            'appointmentTags' => array_values(array_map('intval', (array) ($data['appointmentTags'] ?? $data['tag_ids'] ?? []))),
            'operatoryId' => (int) ($data['operatoryId'] ?? $data['operatory_id'] ?? 0),
            'patientId' => (int) ($data['patientId'] ?? $data['patient_id'] ?? 0),
            'providerId' => (int) ($data['providerId'] ?? $data['provider_id'] ?? 0),
            'startTime' => $formattedStartTime,
            'length' => (int) ($data['length'] ?? $data['length_minutes'] ?? 30),
        ];
    }

    /**
     * Extract integer tag IDs from API tags array (may be integers or URLs like ../appointment_tag/1).
     *
     * @param  array<mixed>  $tags
     * @return array<int>
     */
    protected function extractTagIds(array $tags): array
    {
        $ids = [];

        foreach ($tags as $tag) {
            if (is_numeric($tag)) {
                $ids[] = (int) $tag;
            } elseif (is_string($tag)) {
                $lastSegment = basename($tag);
                if (is_numeric($lastSegment)) {
                    $ids[] = (int) $lastSegment;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Safely parse date.
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
     * Extract error message safely from response.
     */
    protected function extractErrorMessage(mixed $data, string $defaultMessage): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (! is_array($data)) {
            return $defaultMessage ?: 'Unknown API error';
        }

        if (isset($data['error'])) {
            if (is_string($data['error'])) {
                return $data['error'];
            }
            if (is_array($data['error'])) {
                $msg = $data['error']['message'] ?? $data['error']['error'] ?? null;
                if (is_string($msg)) {
                    return $msg;
                }

                return json_encode($data['error']) ?: $defaultMessage;
            }
        }

        if (! empty($data['errors'])) {
            if (is_string($data['errors'])) {
                return $data['errors'];
            }
            if (is_array($data['errors'])) {
                $messages = [];
                foreach ($data['errors'] as $key => $err) {
                    $prefix = is_string($key) ? "{$key}: " : '';
                    if (is_string($err)) {
                        $messages[] = $prefix.$err;
                    } elseif (is_array($err)) {
                        $inner = $err['error'] ?? $err;
                        if (is_array($inner)) {
                            $msg = $inner['message'] ?? $inner['error'] ?? null;
                            if (is_string($msg)) {
                                $messages[] = $prefix.$msg;
                            } else {
                                $messages[] = $prefix.json_encode($inner);
                            }
                        } elseif (is_string($inner)) {
                            $messages[] = $prefix.$inner;
                        } else {
                            $messages[] = $prefix.json_encode($err);
                        }
                    } else {
                        $messages[] = $prefix.json_encode($err);
                    }
                }
                if (! empty($messages)) {
                    return implode('; ', $messages);
                }
            }
        }

        if (isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }

        return json_encode($data) ?: ($defaultMessage ?: 'Unknown API error');
    }
}
