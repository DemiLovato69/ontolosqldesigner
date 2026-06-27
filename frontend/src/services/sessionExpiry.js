// Centralized handling for Sanctum SPA cookie/session auth expiry.
//
// - 419 (CSRF token mismatch / page expired): refresh the CSRF cookie and retry
//   the original request once. If it still fails as an auth error, treat it as
//   an expired session.
// - 401 (Unauthenticated) or a second 419: clear local auth state and send the
//   user to the login screen.
// - Successful authenticated activity (and explicit user interaction) refreshes
//   the server session lifetime via a throttled GET /api/user touch.

const NO_REDIRECT_ROUTES = ['login', 'logout', 'oauth.callback', 'demo']
const TOUCH_INTERVAL_MS = 5 * 60 * 1000

let isHandlingAuthExpiry = false
let lastActivityTouch = Date.now()

function isAuthEndpoint(url = '') {
    return url.includes('/sanctum/csrf-cookie')
        || url.includes('/api/login')
        || url.includes('/api/logout')
}

function safeRedirectPath(fullPath, currentName) {
    if (!fullPath || fullPath === '/' || currentName === 'login') return null
    return { redirect: fullPath }
}

function handleExpiry(error, { store, router }) {
    const config = error.config ?? {}
    if (config.__skipAuthExpiry) {
        return Promise.reject(error)
    }

    error.__authExpired = true
    store.commit('clearUser')

    const current = router.currentRoute.value
    if (!NO_REDIRECT_ROUTES.includes(current.name) && !isHandlingAuthExpiry) {
        isHandlingAuthExpiry = true
        const query = safeRedirectPath(current.fullPath, current.name) ?? {}
        router.replace({ name: 'login', query })
            .catch(() => {})
            .finally(() => setTimeout(() => { isHandlingAuthExpiry = false }, 500))
    }

    return Promise.reject(error)
}

export function installAuthExpiryHandler({ axios, store, router }) {
    axios.interceptors.response.use(
        (response) => {
            lastActivityTouch = Date.now()
            return response
        },
        async (error) => {
            const status = error.response?.status
            const config = error.config ?? {}

            // Preserve existing email-verification redirect behavior.
            if (status === 403 && error.response?.data?.message === 'Your email address is not verified.') {
                router.push({ name: 'verify-email' }).catch(() => {})
                return Promise.reject(error)
            }

            if (isAuthEndpoint(config.url)) {
                return Promise.reject(error)
            }

            // CSRF mismatch / expired page: refresh token and retry once.
            if (status === 419 && !config.__csrfRetried && !config.__skipAuthExpiry) {
                config.__csrfRetried = true
                try {
                    await axios.get('/sanctum/csrf-cookie', { __skipAuthExpiry: true })
                    if (config.headers) delete config.headers['X-XSRF-TOKEN']
                    return await axios(config)
                } catch (retryError) {
                    const retryStatus = retryError.response?.status
                    if (retryStatus === 401 || retryStatus === 419) {
                        return handleExpiry(retryError, { store, router })
                    }
                    return Promise.reject(retryError)
                }
            }

            if (status === 401 || status === 419) {
                return handleExpiry(error, { store, router })
            }

            return Promise.reject(error)
        }
    )
}

export function installSessionActivityTouch({ axios, store }) {
    const onActivity = () => {
        if (!store.getters.isAuthenticated) return
        const now = Date.now()
        if (now - lastActivityTouch < TOUCH_INTERVAL_MS) return
        lastActivityTouch = now
        // A valid request refreshes the Laravel session lifetime. If the session
        // has already expired, the global handler redirects to login.
        axios.get('/api/user').catch(() => {})
    }

    ;['pointerdown', 'keydown', 'touchstart'].forEach((event) => {
        window.addEventListener(event, onActivity, { passive: true })
    })
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') onActivity()
    })
}
