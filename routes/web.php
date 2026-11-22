<?php

use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('/jobs', [JobController::class, 'store'])->middleware('throttle:10,1')->name('jobs.store');
Route::get('/jobs/{id}/download', [JobController::class, 'download'])->name('jobs.download');
