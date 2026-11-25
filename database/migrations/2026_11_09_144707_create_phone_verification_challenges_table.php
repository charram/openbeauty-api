<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_verification_challenges', function (Blueprint $table) {

            $table->id();

            // บัญชีผู้ใช้ที่กำลังเพิ่มเบอร์โทรศัพท์
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // เบอร์โทรศัพท์ที่รอยืนยัน
            $table->string('phone', 20);

            // รหัส OTP ที่เก็บแบบ Hash
            $table->string('code_hash', 255);

            // วันและเวลาที่ OTP หมดอายุ
            $table->timestamp('expires_at');

            // จำนวนครั้งที่กรอกรหัสผิด
            $table->unsignedTinyInteger('attempts')
                ->default(0);

            // เวลาที่ OTP ถูกใช้แล้ว
            $table->timestamp('used_at')
                ->nullable();

            $table->timestamps();

            // Index สำหรับค้นหารายการ OTP ของผู้ใช้
            $table->index(
                ['user_id', 'created_at'],
                'phone_challenges_user_created_index'
            );

            // Index สำหรับค้นหา OTP ที่ยังไม่ถูกใช้
            $table->index(
                ['user_id', 'used_at'],
                'phone_challenges_user_used_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verification_challenges');
    }
};