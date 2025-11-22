import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './styles/login.css';
import './styles/dashboard.css';

// Import login JavaScript if on login page
if (window.location.pathname === '/login') {
    import('./js/login.js');
}

// Import dashboard JavaScript if on dashboard page
if (window.location.pathname === '/dashboard') {
    import('./js/dashboard.js');
}

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
