<?php

use Illuminate\Support\Facades\Route;

// Keep a lightweight health response for the Laravel smoke test; the product
// frontend is served independently and no HTML view is rendered here.
Route::get('/', function () {
    return response()->json(['service' => 'BankVision API']);
});
