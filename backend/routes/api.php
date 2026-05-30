<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => [
    'success' => true,
    'data' => [
        'status' => 'ok',
    ],
]);
