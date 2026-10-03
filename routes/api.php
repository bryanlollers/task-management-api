<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    Route::apiResource('projects', ProjectController::class)
        ->middlewareFor('show', 'can:view,project')
        ->middlewareFor('update', 'can:update,project')
        ->middlewareFor('destroy', 'can:delete,project');
    Route::apiResource('tasks', TaskController::class)
        ->middlewareFor('show', 'can:view,task')
        ->middlewareFor('update', 'can:update,task')
        ->middlewareFor('destroy', 'can:delete,task');
});
