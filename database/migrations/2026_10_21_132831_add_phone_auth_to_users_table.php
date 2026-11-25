<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // เบอร์โทรศัพท์ เช่น +66812345678
            $table->string('phone', 20)
                ->nullable()
                ->unique();

            // เวลาที่ยืนยันเบอร์โทรศัพท์สำเร็จ
            $table->timestamp('phone_verified_at')
                ->nullable();

            // รองรับผู้ใช้ที่สมัครด้วยเบอร์โทรศัพท์
            $table->string('email')
                ->nullable()
                ->change();

            $table->string('password')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);

            $table->dropColumn([
                'phone',
                'phone_verified_at',
            ]);
        });

        // ไม่เปลี่ยน email/password กลับเป็น NOT NULL
        // เพื่อป้องกันปัญหากับบัญชีที่สมัครด้วยเบอร์โทรศัพท์
    }
};