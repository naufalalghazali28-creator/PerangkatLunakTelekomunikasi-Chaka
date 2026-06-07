<?php

use Illuminate\Support\Facades\Route;

// ─── PUBLIC ───────────────────────────────────────
Route::livewire('/', 'pages::auth.login')->name('login');

// ─── PROTECTED ────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // ADMIN
    Route::livewire('/admin',                   'pages::admin.idx')->name('admin');
    Route::livewire('/admin/client',            'pages::admin.client.idx')->name('admin.client');
    Route::livewire('/admin/gedung',            'pages::admin.gedung.idx')->name('admin.gedung');
    Route::livewire('/admin/operator',          'pages::admin.operator.idx')->name('admin.operator');
    Route::livewire('/admin/maintenance',       'pages::admin.maintenance.idx')->name('admin.maintenance');
    Route::livewire('/admin/viewer',            'pages::admin.viewer.idx')->name('admin.viewer');

    // CLIENT
    Route::livewire('/client',                  'pages::client.idx')->name('client');

    // MAINTENANCE
    Route::livewire('/maintenance',             'pages::maintenance.dashboard')->name('maintenance.work');
    Route::livewire('/maintenance/register-node','pages::maintenance.register-node')->name('maintenance.register');
    Route::livewire('/maintenance/nodes',       'pages::maintenance.nodes')->name('maintenance.nodes');
    Route::livewire('/maintenance/logs',        'pages::maintenance.logs')->name('maintenance.logs');
    Route::livewire('/maintenance/akun',        'pages::maintenance.akun')->name('maintenance.akun');

    // OPERATOR
    Route::livewire('/operator',                'pages::operator.dashboard')->name('operator.work');
    Route::livewire('/operator/control',        'pages::operator.control')->name('operator.control');
    Route::livewire('/operator/monitor',        'pages::operator.monitor')->name('operator.monitor');
    Route::livewire('/operator/akun',           'pages::operator.akun')->name('operator.akun');

    // VIEWER
    Route::livewire('/viewer',                  'pages::viewer.dashboard')->name('viewer.work');
    Route::livewire('/viewer/gedung',           'pages::viewer.gedung')->name('viewer.gedung');
    Route::livewire('/viewer/sensors',          'pages::viewer.sensors')->name('viewer.sensors');
    Route::livewire('/viewer/akun',             'pages::viewer.akun')->name('viewer.akun');
});