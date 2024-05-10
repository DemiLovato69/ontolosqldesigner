import { createStore } from 'vuex';

export default createStore({
    state: {
        auth_token: localStorage.getItem('auth_token'),
        current_diagram_name: localStorage.getItem('current_diagram') || 'demo',
        current_diagram_id: localStorage.getItem('current_diagram_id') || null,
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
        setCurrentDiagramName(state, diagram) {
            state.current_diagram_name = diagram;
            localStorage.setItem('current_diagram_name', diagram);
        },
        clearCurrentDiagramName(state) {
            state.current_diagram_name = 'demo';
            localStorage.removeItem('current_diagram_name');
        },
        setCurrentDiagramId(state, id) {
            state.current_diagram_id = id;
            localStorage.setItem('current_diagram_id', id);
        },
        clearCurrentDiagramId(state) {
            state.current_diagram_id = null;
            localStorage.removeItem('current_diagram_id');
        },
    },
    getters: {
        loggedIn(state) {
            return state.auth_token !== null;
        },
        authToken(state) {
            return state.auth_token;
        }
    },
});
