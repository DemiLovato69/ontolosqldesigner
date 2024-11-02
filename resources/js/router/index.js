import { createRouter, createWebHistory } from 'vue-router';
import Register from '../components/Register.vue';
import Login from '../components/Login.vue';
import DiagramList from '../components/DiagramList.vue';
import Diagram from '../components/Diagram.vue';
import store from '../store.js';

function requireAuth(to, from, next) {
    if (!store.state.auth_token) {
        console.log(store.state.auth_token)
        next({ name: 'login' });
    } else {
        next();
    }
}

const routes = [
    { path: '/register', name: 'register', component: Register },
    { path: '/login', name: 'login', component: Login },
    { path: '/diagrams', name: 'diagrams', component: DiagramList, beforeEnter: requireAuth },
    { path: '/diagrams/:id', name: 'diagram.show', component: Diagram, beforeEnter: requireAuth, props: true },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
