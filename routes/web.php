<?php

use Illuminate\Support\Facades\Route;

// There is no public home: the dashboard, or the login for a guest.
Route::redirect('/', '/dashboard');

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
