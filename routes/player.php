<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Screen player (/player)
|--------------------------------------------------------------------------
|
| Standalone full-screen surface — no Customer or Admin chrome.
|
*/

Route::inertia('/player', 'player/index')->name('player');
