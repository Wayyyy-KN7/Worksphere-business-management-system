<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('document_type')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('access_level', ['PRIVATE', 'DEPARTMENT', 'SELECTED_USERS', 'COMPANY_WIDE'])->default('PRIVATE');
            $table->enum('status', ['DRAFT', 'PENDING_REVIEW', 'APPROVED', 'REJECTED', 'PUBLISHED', 'ARCHIVED'])->default('DRAFT');
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};