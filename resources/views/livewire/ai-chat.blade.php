<div class="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2">
    <!-- Chat Window -->
    <div x-data="{ 
            init() { 
                $watch('$wire.messages', () => this.scrollToEnd());
            }, 
            scrollToEnd() { 
                this.$nextTick(() => {
                    if (this.$refs.messages) {
                        this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight;
                    }
                });
            } 
         }" x-show="$wire.isOpen" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-10 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-10 scale-95"
        class="w-80 md:w-96 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden flex flex-col mb-2 sm:mb-0"
        style="height: 500px; max-height: 80vh; display: none;">

        <!-- Header -->
        <div class="bg-green-600 p-4 text-white flex justify-between items-center">
            <h3 class="font-bold">Ignia Agent</h3>
            <div class="flex items-center gap-2">
                <span class="text-xs bg-green-700 px-2 py-1 rounded-full">En línea</span>
                <button wire:click="toggleChat" class="text-white hover:text-gray-200 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Messages -->
        <div x-ref="messages" class="flex-1 p-4 overflow-y-auto space-y-4 bg-gray-50">
            @foreach($messages as $msg)
                    <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%] rounded-lg px-4 py-2 text-sm shadow-sm break-words
                                                                {{ $msg['role'] === 'user'
                ? 'bg-green-100 text-green-900 rounded-br-none'
                : 'bg-white text-gray-800 border border-gray-100 rounded-bl-none' }}">
                            {!! $msg['content'] !!}

                            {{-- UI Interactions --}}
                            @if(isset($msg['role']) && $msg['role'] === 'assistant' && isset($msg['type']))
                                {{-- 1. Catalog / List --}}
                                @if($msg['type'] === 'catalog' && !empty($msg['payload']))
                                    <div class="mt-3 flex flex-col gap-2">
                                        @foreach($msg['payload'] as $prod)
                                            @if(isset($prod['price']))
                                                <button wire:click="selectProduct({{ $prod['id'] }}, '{{ $prod['name'] }}')"
                                                    class="text-left w-full bg-white border border-green-200 hover:bg-green-50 p-2 rounded shadow-sm text-xs transition flex justify-between items-center group">
                                                    <span class="font-medium text-green-800 group-hover:text-green-900">🍄
                                                        {{ $prod['name'] }}</span>
                                                    <span
                                                        class="text-gray-600 font-mono text-[10px] bg-gray-100 px-1 rounded">${{ number_format($prod['price']) }}</span>
                                                </button>
                                            @elseif(isset($prod['index']))
                                                <button wire:click="selectOption({{ $prod['index'] }})"
                                                    class="text-left w-full bg-white border border-green-200 hover:bg-green-50 p-2 rounded shadow-sm text-xs transition flex justify-between items-center group">
                                                    <span class="font-medium text-green-800 group-hover:text-green-900">📂
                                                        {{ $prod['index'] }}. {{ $prod['name'] }}</span>
                                                    <span class="text-green-600">Ver productos &rarr;</span>
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                {{-- 2. Suggestions (Pivot) --}}
                                @if($msg['type'] === 'suggestion' && !empty($msg['payload']))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($msg['payload'] as $prod)
                                            <button wire:click="selectProduct({{ $prod['id'] }}, '{{ $prod['name'] }}')"
                                                class="bg-yellow-50 border border-yellow-200 text-yellow-800 hover:bg-yellow-100 px-3 py-1 rounded-full text-xs font-semibold shadow-sm transition flex items-center gap-1">
                                                <span>☀️</span> {{ $prod['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- 3. Dynamic Actions --}}
                                @if(isset($msg['actions']) && !empty($msg['actions']))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($msg['actions'] as $action)
                                            <button wire:click="triggerAction('{{ $action['type'] }}')"
                                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-3 rounded-lg text-xs shadow-md transition flex items-center gap-1">
                                                {{ $action['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif($msg['type'] === 'order_closure')
                                    {{-- Fallback for legacy messages if any --}}
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <button wire:click="triggerAction('add_more')"
                                            class="bg-gray-50 hover:bg-gray-100 text-gray-700 font-semibold py-2 px-2 rounded-lg text-xs border border-gray-200 transition flex justify-center items-center gap-1">
                                            ➕ Agregar más
                                        </button>
                                        <button wire:click="triggerAction('generate_order')"
                                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-2 rounded-lg text-xs shadow-md transition flex justify-center items-center gap-1">
                                            🛒 Generar Orden
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
            @endforeach

            <!-- Loading Indicator -->
            <div wire:loading wire:target="sendMessage" class="flex justify-start">
                <div class="bg-gray-100 rounded-lg px-4 py-2 text-xs text-gray-500 flex gap-1 items-center">
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-100"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-200"></span>
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="p-3 bg-white border-t border-gray-100">
            <form wire:submit.prevent="sendMessage">
                <!-- Honeypot -->
                <input type="text" wire:model="website" class="hidden" style="display:none" autocomplete="off">

                <div class="flex gap-2">
                    <input wire:model="userInput" type="text" placeholder="Escribe tu pregunta..."
                        class="flex-1 border border-gray-300 rounded-full px-4 py-2 text-sm focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500"
                        required>

                    <button type="submit"
                        class="bg-green-600 text-white rounded-full p-2 hover:bg-green-700 transition disabled:opacity-50"
                        wire:loading.attr="disabled">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                @if($locality === '' && $city === 'Bogotá')
                    <div class="mt-2 text-xs text-gray-500">
                        <input wire:model.blur="locality" type="text" placeholder="Ingresa tu localidad..."
                            class="w-full border-b border-gray-200 focus:outline-none text-xs py-1">
                    </div>
                @elseif($city === '')
                    <div class="mt-2 text-xs text-gray-500">
                        <input wire:model.blur="city" type="text" placeholder="Ingresa tu ciudad..."
                            class="w-full border-b border-gray-200 focus:outline-none text-xs py-1">
                    </div>
                @endif

            </form>
        </div>
    </div>

    <!-- Chat Button -->
    <button wire:click="toggleChat"
        class="bg-green-600 hover:bg-green-700 text-white rounded-full p-4 shadow-lg transition-transform transform hover:scale-105 flex items-center gap-2 z-50">
        @if($isOpen)
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <span class="font-semibold hidden md:inline">Asistente Virtual</span>
        @endif
    </button>
</div>