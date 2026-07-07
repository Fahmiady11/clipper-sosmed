<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('generated_clips', function (Blueprint $table) {
            $table->text('hook_text')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('generated_clips', function (Blueprint $table) {
            $table->string('hook_text', 100)->nullable()->change();
        });
    }
};
