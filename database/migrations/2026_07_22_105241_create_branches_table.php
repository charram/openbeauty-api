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
    Schema::create('branches', function (Blueprint $table) {

        $table->id();

        // ความสัมพันธ์กับคลินิก
        $table->foreignId('clinic_id')
            ->constrained('clinics')
            ->cascadeOnDelete();

        // ข้อมูลสาขา
        $table->string('name');
        $table->string('branch_code')->unique()->nullable();

        // ติดต่อ
        $table->string('phone',30)->nullable();
        $table->string('email')->nullable();

        // ที่อยู่
        $table->string('province')->nullable();
        $table->string('district')->nullable();
        $table->string('subdistrict')->nullable();
        $table->string('postcode',10)->nullable();
        $table->text('address')->nullable();

        // พิกัด
        $table->decimal('latitude',10,7)->nullable();
        $table->decimal('longitude',10,7)->nullable();

        // เวลาทำการ
        $table->time('open_time')->nullable();
        $table->time('close_time')->nullable();

        // สถานะ
        $table->boolean('is_head_office')->default(false);
        $table->boolean('is_active')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
