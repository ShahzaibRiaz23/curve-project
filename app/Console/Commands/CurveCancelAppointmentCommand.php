<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CurveAppointment;
use App\Services\AppointmentService;
use Illuminate\Console\Command;

class CurveCancelAppointmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'curve:cancel-appointment 
                            {id? : The Curve appointment ID or local ID to cancel}
                            {--reason=Patient needed to reschedule : Reason for cancellation}
                            {--add-to-list : Whether to add patient to the cancellation/wait list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel an appointment in Curve Dental and update local database';

    public function handle(AppointmentService $appointmentService): int
    {
        $idArg = $this->argument('id');

        $appointment = null;
        if ($idArg !== null) {
            $appointment = CurveAppointment::where('curve_id', (string) $idArg)->first()
                ?? CurveAppointment::find($idArg);
        } else {
            $appointment = CurveAppointment::latest()->first();
        }

        if (! $appointment) {
            $this->error('No appointment found to cancel.');
            return self::FAILURE;
        }

        $curveId = (string) $appointment->curve_id;
        $reason = (string) $this->option('reason');
        $addToList = (bool) $this->option('add-to-list');

        $this->info("Cancelling Curve Appointment [Curve ID: {$curveId}] (Local ID: {$appointment->id})...");
        $this->line("Reason: {$reason}");

        try {
            $cancelled = $appointmentService->cancelAppointment($curveId, [
                'reason' => $reason,
                'addToList' => $addToList,
            ]);

            $this->info("✓ Appointment {$curveId} successfully cancelled!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Local ID', $cancelled->id],
                    ['Curve ID', $cancelled->curve_id],
                    ['Patient ID', $cancelled->curve_patient_id],
                    ['Operatory ID', $cancelled->curve_operatory_id],
                    ['Provider ID', $cancelled->curve_provider_id],
                    ['Start Time', $cancelled->start_time?->format('Y-m-d H:i:s')],
                    ['Status', $cancelled->status],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to cancel appointment: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
