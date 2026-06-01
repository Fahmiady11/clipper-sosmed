<?php

use App\Models\ClipJob;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::call(function () {
    ClipJob::where('expires_at', '<=', now())
        ->whereNotNull('expires_at')
        ->get()
        ->each(function ($clip) {
            if ($clip->file_path && Storage::exists($clip->file_path)) {
                Storage::delete($clip->file_path);
            }
            $clip->delete();
        });
})->everyMinute()->name('purge-expired-clips');
