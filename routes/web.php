<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest'])->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
});

Route::middleware([EnsureUserHasRole::class . ':admin,user'])->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard.dashboard')->name('dashboard');
    Route::livewire('/pasien', 'pages::dashboard.pasien')->name('pasien');
    Route::livewire('/capaian-individu', 'pages::dashboard.capaian-individu')->name('capaian-individu');
    Route::livewire('/pasien-emr', 'pages::dashboard.pasien-emr')->name('pasien-emr');
    Route::livewire('/profil-saya', 'pages::dashboard.profil-saya')->name('profil-saya');
});

Route::middleware([EnsureUserHasRole::class . ':admin'])->group(function () {
    // Route::livewire('/sinkron-data', 'pages::dashboard.sinkron-data')->name('sinkron-data');
    Route::livewire('/sinkronisasi', 'pages::dashboard.sinkronisasi')->name('sinkronisasi');
    Route::livewire('/sinkronisasi-sekolah', 'pages::dashboard.sinkronisasi-sekolah')->name('sinkronisasi-sekolah');
    Route::livewire('/sinkronisasi-emr', 'pages::dashboard.sinkronisasi-emr')->name('sinkronisasi-emr');
    Route::livewire('/pengaturan', 'pages::dashboard.pengaturan')->name('pengaturan');
});

Route::get('/', function () {
    return redirect()->route('dashboard');
});
