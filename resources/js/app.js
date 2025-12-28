import './bootstrap';
import 'preline';

document.addEventListener('livewire:navigated', () => { 
    window.HSStaticMEthods.autoInit();
})