import './bootstrap';

import 'vue-toast-notification/dist/theme-sugar.css';
import { createApp } from 'vue/dist/vue.esm-bundler.js';
import ToastPlugin from 'vue-toast-notification';

import store from './store';
import router from './router';
import App from './App.vue';


const app = createApp(App).use(router).mount('#app');
app.use(ToastPlugin, {
    position: 'top-right'
})
app.use(store)
app.use(router);

