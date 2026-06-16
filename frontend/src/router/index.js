import store from '@/store/index.js';
import { createRouter, createWebHistory } from 'vue-router';

const Layout = () => import('../components/Layout.vue');
const Login = () => import('../components/Auth/Login.vue');
const Logout = () => import('../components/Auth/Logout.vue');
const OAuthCallback = () => import('../components/Auth/OAuthCallback.vue');
const VerifyEmail = () => import('../components/Auth/VerifyEmail.vue');
const DiagramList = () => import('../components/DiagramList.vue');
const Diagram = () => import('../components/Diagram/Diagram.vue');

function requireAuth(to, from, next) {
  if (store.getters.isAuthenticated) {
    next();
    return;
  }

  store.dispatch('fetchUser')
      .then(() => {
        next();
      })
      .catch(() => {
        store.commit('clearUser');
        next({ name: 'login' });
      });
}

const routes = [
  {
    path: '/',
    redirect: { name: 'diagrams' },
  },
  {
    path: '/',
    component: Layout,
    children: [
      { path: 'register', redirect: { name: 'login' } },
      { path: 'login', name: 'login', component: Login },
      { path: 'oauth/callback', name: 'oauth.callback', component: OAuthCallback },
      { path: 'logout', name: 'logout', component: Logout },
      { path: 'verify-email', name: 'verify-email', component: VerifyEmail },
      { path: 'diagrams', name: 'diagrams', component: DiagramList, beforeEnter: requireAuth },
      { path: 'diagrams/:token', name: 'diagram.show', component: Diagram },
      { path: 'demo', name: 'demo', component: Diagram, props: { isDemo: true } },
      { path: 'shared/:token', redirect: to => ({ name: 'diagram.show', params: { token: to.params.token } }) },
    ]
  },
];

    const router = createRouter({
      history: createWebHistory(),
      routes,
    });

    const pageTitles = {
      'login': 'Login — OntoloSQL Designer',
      'verify-email': 'Verify Email — OntoloSQL Designer',
      'diagrams': 'My Diagrams — OntoloSQL Designer',
      'diagram.show': 'Diagram Editor — OntoloSQL Designer',
      'demo': 'Try Demo — OntoloSQL Designer',
    };

    router.afterEach((to) => {
      document.title = pageTitles[to.name] || 'OntoloSQLDesigner - The free Ontology Design and Generation Tool';
    });

    export default router;
