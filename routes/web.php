<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ContactSuggestionController as AdminContactSuggestionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\PasswordController as AdminPasswordController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LexiconController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/pflegeheime.html', [DirectoryController::class, 'index'])->name('directory.index');
Route::get('/brandenburg.html', [RegionController::class, 'show'])->name('region.show');
Route::get('/pflegelexikon.html', [LexiconController::class, 'index'])->name('lexicon.index');
Route::get('/pflegelexikon/{slug}.html', [LexiconController::class, 'show'])->name('lexicon.show');
Route::view('/ueber-uns.html', 'pages.about')->name('pages.about');
Route::view('/impressum.html', 'pages.imprint')->name('pages.imprint');
Route::view('/datenschutz.html', 'pages.privacy')->name('pages.privacy');
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::redirect(
    '/pflegeeinrichtungen/brandenburg/beeskow/medi-care-gmbh-haus-barbara-15485',
    '/pflegeeinrichtungen/brandenburg/beeskow/medi-care-gmbh-haus-barbara-15848',
    301,
);
Route::redirect(
    '/pflegeeinrichtungen/brandenburg/brandenburg-an-der-havel/vamed-klinik-hohenstuecken-gmbh-14472',
    '/pflegeeinrichtungen/brandenburg/brandenburg-an-der-havel/vitrea-klinik-brandenburg-an-der-havel-gmbh-14772',
    301,
);

Route::middleware('admin-session')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])->middleware('throttle:5,1')->name('admin.login');

    Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/einrichtungen', [AdminFacilityController::class, 'index'])->name('facilities.index');
        Route::post('/einrichtungen/beschreibungen-veroeffentlichen', [AdminFacilityController::class, 'publishDescriptionDrafts'])->name('facilities.description-drafts.publish');
        Route::get('/einrichtungen/{facility}/bearbeiten', [AdminFacilityController::class, 'edit'])->name('facilities.edit');
        Route::put('/einrichtungen/{facility}', [AdminFacilityController::class, 'update'])->name('facilities.update');
        Route::post('/einrichtungen/{facility}/beschreibung-entwurf', [AdminFacilityController::class, 'reviewDescriptionDraft'])->name('facilities.description-draft');
        Route::get('/kontaktpruefung', [AdminContactSuggestionController::class, 'index'])->name('suggestions.index');
        Route::post('/kontaktpruefung/importieren', [AdminContactSuggestionController::class, 'upload'])->name('suggestions.upload');
        Route::get('/kontaktpruefung/{suggestion}', [AdminContactSuggestionController::class, 'show'])->name('suggestions.show');
        Route::post('/kontaktpruefung/{suggestion}/annehmen', [AdminContactSuggestionController::class, 'accept'])->name('suggestions.accept');
        Route::post('/kontaktpruefung/{suggestion}/ablehnen', [AdminContactSuggestionController::class, 'reject'])->name('suggestions.reject');
        Route::get('/passwort', [AdminPasswordController::class, 'edit'])->name('password.edit');
        Route::put('/passwort', [AdminPasswordController::class, 'update'])->name('password.update');
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
    });
});

Route::prefix('pflegeeinrichtungen/brandenburg')->scopeBindings()->group(function (): void {
    Route::get('{city:slug}', [CityController::class, 'show'])->name('cities.show');
    Route::get('{city:slug}/{facility:slug}', [FacilityController::class, 'show'])->name('facilities.show');
});
