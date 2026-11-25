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
    Schema::create('clinics', function (Blueprint $table) {

        $table->id();

        // ข้อมูลพื้นฐาน
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();

        // รูปภาพ
        $table->string('logo')->nullable();
        $table->string('cover_image')->nullable();

        // ติดต่อ
        $table->string('phone',30)->nullable();
        $table->string('email')->nullable();
        $table->string('website')->nullable();

        $table->string('facebook')->nullable();
        $table->string('instagram')->nullable();
        $table->string('line_id')->nullable();

        // ที่อยู่
        $table->string('country')->default('Thailand');
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

        // คะแนน
        $table->decimal('rating',2,1)->default(0);
        $table->integer('review_count')->default(0);

        // สถานะ
        $table->boolean('is_verified')->default(false);
        $table->boolean('is_active')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
