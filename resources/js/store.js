import { createStore } from 'vuex';

export default createStore({
    state: {
        auth_token: localStorage.getItem('auth_token'),
    },
    mutations: {
        setAuthToken(state, token) {
            state.auth_token = token;
            localStorage.setItem('auth_token', token);
        },
        clearAuthToken(state) {
            state.auth_token = null;
            localStorage.removeItem('auth_token');
        },
    },
    getters: {
        loggedIn(state) {
            return state.auth_token !== null;
        },
    },
});
