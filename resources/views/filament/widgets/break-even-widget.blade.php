<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold">Punto de Equilibrio ({{ now()->locale('es')->monthName }})</h2>
            @if(($percentage ?? 0) >= 100)
                <x-filament::badge color="success">
                    Meta Alcanzada
                </x-filament::badge>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Gastos Reales</p>
                <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">
                    ${{ number_format($expenses ?? 0, 2) }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Ingresos Reales</p>
                <p class="text-2xl font-bold text-success-600 dark:text-success-400">
                    ${{ number_format($income ?? 0, 2) }}</p>
            </div>
        </div>

        <div class="mt-6">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-600 dark:text-gray-300">Progreso</span>
                <span
                    class="font-bold {{ ($percentage ?? 0) >= 100 ? 'text-success-600' : 'text-primary-600' }}">{{ $percentage ?? 0 }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700 overflow-hidden">
                <div class="{{ ($percentage ?? 0) >= 100 ? 'bg-success-500' : 'bg-primary-500' }} h-4 rounded-full transition-all duration-500"
                    style="width: {{ min(100, $percentage ?? 0) }}%"></div>
            </div>
            @if(($missing ?? 0) > 0)
                <p class="text-sm text-gray-500 mt-2">Faltan <span
                        class="font-bold text-gray-700 dark:text-gray-200">${{ number_format($missing, 2) }}</span> para
                    cubrir los gastos de este mes.</p>
            @else
                <p class="text-sm text-success-600 mt-2 font-bold">¡Punto de equilibrio superado por
                    ${{ number_format(($income ?? 0) - ($expenses ?? 0), 2) }}!</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>