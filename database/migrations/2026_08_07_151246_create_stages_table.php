<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_result_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('stage_number');
            $table->string('name')->nullable();

            $table->boolean('is_classifier')->default(false);
            $table->string('classifier_code')->nullable();

            $table->unsignedInteger('minimum_rounds')->nullable();
            $table->unsignedInteger('maximum_points')->nullable();

            $table->string('stage_type')->nullable();

            $table->unsignedInteger('sequence')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['match_result_id', 'stage_number']);
            $table->index('classifier_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
