<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curve_providers', function (Blueprint $table) {
            $table->id();
            $table->string('curve_id')->unique();
            $table->string('kind')->nullable();      // Dentist, Hygienist, etc.
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->nullable();    // Active, etc.
            $table->json('clinic_ids')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curve_providers');
    }
};
