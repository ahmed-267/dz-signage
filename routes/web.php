<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/

Route::inertia('/', 'welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Application surfaces
|--------------------------------------------------------------------------
*/

require __DIR__.'/app.php';
require __DIR__.'/admin.php';
require __DIR__.'/player.php';
require __DIR__.'/settings.php';
