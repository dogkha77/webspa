<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('spa:gui-nhac-hen')->everyMinute()->withoutOverlapping();
