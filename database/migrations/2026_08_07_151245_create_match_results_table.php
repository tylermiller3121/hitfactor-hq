<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('club_name')->nullable();
            $table->date('match_date')->nullable();

            $table->string('discipline')->default('USPSA');
            $table->string('division')->nullable();
            $table->string('classification')->nullable();

            $table->string('location')->nullable();

            $table->text('practiscore_url')->nullable();
            $table->string('practiscore_match_id')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'match_date']);
            $table->index('practiscore_match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
