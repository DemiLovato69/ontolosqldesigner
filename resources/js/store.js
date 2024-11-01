import { createStore } from 'vuex';

export default createStore({
    state: {
        auth_token: localStorage.getItem('auth_token'),
        current_diagram_name: localStorage.getItem('current_diagram') || 'demo',
    },
    mutations: {
        setAuthToken(state, token) {
            state.auth_token = token;
            localStorage.setItem('auth_token', token);
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        },
        clearAuthToken(state) {
            state.auth_token = null;
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common["Authorization"];
        },
        setCurrentDiagramName(state, diagram) {
            state.current_diagram_name = diagram;
            localStorage.setItem('current_diagram_name', diagram);
        },
        clearCurrentDiagramName(state) {
            state.current_diagram_name = 'demo';
            localStorage.removeItem('current_diagram_name');
        },
    },
    getters: {
        authToken(state) {
            return state.auth_token;
        }
    },
});
