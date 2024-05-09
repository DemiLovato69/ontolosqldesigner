import axios from "axios";

export const Auth = {

    async register(userData) {
        console.log(userData)
        const response = await axios.post('/api/auth/register', {
            email: userData.email,
            password: userData.password
        });
        console.log(response)
    },
    async login() {

    }
}
