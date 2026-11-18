<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curve_appointment_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curve_id')->unique();
            $table->string('name');
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('curve_updated_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curve_appointment_tags');
    }
};
