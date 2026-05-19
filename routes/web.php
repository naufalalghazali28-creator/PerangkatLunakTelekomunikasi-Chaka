<?php

use Illuminate\Support\Facades\Route;

// Public
Route::livewire('/', 'pages::auth.login')->name('login');

// Dashboard Utama (Admin & Client)
Route::livewire('/admin', 'pages::admin.idx')->name('admin'); // Ini yang bikin error tadi
Route::livewire('/admin/client', 'pages::admin.client.idx')->name('admin.client');
Route::livewire('/admin/gedung', 'pages::admin.gedung.idx')->name('admin.gedung');
Route::livewire('/client', 'pages::client.idx')->name('client');

// Manajemen Staf untuk Admin
Route::livewire('/admin/operator', 'pages::admin.operator.idx')->name('admin.operator');
Route::livewire('/admin/maintenance', 'pages::admin.maintenance.idx')->name('admin.maintenance');
Route::livewire('/admin/viewer', 'pages::admin.viewer.idx')->name('admin.viewer');

// Halaman Kerja Staf (Bukan area admin)
Route::livewire('/maintenance', 'pages::maintenance.dashboard')->name('maintenance.work');
Route::livewire('/operator', 'pages::operator.dashboard')->name('operator.work');
Route::livewire('/viewer', 'pages::viewer.dashboard')->name('viewer.work');

// Area Client
Route::livewire('/', 'pages::auth.login')->name('login');

// ADMIN
Route::livewire('/admin', 'pages::admin.idx')->name('admin');
Route::livewire('/admin/client', 'pages::admin.client.idx')->name('admin.client');
Route::livewire('/admin/gedung', 'pages::admin.gedung.idx')->name('admin.gedung');
Route::livewire('/admin/operator', 'pages::admin.operator.idx')->name('admin.operator');
Route::livewire('/admin/maintenance', 'pages::admin.maintenance.idx')->name('admin.maintenance');
Route::livewire('/admin/viewer', 'pages::admin.viewer.idx')->name('admin.viewer');

// CLIENT (FIXED - SINGLE PAGE + TAB SYSTEM)
Route::middleware('auth')->group(function () {
    Route::livewire('/client', 'pages::client.idx')->name('client');
});

// STAFF WORK AREAS
Route::livewire('/maintenance', 'pages::maintenance.dashboard')->name('maintenance.work');
Route::livewire('/operator', 'pages::operator.dashboard')->name('operator.work');
Route::livewire('/viewer', 'pages::viewer.dashboard')->name('viewer.work');