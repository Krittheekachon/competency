<?php

use App\Services\NotificationDigestService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(NotificationDigestService::class)->sendHourlyDigest())->hourly();
Schedule::call(fn () => app(NotificationDigestService::class)->sendDailyDigest())->dailyAt('09:00');
