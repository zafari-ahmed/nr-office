<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('nr:daily-reminders')->dailyAt('08:00');
