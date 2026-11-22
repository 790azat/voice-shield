<?php

use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('/jobs', [JobController::class, 'store'])->middleware('throttle:10,1')->name('jobs.store');
Route::get('/jobs/{id}/download', [JobController::class, 'download'])->name('jobs.download');

// Launchers for the "run on my computer" mode.
Route::get('/download/{os}', function (string $os) {
    $file = ['windows' => 'start.bat', 'unix' => 'start.sh'][$os] ?? abort(404);

    return response()->download(base_path('worker/'.$file), 'voice-shield-'.$file, ['Content-Type' => 'application/octet-stream']);
})->name('launcher');
