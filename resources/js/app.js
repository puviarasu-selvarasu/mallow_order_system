import './bootstrap';
import Alpine from 'alpinejs';
import orderDashboard from './order-dashboard';

window.Alpine = Alpine;

Alpine.data('orderDashboard', orderDashboard);

Alpine.start();