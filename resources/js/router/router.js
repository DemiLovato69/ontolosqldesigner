import { createRouter, createWebHistory } from 'vue-router';
const DiagramList = () => import('../components/DiagramList.vue');
const Diagram = () => import('../components/Diagram.vue');

const routes = [
    { path: '/diagrams', component: DiagramList, name: 'DiagramList' },
    { path: '/diagrams/:id', component: Diagram, name: 'Diagram', props: true },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
