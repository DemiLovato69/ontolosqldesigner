import 'vue-toast-notification/dist/theme-sugar.css';
import { createApp } from 'vue';
import Clarity from '@microsoft/clarity';
import ToastPlugin from 'vue-toast-notification';
import { useToast } from 'vue-toast-notification';
import axios from '@/axios';

import store from '@/store/index.js'
import router from '@/router/index.js';
import App from '@/App.vue';
import { installAuthExpiryHandler, installSessionActivityTouch } from '@/services/sessionExpiry.js';

if (import.meta.env.PROD) {
    Clarity.init('wndxp2jbej');
}

// Handle CSRF mismatch (419) / session expiry (401) and refresh session on activity.
installAuthExpiryHandler({ axios, store, router });
installSessionActivityTouch({ axios, store });

store.dispatch('initializeAuth');

// Show success toast when returning from email verification link
router.afterEach((to) => {
    if (to.query.verified === '1') {
        useToast({ position: 'bottom-right' }).success('Email verified successfully!');
        router.replace({ path: to.path });
    }
});

const app = createApp(App);

app.config.globalProperties.$http = axios;

app.use(router);
app.use(store);
app.use(ToastPlugin, {
  position: 'top-right'
});

app.mount('#app');