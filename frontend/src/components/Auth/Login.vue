<template>
    <div class="centered-container">
        <div class="auth-card">
            <div class="auth-tabs">
                <button class="auth-tab auth-tab--active" @click="router.push({ name: 'login' })">Sign in</button>
            </div>

            <form class="auth-form" @submit.prevent="login">
                <div class="auth-field">
                    <label class="auth-label" for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        class="auth-input"
                        v-model="userData.email"
                        placeholder="you@example.com"
                        required
                        autocomplete="email"
                    />
                </div>
                <div class="auth-field">
                    <label class="auth-label" for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        class="auth-input"
                        v-model="userData.password"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    />
                </div>
                <button type="submit" class="auth-submit">Sign in</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Auth } from '@/services/Auth.js'
import router from '@/router/index.js'
import '@/css/auth.css'

const userData = ref({ email: '', password: '' })

const login = async () => {
    await Auth.login(userData.value)
}
</script>
