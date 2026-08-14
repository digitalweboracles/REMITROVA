<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'RemitRova backend',
        'status' => 'ok',
    ]);
});
