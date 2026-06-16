<template>
    <div class="centered-container">
        <div class="auth-card">
            <div class="auth-form">
                <p class="auth-label">Signing in...</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '@/axios'
import store from '@/store/index.js'
import '@/css/auth.css'

const route = useRoute()
const router = useRouter()

onMounted(async () => {
    const token = Array.isArray(route.query.token) ? route.query.token[0] : route.query.token
    const avatar = Array.isArray(route.query.avatar) ? route.query.avatar[0] : route.query.avatar

    if (token) {
        store.commit('login', token)
        avatar
            ? localStorage.setItem('last_login_avatar', avatar)
            : localStorage.removeItem('last_login_avatar')

        try {
            const response = await axios.get('/api/user')
            localStorage.setItem('last_login_email', response.data.email)
        } catch {
            localStorage.removeItem('last_login_email')
            localStorage.removeItem('last_login_avatar')
        }
        router.replace({ name: 'diagrams' })
        return
    }

    router.replace({ name: 'login', query: { oauth_error: 'company_domain' } })
})
</script>
