<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每小時自動同步所有追蹤專案的 specflow/changes;withoutOverlapping 防上次未跑完就重觸發
Schedule::command('projects:sync')->hourly()->withoutOverlapping();
