<?php

use App\Modules\Engagement\Console\MatchSavedSearches;
use App\Modules\Listings\Console\ExpireListings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ExpireListings::class)->hourly()->withoutOverlapping();
Schedule::command(MatchSavedSearches::class)->hourly()->withoutOverlapping();
