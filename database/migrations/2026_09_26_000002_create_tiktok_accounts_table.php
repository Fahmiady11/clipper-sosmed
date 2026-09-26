<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('open_id');
            $table->string('display_name')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('access_token');  // encrypted cast
            $table->text('refresh_token'); // encrypted cast
            $table->timestamp('access_expires_at');
            $table->timestamp('refresh_expires_at')->nullable();
            $table->string('scope')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'open_id']);
        });

        Schema::table('generated_clips', function (Blueprint $table) {
            $table->foreignId('tiktok_account_id')->nullable()->after('music_track');
            $table->string('tiktok_publish_id')->nullable()->after('tiktok_account_id');
            $table->string('tiktok_status', 32)->nullable()->after('tiktok_publish_id');
            $table->text('tiktok_error')->nullable()->after('tiktok_status');
        });
    }

    public function down(): void
    {
        Schema::table('generated_clips', function (Blueprint $table) {
            $table->dropColumn(['tiktok_account_id', 'tiktok_publish_id', 'tiktok_status', 'tiktok_error']);
        });
        Schema::dropIfExists('tiktok_accounts');
    }
};
