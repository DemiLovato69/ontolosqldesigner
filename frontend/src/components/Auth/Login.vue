<template>
    <div class="centered-container">
        <div class="auth-card">
            <div class="auth-tabs">
                <button class="auth-tab auth-tab--active">Sign in</button>
            </div>

            <div class="auth-form">
                <p v-if="oauthError" class="auth-error">
                    Use your company Google account to sign in.
                </p>
                <div v-if="lastLoginEmail" class="auth-last-login">
                    <img
                        v-if="lastLoginAvatar"
                        :src="lastLoginAvatar"
                        alt=""
                        referrerpolicy="no-referrer"
                    />
                    <div v-else class="auth-last-login__avatar">
                        {{ lastLoginEmail.charAt(0).toUpperCase() }}
                    </div>
                    <div class="auth-last-login__copy">
                        <span>Last signed in as</span>
                        <strong>{{ maskedLastLoginEmail }}</strong>
                    </div>
                </div>
                <a class="auth-submit auth-submit--google" href="/auth/google">
                    <img src="@/icons/google.svg" alt="" />
                    <span>Sign in with Google</span>
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import '@/css/auth.css'

const route = useRoute()
const lastLoginEmail = localStorage.getItem('last_login_email')
const lastLoginAvatar = localStorage.getItem('last_login_avatar')
const oauthError = computed(() => route.query.oauth_error === 'company_domain')
const maskedLastLoginEmail = computed(() => maskEmail(lastLoginEmail))

function maskEmail(email) {
    if (!email) return ''

    const [local, domain] = email.split('@')

    if (!local || !domain) return email
    if (local.length <= 2) return `${local.charAt(0)}***@${domain}`

    return `${local.slice(0, 2)}${'*'.repeat(Math.min(local.length - 2, 6))}@${domain}`
}
</script>
