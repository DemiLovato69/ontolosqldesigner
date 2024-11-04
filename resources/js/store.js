import { createStore } from 'vuex';

export default createStore({
    state: {
        auth_token: localStorage.getItem('auth_token') || null,
        user: null,
    },
    mutations: {
        login(state, token) {
            state.auth_token = token;
            localStorage.setItem('auth_token', token);
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        },
        logout(state) {
            state.auth_token = null;
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common["Authorization"];
        },
    },
    getters: {
        authToken(state) {
            return state.auth_token;
        }
    },
});
