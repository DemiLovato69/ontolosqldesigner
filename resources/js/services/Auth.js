import axios from "axios";
import {useToast} from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';

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

    async login(userData) {
        try {
            const response = await axios.post('/api/auth/login', {
                email: userData.email,
                password: userData.password
            });
            localStorage.setItem('auth_token', JSON.stringify(response.data.token));
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
    }
}






