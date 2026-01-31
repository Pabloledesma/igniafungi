<?php

namespace App\Livewire;

use App\Services\AiAgentService;
use Illuminate\Support\Str;
use Livewire\Component;
use Illuminate\Support\Facades\Request;

class AiChat extends Component
{
    public $messages = [];
    public $userInput = '';
    public $isOpen = false;
    public $sessionId;

    // Honeypot field
    public $website = '';

    // Context
    public $city = '';
    public $locality = '';

    public function mount()
    {
        $this->sessionId = session()->getId();
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => '¡Hola! Soy el asistente virtual de Ignia Fungi. ¿En qué puedo ayudarte hoy? (Envíos, productos, cultivo...)'
            ]
        ];
    }

    public function toggleChat()
    {
        $this->isOpen = !$this->isOpen;
    }

    public function sendMessage(AiAgentService $aiService)
    {
        // 1. Honeypot check
        if (!empty($this->website)) {
            // It's a bot
            return;
        }

        // 2. Validate input
        $this->validate([
            'userInput' => 'required|string|max:500'
        ]);

        $input = $this->userInput;
        $this->userInput = ''; // Reset immediately

        // Add user message to UI
        $this->messages[] = ['role' => 'user', 'content' => $input];

        // 3. Prepare Context
        $context = [
            'session_id' => $this->sessionId,
            'city' => $this->city,
            'locality' => $this->locality,
            // 'cart_total' => session('cart_total', 0) // Example
        ];

        // 4. Call Service
        $response = $aiService->processMessage($input, Request::ip(), $context);

        // 5. Handle Response
        $this->messages[] = ['role' => 'assistant', 'content' => $response['message']];

        // 6. Update local state if needed (e.g. if AI asked for City and user provided it, 
        // ideally the service extracts it and returns it, simplified here)
        // In a real app we'd parse the 'type' to see if we need to show a form field for City
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
