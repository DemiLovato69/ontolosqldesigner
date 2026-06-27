import { createStore } from 'vuex';
import axios from '@/axios';

export default createStore({
    state: {
        authenticated: false,
        authChecked: false,
        user: null,
    },
    mutations: {
        setUser(state, user) {
            state.user = user;
            state.authenticated = !!user;
            state.authChecked = true;
        },
        clearUser(state) {
            state.user = null;
            state.authenticated = false;
            state.authChecked = true;
        },
    },
    getters: {
        isAuthenticated(state) {
            return state.authenticated;
        }
    },
    actions: {
        async fetchUser({ commit }) {
            // Auth probe: a 401 here just means "not logged in". The global expiry
            // handler must not redirect for this; route guards handle that.
            const response = await axios.get('/api/user', { __skipAuthExpiry: true });
            commit('setUser', response.data);
            return response.data;
        },
        async initializeAuth({ dispatch, commit }) {
            try {
                return await dispatch('fetchUser');
            } catch {
                commit('clearUser');
                return null;
            }
        }
    }
});
