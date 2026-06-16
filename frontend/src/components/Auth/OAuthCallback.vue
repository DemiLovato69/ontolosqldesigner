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
import { useRouter } from 'vue-router'
import axios from '@/axios'
import store from '@/store/index.js'
import '@/css/auth.css'

const router = useRouter()

onMounted(async () => {
    try {
        const response = await axios.get('/api/user')
        store.commit('setUser', response.data)
        localStorage.setItem('last_login_email', response.data.email)
        localStorage.removeItem('last_login_avatar')
        router.replace({ name: 'diagrams' })
        return
    } catch {
        localStorage.removeItem('last_login_email')
        localStorage.removeItem('last_login_avatar')
    }

    router.replace({ name: 'login', query: { oauth_error: 'company_domain' } })
})
</script>
