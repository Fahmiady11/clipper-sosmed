<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('ALTER TABLE clip_jobs RENAME TO clip_jobs_old');

        DB::statement('
            CREATE TABLE clip_jobs (
                id VARCHAR NOT NULL,
                youtube_url VARCHAR NOT NULL,
                start_time INTEGER NOT NULL,
                end_time INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                file_path VARCHAR,
                error_msg TEXT,
                overlay_text VARCHAR,
                overlay_position VARCHAR DEFAULT \'bottom_right\',
                overlay_color VARCHAR DEFAULT \'white\',
                overlay_fontsize INTEGER DEFAULT 36,
                created_at DATETIME,
                updated_at DATETIME,
                expires_at DATETIME,
                auto_caption TINYINT(1) NOT NULL DEFAULT 0,
                caption_language VARCHAR,
                batch_id VARCHAR,
                layout_mode VARCHAR,
                gemini_hook TEXT,
                gemini_caption TEXT,
                gemini_hashtags TEXT,
                thumbnail_url VARCHAR,
                PRIMARY KEY (id)
            )
        ');

        DB::statement('INSERT INTO clip_jobs SELECT * FROM clip_jobs_old');
        DB::statement('DROP TABLE clip_jobs_old');

        DB::statement('CREATE INDEX clip_jobs_status_index ON clip_jobs (status)');
        DB::statement('CREATE INDEX clip_jobs_batch_id_index ON clip_jobs (batch_id)');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // irreversible — status constraint removed intentionally
    }
};
