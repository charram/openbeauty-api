<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->boolean('is_active')->default(false);

            $table->timestamps();

            $table->unique(['clinic_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_user');
    }
};