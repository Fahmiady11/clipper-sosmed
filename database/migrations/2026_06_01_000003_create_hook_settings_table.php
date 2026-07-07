<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hook_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('clip_project_id');
            $table->foreign('clip_project_id')->references('id')->on('clip_projects')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('hook_text', 100)->nullable();
            $table->boolean('is_ai_generated')->default(false);
            $table->decimal('duration_seconds', 3, 1)->default(3.0);
            $table->enum('position', ['top', 'center', 'bottom'])->default('center');
            $table->string('text_color', 9)->default('#ffffff');
            $table->enum('background_style', ['none', 'semi', 'full'])->default('semi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hook_settings');
    }
};
