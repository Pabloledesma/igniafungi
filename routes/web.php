<?php

use App\Livewire\CartPage;
use App\Livewire\HomePage;
use App\Livewire\CancelPage;
use App\Livewire\SuccessPage;
use App\Livewire\CheckoutPage;
use App\Livewire\MyOrdersPage;
use App\Livewire\ProductsPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\CategoriesPage;
use App\Livewire\Auth\RegisterPage;
use App\Livewire\MyOrderDetailPage;
use App\Livewire\ProductDetailPage;
use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\ResetPasswordPage;
use App\Livewire\Shop\OrderConfirmation;
use App\Livewire\Auth\ForgotPasswordPage;
use App\Http\Controllers\BoldWebhookController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Mail\MyTestEmail;
use Illuminate\Support\Facades\Mail;

Route::get('/test-envio-real', function () {
    Mail::raw('¡Prueba exitosa! Este correo sale desde el Transactional Stream de Ignia Fungi.', function ($message) {
        $message->to('pabloledes83@gmail.com')
                ->subject('Validación de Dominio Ignia Fungi');
    });
    return "Correo enviado a través de la infraestructura de Mailtrap.";
});

Route::get('/', HomePage::class)->name('home');
Route::get('/categories', CategoriesPage::class);
Route::get('/products', ProductsPage::class);
Route::get('/products/{slug}', ProductDetailPage::class);
Route::get('/cart', CartPage::class);

Route::middleware('guest')->group(function (){
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

Route::middleware(['auth', 'verified'])->group(function (){
    Route::get('/logout', function(){
        auth()->logout();
        return redirect()->intended('/');
    })->name('logout');
    Route::get('/checkout', CheckoutPage::class);
    Route::get('/my-orders', MyOrdersPage::class);
    Route::get('/gracias', OrderConfirmation::class)->name('order.thanks');
    Route::get('/my-orders/{order}', MyOrderDetailPage::class);
    Route::get('/success', SuccessPage::class)->name('success');
    Route::get('/cancel', cancelPage::class)->name('cancel');
});

Route::post('/api/webhooks/bold', [BoldWebhookController::class, 'handle']);
