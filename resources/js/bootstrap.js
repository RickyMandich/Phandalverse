import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Import Bootstrap JavaScript
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;