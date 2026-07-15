<?php

use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\LineLoginController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VenueController::class, 'index'])->name('venues.index');
Route::get('/create', [VenueController::class, 'create'])->name('venues.create');
Route::post('/venues', [VenueController::class, 'store'])->name('venues.store')->middleware('throttle:5,1');
Route::get('/venues/{venue}', [VenueController::class, 'show'])->name('venues.show');
Route::post('/venues/{venue}/reviews', [ReviewController::class, 'store'])->name('venues.reviews.store')->middleware('throttle:10,1');
Route::post('/venues/{venue}/like', [VenueController::class, 'like'])->name('venues.like')->middleware('throttle:30,1');
Route::post('/venues/{venue}/congestion', [VenueController::class, 'reportCongestion'])->name('venues.congestion.report')->middleware('throttle:30,1');
Route::post('/venues/{venue}/document-request', [DocumentRequestController::class, 'store'])
    ->name('venues.document-request.store')
    ->middleware('throttle:10,1');
Route::view('/thanks', 'venues.thanks')->name('venues.thanks');

Route::view('/about', 'about')->name('about');
Route::get('/sitemap.xml', [VenueController::class, 'sitemap'])->name('sitemap');

// LINE連携（お気に入り式場の見学会混雑状況通知／資料請求受付）
Route::get('/line/login', [LineLoginController::class, 'redirect'])->name('line.login');
Route::get('/line/callback', [LineLoginController::class, 'callback'])->name('line.callback');
Route::post('/venues/{venue}/favorite', [FavoriteController::class, 'toggle'])
    ->name('venues.favorite.toggle')
    ->middleware('throttle:10,1');
Route::post('/line/webhook', [LineWebhookController::class, 'handle'])->name('line.webhook');
