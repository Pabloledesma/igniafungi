<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Password;

#[Title('Forgot Password')]
class ForgotPasswordPage extends Component
{
    public $email;

    public function save()
    {
        $this->validate([
            'email' => 'required|email|max:255|exists:users,email'
        ]);

        $status = Password::sendResetLink(['email' => $this->email]);

        if($status === Password::RESET_LINK_SENT)
        {
            session()->flash('success', 'El enlace para restaurar la contraseña ha sido enviado a tu correo electrónico.');
            $this->email = '';
        }
    }
    
    public function render()
    {
        return view('livewire.auth.forgot-password-page');
    }
}
