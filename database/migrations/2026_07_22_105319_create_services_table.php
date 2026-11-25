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
    Schema::create('services', function (Blueprint $table) {

        $table->id();

        $table->foreignId('clinic_id')
            ->constrained('clinics')
            ->cascadeOnDelete();

        $table->foreignId('branch_id')
            ->constrained('branches')
            ->cascadeOnDelete();

        $table->foreignId('service_category_id')
            ->constrained('service_categories')
            ->cascadeOnDelete();

        $table->string('name');

        $table->text('description')->nullable();

        $table->decimal('price',10,2);

        $table->decimal('sale_price',10,2)->nullable();

        $table->integer('duration')->default(60);

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
        Schema::dropIfExists('services');
    }
};
