import './bootstrap';
import 'preline';
import Swal from 'sweetalert2';

window.Swal = Swal;

document.addEventListener('livewire:navigated', () => { 
    window.HSStaticMEthods.autoInit();
})