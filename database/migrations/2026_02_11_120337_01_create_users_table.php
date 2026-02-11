<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            // ✅ ENUM type - WITHOUT AFTER!
            $table->enum('type', ['customer', 'staff', 'admin', 'super_admin'])->default('customer');
            
            // ✅ STAFF FIELDS - URUTKAN MANUAL, HAPUS "AFTER"!
            $table->string('employee_id', 50)->nullable()->unique();
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->date('hire_date')->nullable();
            $table->enum('employment_status', ['permanent', 'contract', 'probation', 'intern', 'resigned'])->nullable();
            
            // ✅ EMERGENCY CONTACT
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            
            // ✅ STATUS
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // ✅ INDEXES
            $table->index('type');
            $table->index('employee_id');
            $table->index('is_active');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};