<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curve_clinics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curve_id')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('time_zone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('ghl_location_id')->nullable(); // filled by hand, never overwritten by sync
            $table->timestamp('curve_updated_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curve_clinics');
    }
};
