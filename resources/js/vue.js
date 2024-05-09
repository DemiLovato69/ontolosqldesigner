import './bootstrap';

import { createApp } from 'vue/dist/vue.esm-bundler.js';
import ToastPlugin from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-bootstrap.css';


const app = createApp({});
app.use(ToastPlugin, {
    position: 'top-right'
})

import Designer from "./components/Designer.vue";
import Header from "./components/Header.vue";
import ChickenFootEdge from "./components/ChickenFootEdge.vue";

import Register from "./components/Register.vue";
import Login from "./components/Login.vue"

app.component("designer-component", Designer)
app.component("header-component", Header)
app.component("chicken-foot-edge-component", ChickenFootEdge)
app.component("register-component", Register)
app.component('login-component', Login)

app.mount('#app')
