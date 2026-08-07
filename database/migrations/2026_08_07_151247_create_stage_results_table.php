<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stage_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('time', 10, 3)->nullable();

            $table->unsignedInteger('alpha')->default(0);
            $table->unsignedInteger('charlie')->default(0);
            $table->unsignedInteger('delta')->default(0);
            $table->unsignedInteger('mike')->default(0);
            $table->unsignedInteger('no_shoot')->default(0);
            $table->unsignedInteger('procedural')->default(0);

            $table->integer('points')->nullable();

            $table->decimal('hit_factor', 12, 6)->nullable();
            $table->decimal('stage_percentage', 8, 3)->nullable();

            $table->unsignedInteger('overall_place')->nullable();
            $table->unsignedInteger('division_place')->nullable();
            $table->unsignedInteger('class_place')->nullable();

            $table->decimal('stage_points', 12, 4)->nullable();

            $table->string('source')->default('manual');

            $table->json('source_data')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_results');
    }
};
