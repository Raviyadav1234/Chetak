<?php

use App\Http\Controllers\Api\ProductController;
use App\Models\User;

Route::get('/mock-login', function () {
    $user = User::firstOrCreate(
        ['email' => 'test@example.com'],
        ['name' => 'Test User', 'password' => bcrypt('password')]
    );
    $token = $user->createToken('test-token')->plainTextToken;
    return response()->json(['token' => $token]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products/{id}/book', [ProductController::class, 'book'])->middleware('auth:sanctum');
});
