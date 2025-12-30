<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;

#[Title('Register Page')]
class RegisterPage extends Component
{
    public $name;
    public $email;
    public $password;
    
    public function save()
    {
        $this->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|min:8|max:255'
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password)
        ]);

        auth()->login($user);
        return redirect()->intended('/'); 
    }
    
    public function render()
    {
        return view('livewire.auth.register-page');
    }
}
