<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('code_hash', 255);

            $table->string('purpose', 30)
                ->default('email_verification');

            $table->timestamp('expires_at');

            $table->unsignedTinyInteger('attempts')
                ->default(0);

            $table->timestamp('used_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'purpose',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};