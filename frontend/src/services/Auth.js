import { useToast } from 'vue-toast-notification'
import router from '../router/index.js'
import store from '@/store/index.js'
import axios from '@/axios'

function postLoginTarget() {
    const redirect = router.currentRoute.value.query.redirect
    if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')) {
        return { path: redirect }
    }
    return { name: 'diagrams' }
}

async function authenticate(endpoint, userData) {
    const $toast = useToast({ position: 'bottom-right' })
    try {
        await axios.get('/sanctum/csrf-cookie')
        const response = await axios.post(endpoint, { email: userData.email, password: userData.password })
        $toast.success(response.data.message)
        store.commit('setUser', response.data.user)
        await router.push(postLoginTarget())
    } catch (error) {
        $toast.error(error.response?.data?.message ?? 'An error occurred')
    }
}

export const Auth = {
    login: (userData) => authenticate('/api/login', userData),

    async logout() {
        const $toast = useToast({ position: 'bottom-right' })
        try {
            await axios.post('/api/logout')
        } catch {
            // Logging out regardless of server response (e.g. session already expired).
        }
        store.commit('clearUser')
        $toast.success('Logged out successfully')
        window.location.href = '/' //TODO potentially will prevent SSR in the future and gives 1 sec of white screen
    }
}
