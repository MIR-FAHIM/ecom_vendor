<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->group(function () {
    Route::post('/create', [UserController::class, 'createUser']);

    Route::get('/list', [UserController::class, 'listUsers']);
    Route::get('/customers', [UserController::class, 'getCustomers']);
    Route::get('/vendors', [UserController::class, 'getVendors']);
    Route::get('/details/{id}', [UserController::class, 'getUserDetails']);

    Route::put('/update/{id}', [UserController::class, 'updateUser']);

    Route::patch('/ban/{id}', [UserController::class, 'banUser']);
    Route::patch('/unban/{id}', [UserController::class, 'unbanUser']);

    Route::delete('/delete/{id}', [UserController::class, 'deleteUser']);
});

Route::prefix('categories')->group(function () {
    Route::post('/create', [CategoryController::class, 'createCategory']);

    Route::get('/list', [CategoryController::class, 'listCategories']);
    Route::get('/details/{id}', [CategoryController::class, 'getCategoryDetails']);
    Route::get('/children/{id}', [CategoryController::class, 'getCategoryChildren']);

    Route::put('/update/{id}', [CategoryController::class, 'updateCategory']);

    Route::delete('/delete/{id}', [CategoryController::class, 'deleteCategory']);
});