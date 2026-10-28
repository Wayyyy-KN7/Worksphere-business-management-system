<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('forecast_number')->unique();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->string('forecast_period');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('target_amount', 15, 2)->default(0);
            $table->decimal('forecasted_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_forecasts');
    }
};