import Alpine from 'alpinejs';
import { voiceCapture, orderHistory } from './voice-orders';

window.Alpine = Alpine;

Alpine.data('voiceCapture', voiceCapture);
Alpine.data('orderHistory', orderHistory);

Alpine.start();
