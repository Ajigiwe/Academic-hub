<?php

use App\Http\Controllers\Admin\{AdminController, BundleAdminController, MaterialAdminController, OrderAdminController, PaperAdminController, SettingsAdminController, StudentAdminController};
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FavouriteController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MaterialsController;
use App\Http\Controllers\MockGatewayController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentResultController;
use App\Http\Controllers\PaymentVerifyController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ViewerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/courses', [CatalogController::class, 'courses'])->name('courses');
Route::get('/bundles/{slug}', [CatalogController::class, 'showBundle'])->name('bundles.show');

// ── Free materials (public — guests included) ────────────────────────
Route::get('/materials', [MaterialsController::class, 'index'])->name('materials');
Route::get('/materials/{slug}/download', [MaterialsController::class, 'download'])
    ->name('materials.download');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ── Checkout & payments ──────────────────────────────────────────────
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('auth')
    ->name('checkout.store');

// Sandbox gateway pages/endpoints — only while the mock provider is
// available (see SandboxGatewayOnly). Paystack replaces them entirely
// with its own hosted checkout.
Route::middleware('sandbox')->group(function () {
    Route::get('/payment/mock-checkout', fn (Illuminate\Http\Request $request) => view('payment.mock-checkout', [
        'ref' => (string) $request->query('ref', ''),
        'amountPesewas' => (int) $request->query('amount', 0),
        'callback' => (string) $request->query('callback', ''),
    ]))->name('payment.mock-checkout');

    Route::post('/payment/mock-gateway', MockGatewayController::class)->name('payment.mock-gateway');
});

// Browser-return verification: the gateway sends the student back here;
// the server still re-verifies with the provider (never trust the client).
//
// The reference travels in the PATH because Paystack appends its own
// `?trxref=…&reference=…` to whatever callback URL it was given; a query
// string of ours could be mangled by that. `?ref=` is still accepted for
// links already in the wild.
Route::get('/payments/verify/{reference?}', PaymentVerifyController::class)
    ->middleware('auth')
    ->name('payments.verify');

// Provider webhook (server-to-server, CSRF-exempt — see bootstrap/app.php).
Route::post('/payments/webhook', PaymentWebhookController::class)->name('payments.webhook');

// Browser return landing page (port of Next.js /payment/result): the
// receipt, or an honest account of why there is none. Details are shown
// only to the student who owns the order (see PaymentResultController).
Route::get('/payment/result', PaymentResultController::class)->name('payment.result');

// ── Student area ─────────────────────────────────────────────────────
Route::get('/library', LibraryController::class)->middleware('auth')->name('library');
Route::get('/notifications', [NotificationController::class, 'index'])
    ->middleware('auth')->name('notifications.index');
Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
    ->middleware('auth')->name('notifications.read-all');
Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])
    ->middleware('auth')->name('notifications.open');
Route::get('/favourites', [FavouriteController::class, 'index'])
    ->middleware('auth')->name('favourites.index');
Route::post('/favourites/{resource}', [FavouriteController::class, 'toggle'])
    ->middleware('auth')->name('favourites.toggle');

// ── Secure viewer ────────────────────────────────────────────────────
Route::get('/view/{slug}', [ViewerController::class, 'show'])
    ->middleware('auth')->name('viewer.show');
// Re-runs the authorization chain and hands back a FRESH signed document
// URL — the reader's "open full screen" action, and the way back in when
// the browser cannot display a PDF inside the page (Chrome on Android).
Route::get('/view/{slug}/document', [ViewerController::class, 'open'])
    ->middleware('auth')->name('viewer.open');
// The same fresh link as JSON, for the in-page reader's silent renewal.
Route::get('/view/{slug}/document-url', [ViewerController::class, 'documentUrl'])
    ->middleware('auth')->name('viewer.document-url');
Route::get('/viewer-document/{resource}/{session}', [ViewerController::class, 'document'])
    ->middleware(['auth', 'signed'])
    ->name('viewer.document');

// ── Admin area (auth + admin middleware; 404s non-admins) ───────────
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');

    Route::get('/bundles', [BundleAdminController::class, 'index'])->name('bundles');
    Route::post('/bundles/{bundle}/status', [BundleAdminController::class, 'setStatus'])->name('bundles.status');
    Route::delete('/bundles/{bundle}', [BundleAdminController::class, 'destroy'])->name('bundles.destroy');

    Route::get('/papers', [PaperAdminController::class, 'index'])->name('papers');
    Route::post('/papers', [PaperAdminController::class, 'storeFile'])->name('papers.store');
    Route::get('/papers/{resource}', [PaperAdminController::class, 'show'])->name('papers.show');
    Route::post('/papers/{resource}/status', [PaperAdminController::class, 'setStatus'])->name('papers.status');
    Route::post('/papers/{resource}/update', [PaperAdminController::class, 'update'])->name('papers.update');
    Route::post('/papers/{resource}/replace', [PaperAdminController::class, 'replaceFile'])->name('papers.replace');
    Route::delete('/papers/{resource}', [PaperAdminController::class, 'destroy'])->name('papers.destroy');

    Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders');

    Route::get('/students', [StudentAdminController::class, 'index'])->name('students');
    Route::post('/students/{user}/status', [StudentAdminController::class, 'setStatus'])->name('students.status');

    // Free materials + programme visibility settings.
    Route::get('/materials', [MaterialAdminController::class, 'index'])->name('materials');
    Route::post('/materials', [MaterialAdminController::class, 'store'])->name('materials.store');
    Route::get('/settings', [SettingsAdminController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsAdminController::class, 'store'])->name('settings.store');
    // Declared before the {programme} routes so it is never swallowed.
    Route::post('/settings/pricing', [SettingsAdminController::class, 'updatePricing'])->name('settings.pricing');
    Route::post('/settings/{programme}/toggle', [SettingsAdminController::class, 'toggle'])->name('settings.toggle');
    Route::post('/settings/{programme}/rename', [SettingsAdminController::class, 'rename'])->name('settings.rename');
    Route::delete('/settings/{programme}', [SettingsAdminController::class, 'destroy'])->name('settings.destroy');
});
