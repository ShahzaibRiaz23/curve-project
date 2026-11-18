<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curve_operatories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curve_id');
            $table->unsignedBigInteger('curve_clinic_id')->index();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('provider_curve_id')->nullable();
            $table->boolean('is_bookable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('curve_updated_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['curve_clinic_id', 'curve_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curve_operatories');
    }
};
