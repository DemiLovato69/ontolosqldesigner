import axios from "axios";

import { useToast } from 'vue-toast-notification';
const $toast = useToast();

export const Auth = {

    async register(userData) {
        try {
            const response = await axios.post('/api/register', {
                email: userData.email,
                password: userData.password
            });
            $toast.success(response.data.message)
        }
        catch (error) {
            if (error.response) {
                $toast.error(error.response.data.message);
            }
            else {
                $toast.error('Something went wrong!')
            }
        }
    },

    async login(userData, store) {
        try {
            await axios.get('/sanctum/csrf-cookie');

            const response = await axios.post('/api/login', {
                email: userData.email,
                password: userData.password
            });
            store.commit('setAuthToken', response.data.token);
            $toast.success(response.data.message);

            setTimeout(() => window.location.href = `/diagrams`, 500);
        }
        catch (error) {

            if (error.response) {
                $toast.error(error.response.data.message);
            } else {
                $toast.error('Something went wrong!');
            }
        }
    },

    async logout(store) {
        store.commit('clearAuthToken');
        store.commit('clearCurrentDiagramId');
        store.commit('clearCurrentDiagramName');
        try {
            const response = await axios.post('/api/logout',);
            $toast.success(response.data.message);
            setTimeout(() => window.location.href = '/', 500);
        } catch (error) {
            if (error.response) {
                $toast.error(error.response.data.message);
            } else {
                $toast.error('Something went wrong!');
            }
        }
    },
}






