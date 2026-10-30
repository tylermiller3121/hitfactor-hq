<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_videos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stage_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('video_type')->default('pov');

            $table->string('disk')->default('local');

            $table->text('original_path');
            $table->text('proxy_path')->nullable();

            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->unsignedBigInteger('duration_ms')->nullable();

            $table->decimal('frame_rate', 10, 4)->nullable();
            $table->unsignedBigInteger('frame_count')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('codec')->nullable();

            $table->string('processing_status')->default('pending');
            $table->text('processing_error')->nullable();

            $table->timestamp('recorded_at')->nullable();

            $table->json('metadata')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['stage_id', 'video_type']);
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_videos');
    }
};
