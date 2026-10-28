<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

require __DIR__.'/../app/Dashboard/routes.php';

Route::middleware('auth')->group(function () {
    require __DIR__.'/../app/Customers/routes.php';

    require __DIR__.'/../app/Routes/routes.php';

    require __DIR__.'/../app/Street/routes.php';

    require __DIR__.'/../app/Review/routes.php';

    require __DIR__.'/../app/CashDesk/routes.php';
});

require __DIR__.'/../app/Catalog/routes.php';
require __DIR__.'/../app/Accounts/routes.php';
