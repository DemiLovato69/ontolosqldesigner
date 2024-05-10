import axios from "axios";
import { useToast } from 'vue-toast-notification';

const $toast = useToast();

export const Auth = {

    async register(userData) {
        try {
            const response = await axios.post('/api/auth/register', {
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
            const response = await axios.post('/api/auth/login', {
                email: userData.email,
                password: userData.password
            });
            store.commit('setAuthToken', response.data.token);
            $toast.success(response.data.message)
        }
        catch (error) {
            if (error.response) {
                $toast.error(error.response.data.message);
            }
            else {
                console.log(error)
                $toast.error('Something went wrong!')
            }
        }
    },
    logout(store) {
        store.commit('clearAuthToken');
    },
}






