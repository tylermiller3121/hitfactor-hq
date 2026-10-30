<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('matches', 'matches.index')
        ->name('matches.index');

    Route::livewire('matches/create', 'matches.create')
        ->name('matches.create');

    Route::livewire('matches/{matchResult}', 'matches.show')
        ->name('matches.show');

    Route::livewire('matches/{matchResult}/stages/create', 'stages.create')
        ->name('stages.create');

    Route::livewire('stages/{stage}', 'stages.show')
        ->name('stages.show');

    Route::livewire('stages/{stage}/videos/upload', 'videos.upload')
        ->name('videos.upload');
});

require __DIR__.'/settings.php';
