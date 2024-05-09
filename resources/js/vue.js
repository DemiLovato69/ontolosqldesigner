import './bootstrap';

import { createApp } from 'vue/dist/vue.esm-bundler.js';


const app = createApp({});

import Designer from "./components/Designer.vue";
import Header from "./components/Header.vue";
import ChickenFootEdge from "./components/ChickenFootEdge.vue";

import Register from "./components/Register.vue";


app.component("designer-component", Designer)
app.component("header-component", Header)
app.component("chicken-foot-edge-component", ChickenFootEdge)
app.component("register-component", Register)


app.mount('#app')
