import axios from "axios";

export const Auth = {

    async register(userData) {
        try {
            const response = await axios.post('/api/auth/register', {
                email: userData.email,
                password: userData.password
            });
        }
        catch (error) {
            if (error.response) {

            }
            else if (error.request) {

            }
            else {

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
        }
        catch (error) {
            if (error.response) {

            }
            else if (error.request) {

            }
            else {

            }
        }
    }
}






