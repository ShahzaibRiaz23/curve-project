<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curve_patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curve_id')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();           // cell phone number, fall back to home
            $table->string('status')->nullable();                   // e.g. "New Patient"
            $table->unsignedBigInteger('preferred_clinic_curve_id')->nullable()->index();
            $table->string('ghl_contact_id')->nullable()->index();  // link to the GHL contact
            $table->timestamp('curve_created_at')->nullable();
            $table->longText('raw')->nullable();                    // encrypted (see model cast), so not a JSON column
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curve_patients');
    }
};
