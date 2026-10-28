<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', [
                'LEAVE_REQUEST',
                'LEAVE_APPROVED',
                'LEAVE_REJECTED',
                'DOCUMENT_SHARED',
                'DOCUMENT_APPROVED',
                'DOCUMENT_REJECTED',
                'FOLLOW_UP_DUE',
                'REMINDER_DUE',
                'GENERAL'
            ])->default('GENERAL');
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('related_type')->nullable();
            $table->bigInteger('related_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};