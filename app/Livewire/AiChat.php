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
        // We merge the full response to keep 'type' and 'payload'
        $this->messages[] = array_merge(['role' => 'assistant', 'content' => $response['message']], $response);

        // 6. Check for Closure/UI Events that need auto-interaction?
        // No, we wait for user to click.
    }

    public function selectProduct($productId, $productName)
    {
        // Simulate user typing
        $this->userInput = "He seleccionado " . $productName;
        $this->sendMessage(app(AiAgentService::class));
    }

    public function triggerAction($action)
    {
        if ($action === 'generate_order') {
            $this->userInput = "Generar orden";
        } elseif ($action === 'add_more') {
            $this->userInput = "Quiero agregar más productos";
        }
        $this->sendMessage(app(AiAgentService::class));
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
