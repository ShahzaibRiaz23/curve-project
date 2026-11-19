<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CurveSyncService;

class CurveSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'curve:sync {entity=all : The entity to sync (all, clinics, operatories, providers, tags, patients, appointments, patients-appointments)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync reference data from Curve Dental API';

    /**
     * Execute the console command.
     */
    public function handle(CurveSyncService $service): int
    {
        $entity = strtolower((string) $this->argument('entity'));

        $validEntities = [
            'all',
            'clinics',
            'operatories',
            'providers',
            'tags',
            'appointment-tags',
            'appointment_tags',
            'patients',
            'appointments',
            'patients-appointments',
        ];

        if (! in_array($entity, $validEntities, true)) {
            $this->error("Invalid entity [{$entity}]. Valid options are: all, clinics, operatories, providers, tags, patients, appointments, patients-appointments.");

            return self::FAILURE;
        }

        /** @var array<int|string, array> $summaries */
        $summaries = match ($entity) {
            'all' => $service->syncAll(),
            'clinics' => [$service->syncClinics()],
            'operatories' => [$service->syncOperatories()],
            'providers' => [$service->syncProviders()],
            'tags', 'appointment-tags', 'appointment_tags' => [$service->syncAppointmentTags()],
            'patients' => [$service->syncPatients()],
            'appointments' => [$service->syncAppointments()],
            'patients-appointments' => [
                $service->syncPatients(),
                $service->syncAppointments(),
            ],
        };

        $hasErrors = false;

        foreach ($summaries as $summary) {
            $entityName = $summary['entity'] ?? 'unknown';
            $fetched = (int) ($summary['fetched'] ?? 0);
            $created = (int) ($summary['created'] ?? 0);
            $updated = (int) ($summary['updated'] ?? 0);
            $skipped = (int) ($summary['skipped'] ?? 0);
            $errors = (array) ($summary['errors'] ?? []);
            $errorCount = count($errors);

            if ($errorCount > 0) {
                $hasErrors = true;
            }

            $this->line(sprintf(
                '%s: fetched %d, created %d, updated %d, skipped %d, errors %d',
                $entityName,
                $fetched,
                $created,
                $updated,
                $skipped,
                $errorCount
            ));

            if ($errorCount > 0) {
                foreach ($errors as $error) {
                    $msg = is_array($error) ? ($error['message'] ?? json_encode($error)) : (string) $error;
                    $idStr = is_array($error) && ! empty($error['id']) ? "[ID: {$error['id']}] " : '';
                    $this->error("  - {$idStr}{$msg}");
                }
            }
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }
}
