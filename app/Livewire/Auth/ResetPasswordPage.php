<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;

#[Title('Reset Password')]
class ResetPasswordPage extends Component
{
    public $token;
    public $password;
    #[Url]
    public $email;
    public $password_confirmation;

    public function mount($token)
    {
        $this->token = $token;
    }

    public function save()
    {
        $this->validate([
            'password' => 'required|min:8|max:255',
            'password_confirmation' => 'required|same:password',
            'token' => 'required',
            'email' => 'required|email|max:255'
        ]);

        $status = Password::reset([
            'token' => $this->token,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ], function(User $user, string $password){
            $user->password = $password;
            $user->forceFill([
                'password' => Hash::make($password)
            ])->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET ? REDIRECT('/login') : session()->flash('error', 'Ups! algo salio mal. Intenta de nuevo.');
    }

    public function render()
    {
        return view('livewire.auth.reset-password-page');
    }
}
