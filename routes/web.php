<?php

declare(strict_types=1);

use Adminos\Modules\Feedmanager\Http\Controllers\B2cFeedController;
use Adminos\Modules\Feedmanager\Http\Middleware\ExportHashAuth;
use Illuminate\Support\Facades\Route;

// B2C marketplace feed — hash as path segment.
//   /export/feed/{slug}/{hash}
Route::middleware([ExportHashAuth::class])
    ->get('/export/feed/{slug}/{hash}', [B2cFeedController::class, 'show'])
    ->name('feedmanager.b2c.feed')
    ->where('slug', '[a-z0-9-]+')
    ->where('hash', '[A-Za-z0-9]+');
