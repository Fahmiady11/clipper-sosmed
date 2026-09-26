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

// Autopilot: check each enabled video source whose interval has elapsed
Schedule::call(function () {
    App\Models\VideoSource::where('enabled', true)->get()
        ->filter->isDue()
        ->each(function ($source) {
            $source->update(['last_run_at' => now()]); // don't re-dispatch while this run is queued
            App\Jobs\DiscoverVideosJob::dispatch($source->id);
        });
})->everyTenMinutes()->name('autopilot-discover')->withoutOverlapping();
