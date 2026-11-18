<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;
use Illuminate\Support\Arr;
use App\Models\CurvePatient;

class PatientService
{
    /**
     * Create a new patient in Curve Dental and persist to local database.
     *
     * @param  array<string, mixed>  $data  Patient payload fields
     * @param  string|null  $ghlContactId  Optional GHL Contact ID to link
     */
    public function createPatient(array $data, ?string $ghlContactId = null): CurvePatient
    {
        $payload = $this->formatCreatePayload($data);

        $response = CurveAuth::post('/patient', $payload);

        if ($response->failed()) {
            $status = $response->status();
            $errorMessage = $this->extractErrorMessage($response->json(), $response->reason());

            throw new RuntimeException("Curve API patient creation failed with HTTP {$status}: {$errorMessage}");
        }

        $responseData = $response->json();
        $patientData = $responseData['patient'] ?? $responseData;

        if (! isset($patientData['id'])) {
            throw new RuntimeException('Curve API patient creation succeeded but no patient ID was returned.');
        }

        return $this->savePatientToDatabase($patientData, $ghlContactId);
    }

    /**
     * Retrieve a patient by Curve ID (local DB first, or fetched from API if needed).
     */
    public function getPatient(int|string $curveId, bool $forceFresh = false): ?CurvePatient
    {
        if (! $forceFresh) {
            $existing = CurvePatient::where('curve_id', $curveId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $response = CurveAuth::get("/patient/{$curveId}");

        if ($response->failed()) {
            return null;
        }

        $responseData = $response->json();
        $patientData = $responseData['patient'] ?? $responseData;

        if (! isset($patientData['id'])) {
            return null;
        }

        return $this->savePatientToDatabase($patientData);
    }

    /**
     * Find existing patient by GHL contact ID or email/phone, or create a new one.
     *
     * @param  array<string, mixed>  $ghlContactData
     */
    public function findOrCreateFromGhl(array $ghlContactData, int $preferredClinicId, int $defaultDentistId): CurvePatient
    {
        $ghlContactId = $ghlContactData['id'] ?? null;
        $email = $ghlContactData['email'] ?? null;
        $phone = $ghlContactData['phone'] ?? null;

        if ($ghlContactId) {
            $patient = CurvePatient::where('ghl_contact_id', $ghlContactId)->first();
            if ($patient) {
                return $patient;
            }
        }

        if ($email) {
            $patient = CurvePatient::where('email', $email)->first();
            if ($patient) {
                if ($ghlContactId && ! $patient->ghl_contact_id) {
                    $patient->ghl_contact_id = $ghlContactId;
                    $patient->save();
                }

                return $patient;
            }
        }

        if ($phone) {
            $patient = CurvePatient::where('phone', $phone)->first();
            if ($patient) {
                if ($ghlContactId && ! $patient->ghl_contact_id) {
                    $patient->ghl_contact_id = $ghlContactId;
                    $patient->save();
                }

                return $patient;
            }
        }

        // Format and create in Curve
        $payload = [
            'firstName' => $ghlContactData['firstName'] ?? $ghlContactData['first_name'] ?? 'Guest',
            'lastName' => $ghlContactData['lastName'] ?? $ghlContactData['last_name'] ?? 'Patient',
            'email' => $email,
            'cellPhone' => $phone ? ['number' => $phone, 'extension' => null] : null,
            'preferredClinicId' => $preferredClinicId,
            'defaultDentistId' => $defaultDentistId,
            'preferredContactMethod' => 'Cell phone',
        ];

        return $this->createPatient($payload, $ghlContactId);
    }

    /**
     * Save/upsert API patient record to local database table.
     *
     * @param  array<string, mixed>  $patientData
     */
    public function savePatientToDatabase(array $patientData, ?string $ghlContactId = null): CurvePatient
    {
        $curveId = (int) $patientData['id'];

        $phone = data_get($patientData, 'cellPhone.number')
            ?: data_get($patientData, 'homePhone.number')
            ?: data_get($patientData, 'workPhone.number');

        $rawFiltered = Arr::except($patientData, CurvePatient::RAW_EXCLUDE);

        $attributes = [
            'first_name' => $patientData['firstName'] ?? null,
            'last_name' => $patientData['lastName'] ?? null,
            'email' => $patientData['email'] ?? null,
            'phone' => $phone,
            'status' => $patientData['status'] ?? 'New Patient',
            'preferred_clinic_curve_id' => isset($patientData['preferredClinicId']) ? (int) $patientData['preferredClinicId'] : null,
            'curve_created_at' => $this->parseDate($patientData['createdAt'] ?? null),
            'raw' => $rawFiltered,
        ];

        if ($ghlContactId) {
            $attributes['ghl_contact_id'] = $ghlContactId;
        }

        return CurvePatient::updateOrCreate(
            ['curve_id' => $curveId],
            $attributes
        );
    }

    /**
     * Format payload for Curve Dental patient creation API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function formatCreatePayload(array $data): array
    {
        $payload = [
            'firstName' => (string) ($data['firstName'] ?? $data['first_name'] ?? ''),
            'lastName' => (string) ($data['lastName'] ?? $data['last_name'] ?? ''),
            'middleName' => $data['middleName'] ?? $data['middle_name'] ?? null,
            'preferredName' => $data['preferredName'] ?? $data['preferred_name'] ?? null,
            'preferredLanguage' => $data['preferredLanguage'] ?? $data['preferred_language'] ?? 'English',
            'dob' => $data['dob'] ?? $data['dateOfBirth'] ?? null,
            'email' => $data['email'] ?? null,
            'gender' => $data['gender'] ?? null,
            'homePhone' => $this->formatPhone($data['homePhone'] ?? $data['home_phone'] ?? null),
            'cellPhone' => $this->formatPhone($data['cellPhone'] ?? $data['cell_phone'] ?? null),
            'workPhone' => $this->formatPhone($data['workPhone'] ?? $data['work_phone'] ?? null),
            'preferredClinicId' => (int) ($data['preferredClinicId'] ?? $data['clinic_id'] ?? 0),
            'defaultDentistId' => (int) ($data['defaultDentistId'] ?? $data['dentist_id'] ?? 0),
            'householdHeadId' => isset($data['householdHeadId']) ? (int) $data['householdHeadId'] : null,
            'responsiblePartyId' => isset($data['responsiblePartyId']) ? (int) $data['responsiblePartyId'] : null,
            'preferredContactMethod' => $data['preferredContactMethod'] ?? $data['preferred_contact_method'] ?? 'Cell phone',
        ];

        if (! empty($data['address']) && is_array($data['address'])) {
            $country = (string) ($data['address']['country'] ?? 'US');
            $address = [
                'line1' => (string) ($data['address']['line1'] ?? ''),
                'line2' => (string) ($data['address']['line2'] ?? ''),
                'city' => (string) ($data['address']['city'] ?? ''),
                'country' => $country,
                'postal' => (string) ($data['address']['postal'] ?? $data['address']['zip'] ?? ''),
            ];

            $subdiv = $data['address']['countrySubdivision'] ?? $data['address']['state'] ?? $data['address']['province'] ?? null;
            if ($subdiv) {
                if (strtoupper($country) === 'US') {
                    $address['countrySubdivision'] = str_starts_with((string) $subdiv, 'US-') ? (string) $subdiv : 'US-'.ltrim((string) $subdiv, '-');
                } else {
                    $address['province'] = (string) $subdiv;
                }
            }

            $payload['address'] = $address;
        }

        return array_filter($payload, fn ($val) => $val !== null);
    }

    /**
     * Format phone object { number, extension }.
     */
    protected function formatPhone(mixed $phone): ?array
    {
        if (empty($phone)) {
            return null;
        }

        if (is_string($phone)) {
            return [
                'number' => $phone,
                'extension' => null,
            ];
        }

        if (is_array($phone)) {
            return [
                'number' => (string) ($phone['number'] ?? ''),
                'extension' => isset($phone['extension']) ? (string) $phone['extension'] : null,
            ];
        }

        return null;
    }

    /**
     * Safely parse a date string.
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
