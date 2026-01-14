<?php

use App\Livewire\AboutUs;
use App\Livewire\CartPage;
use App\Livewire\HomePage;
use App\Livewire\BlogIndex;
use App\Livewire\BlogDetail;
use App\Livewire\CancelPage;
use Illuminate\Http\Request;
use App\Livewire\BatchKanban;
use App\Livewire\CheckoutPage;
use App\Livewire\MyOrdersPage;
use App\Livewire\ProductsPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\CategoriesPage;
use App\Livewire\Auth\RegisterPage;
use App\Livewire\MyOrderDetailPage;
use App\Livewire\ProductDetailPage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\ResetPasswordPage;
use App\Livewire\Shop\OrderConfirmation;
use App\Livewire\Auth\ForgotPasswordPage;
use App\Http\Controllers\BatchLossController;
use App\Http\Controllers\BatchPhaseController;
use App\Http\Controllers\BoldWebhookController;
use App\Http\Controllers\SitemapController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

Route::get('/', HomePage::class)->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/categories', CategoriesPage::class)->name('categories');
Route::get('/products', ProductsPage::class)->name('products');
Route::get('/products/{slug}', ProductDetailPage::class);
Route::get('/cart', CartPage::class)->name('cart');
Route::get('/sobre-nosotros', AboutUs::class)->name('about');
Route::get('/blog', BlogIndex::class)->name('blog.index');
Route::get('/blog/{slug}', BlogDetail::class)->name('blog.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', LoginPage::class)->name('login');
    Route::get('/register', RegisterPage::class);
    Route::get('/forgot', ForgotPasswordPage::class)->name('password.request');
    Route::get('/reset/{token}', ResetPasswordPage::class)->name('password.reset');
});

// 1. La vista que dice "Por favor verifica tu correo"
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// 2. El manejador del clic en el correo
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/checkout'); // A donde va tras verificar
})->middleware(['auth', 'signed'])->name('verification.verify');

// 3. Reenviar el correo si no le llegó
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Verification link sent!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/logout', function () {
        auth()->logout();
        return redirect()->intended('/');
    })->name('logout');
    Route::get('/checkout', CheckoutPage::class);
    Route::get('/my-orders', MyOrdersPage::class)->name('my-orders');
    Route::get('/gracias', OrderConfirmation::class)->name('order.thanks');
    Route::get('/my-orders/{order}', MyOrderDetailPage::class);
    Route::get('/cancel', cancelPage::class)->name('cancel');
    Route::get('/kanban', BatchKanban::class)->name('kanban');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/batches/{batch}/transition', [BatchPhaseController::class, 'transition'])
        ->name('batches.transition');
    Route::post('/batches/{batch}/losses', [BatchLossController::class, 'store'])
        ->name('batches.losses.store');
});

Route::post('/api/webhooks/bold', [BoldWebhookController::class, 'handle']);
