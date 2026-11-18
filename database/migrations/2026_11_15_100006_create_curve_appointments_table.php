<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curve_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('curve_id', 50);                         // recurring appointments repeat the same id
            $table->unsignedBigInteger('curve_patient_id')->index();
            $table->unsignedBigInteger('curve_clinic_id')->nullable()->index();
            $table->unsignedBigInteger('curve_operatory_id')->nullable();
            $table->string('curve_provider_id', 50)->nullable();    // matches curve_providers.curve_id
            $table->string('status')->nullable()->index();          // appointmentStatus, e.g. "Active"
            $table->string('confirmation')->nullable();            // e.g. "Called"
            $table->dateTime('start_time')->index();                // clinic local time, see timezone column
            $table->string('timezone')->nullable();
            $table->unsignedSmallInteger('length_minutes')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->json('tag_ids')->nullable();                    // appointment tag ids
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_booked_online')->default(false);
            $table->dateTime('curve_created_at')->nullable();
            $table->dateTime('curve_updated_at')->nullable()->index(); // used to find recent changes when polling
            $table->string('last_pushed_status')->nullable();      // last status already sent to GHL / Google Ads
            $table->timestamp('ghl_synced_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['curve_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curve_appointments');
    }
};
