<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('action', [
                'CREATED',
                'UPDATED',
                'DELETED',
                'VIEWED',
                'APPROVED',
                'REJECTED',
                'LOGGED_IN',
                'LOGGED_OUT',
                'OTHER'
            ])->default('OTHER');
            $table->string('table_name')->nullable();
            $table->bigInteger('record_id')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};