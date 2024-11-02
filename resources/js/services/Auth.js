import axios from "axios";
import { useToast } from 'vue-toast-notification';
import router from '../router';

const $toast = useToast();

export const Auth = {

    async register(userData) {
        try {
            const response = await axios.post('/api/register', {
                email: userData.email,
                password: userData.password
            });
            $toast.success(response.data.message);
        } catch (error) {
            if (error.response) {
                $toast.error(error.response.data.message);
            } else {
                $toast.error('Something went wrong!');
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
            await router.push({name: 'diagrams'});
        } catch (error) {
            console.log(error)
            if (error.response) {
                $toast.error(error.response.data.message);
            } else {
                $toast.error('Something went wrong!');
            }
        }
    },

    async logout(store) {
        try {

            const response = await axios.post('/api/logout');

            store.commit('clearAuthToken');

            $toast.success(response.data.message);
            await router.push({path: '/'});
        } catch (error) {

            if (error.response) {
                $toast.error(error.response.data.message);
            } else {

                $toast.error('Something went wrong!');
            }
        }
    },
};
