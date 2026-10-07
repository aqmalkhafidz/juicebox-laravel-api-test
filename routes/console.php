<?php

use App\Jobs\RefreshWeather;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new RefreshWeather)->hourly()->withoutOverlapping();
