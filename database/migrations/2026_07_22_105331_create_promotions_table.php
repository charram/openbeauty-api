<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('promotions', function (Blueprint $table) {

        $table->id();

        $table->foreignId('clinic_id')
            ->constrained('clinics')
            ->cascadeOnDelete();

        $table->foreignId('branch_id')
            ->nullable()
            ->constrained('branches')
            ->nullOnDelete();

        $table->foreignId('service_id')
            ->nullable()
            ->constrained('services')
            ->nullOnDelete();

        $table->string('title');

        $table->text('description')->nullable();

        $table->decimal('original_price',10,2)->nullable();

        $table->decimal('discount_price',10,2);

        $table->date('start_date');

        $table->date('end_date');

        $table->string('cover_image')->nullable();

        $table->boolean('is_featured')->default(false);

        $table->boolean('is_active')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
