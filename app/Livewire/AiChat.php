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

        // Load Context from Session
        $context = session('ai_context', []);
        $this->city = $context['city'] ?? session('checkout_shipping')['city'] ?? '';
        $this->locality = $context['locality'] ?? session('checkout_shipping')['location'] ?? '';

        $greeting = '¡Hola! Soy el asistente virtual de Ignia Fungi. ¿En qué puedo ayudarte hoy? (Envíos, productos, reservar cosechas)';

        if (auth()->check()) {
            $name = explode(' ', auth()->user()->name)[0]; // First name only
            $greeting = "¡Hola {$name}! ¡Qué gusto tenerte una vez más por acá! ¿En qué puedo ayudarte?";
        }

        $this->messages = [
            [
                'role' => 'assistant',
                'content' => $greeting
            ]
        ];
    }

    public function updatedCity($value)
    {
        $this->updateSessionContext('city', $value);
    }

    public function updatedLocality($value)
    {
        $this->updateSessionContext('locality', $value);
    }

    protected function updateSessionContext($key, $value)
    {
        $context = session('ai_context', []);
        $context[$key] = $value;
        session(['ai_context' => $context]);
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

        // 6. Check for Session Regeneration (Reload Page)
        if (!empty($response['should_reload'])) {
            // Wait a moment for the user to read or just reload?
            // The user needs to see the success message. 
            // Turning it into a flash message might be better, but let's just reload to fix CSRF.
            // A simple redirect to self works.
            return redirect(request()->header('Referer'));
        }
    }

    public function selectProduct($productId, $productName)
    {
        // Simulate user typing
        $this->userInput = "He seleccionado " . $productName;
        $this->sendMessage(app(AiAgentService::class));
    }

    public function selectOption($option)
    {
        $this->userInput = (string) $option;
        $this->sendMessage(app(AiAgentService::class));
    }

    public function triggerAction($action, $payload = null)
    {
        if ($action === 'checkout' || $action === 'generate_order') {
            $this->userInput = "Generar orden";
        } elseif ($action === 'more_products' || $action === 'add_more') {
            $this->userInput = "Quiero agregar más productos";
        } else {
            $this->userInput = "Opción: " . $action;
        }
        $this->sendMessage(app(AiAgentService::class));
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
