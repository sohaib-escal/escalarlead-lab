<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AiStudioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CreativeController;
use App\Http\Controllers\CreativeGenerationController;
use App\Http\Controllers\CreativePromptController;
use App\Http\Controllers\CreativeTreeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PerformanceController;
use Illuminate\Support\Facades\Route;

// The tree is the product: it is what you land on.
Route::redirect('/', '/creative-tree');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Everyone who can log in is an admin — no role gates.
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/creative-tree', [CreativeTreeController::class, 'index'])->name('creative-tree');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/creatives', [CreativeController::class, 'index'])->name('creatives.index');
    Route::get('/creatives/new', [CreativeController::class, 'create'])->name('creatives.create');
    Route::post('/creatives', [CreativeController::class, 'store'])->name('creatives.store');
    Route::get('/creatives/{creative}', [CreativeController::class, 'show'])->name('creatives.show');
    Route::get('/creatives/{creative}/edit', [CreativeController::class, 'edit'])->name('creatives.edit');
    Route::put('/creatives/{creative}', [CreativeController::class, 'update'])->name('creatives.update');
    Route::delete('/creatives/{creative}', [CreativeController::class, 'destroy'])->name('creatives.destroy');
    Route::post('/creatives/{creative}/duplicate', [CreativeController::class, 'duplicate'])->name('creatives.duplicate');
    Route::put('/creatives/{creative}/status', [CreativeController::class, 'updateStatus'])->name('creatives.status');
    Route::put('/creatives/{creative}/rating', [CreativeController::class, 'updateRating'])->name('creatives.rating');
    Route::post('/creatives/{creative}/notes', [CreativeController::class, 'storeNote'])->name('creatives.notes');

    // Idea → prompt → validation
    Route::post('/creatives/{creative}/prompts', [CreativePromptController::class, 'store'])->name('prompts.store');
    Route::put('/prompts/{prompt}', [CreativePromptController::class, 'update'])->name('prompts.update');
    Route::post('/prompts/{prompt}/validate', [CreativePromptController::class, 'validatePrompt'])->name('prompts.validate');
    Route::delete('/prompts/{prompt}', [CreativePromptController::class, 'destroy'])->name('prompts.destroy');

    // Validated prompt → external generation → asset
    Route::post('/creatives/{creative}/generations', [CreativeGenerationController::class, 'store'])->name('generations.store');
    Route::post('/generations/{generation}/refresh', [CreativeGenerationController::class, 'refresh'])->name('generations.refresh');
    Route::post('/generations/{generation}/attach', [CreativeGenerationController::class, 'attach'])->name('generations.attach');
    Route::post('/generations/{generation}/use', [CreativeGenerationController::class, 'use'])->name('generations.use');
    Route::delete('/generations/{generation}', [CreativeGenerationController::class, 'destroy'])->name('generations.destroy');

    Route::get('/ai-studio', AiStudioController::class)->name('ai-studio');

    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::put('/campaigns/{campaign}/creatives', [CampaignController::class, 'syncCreatives'])->name('campaigns.creatives');

    Route::get('/landing-pages', [LandingPageController::class, 'index'])->name('landing-pages.index');
    Route::post('/landing-pages', [LandingPageController::class, 'store'])->name('landing-pages.store');
    Route::put('/landing-pages/{landingPage}', [LandingPageController::class, 'update'])->name('landing-pages.update');
    Route::delete('/landing-pages/{landingPage}', [LandingPageController::class, 'destroy'])->name('landing-pages.destroy');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance');
    Route::post('/creatives/{creative}/metrics', [PerformanceController::class, 'store'])->name('metrics.store');
    Route::put('/metrics/{metric}', [PerformanceController::class, 'update'])->name('metrics.update');
    Route::delete('/metrics/{metric}', [PerformanceController::class, 'destroy'])->name('metrics.destroy');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    Route::post('/admin/{resource}', [AdminController::class, 'store'])->name('admin.store');
    Route::put('/admin/{resource}/{id}', [AdminController::class, 'update'])->name('admin.update');
    Route::delete('/admin/{resource}/{id}', [AdminController::class, 'destroy'])->name('admin.destroy');
});
