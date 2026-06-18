import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import axios from '@/axios.js'

window.Pusher = Pusher

const isUnresolvedPlaceholder = (value) => /\$\{[^}]+\}/.test(String(value ?? ''))

export function createEcho() {
    const config = {
        key: import.meta.env.VITE_REVERB_APP_KEY,
        host: import.meta.env.VITE_REVERB_HOST,
        port: import.meta.env.VITE_REVERB_PORT,
        scheme: import.meta.env.VITE_REVERB_SCHEME ?? 'https',
    }

    if (isUnresolvedPlaceholder(config.key) || isUnresolvedPlaceholder(config.host) || isUnresolvedPlaceholder(config.scheme)) {
        console.error('Reverb Vite env vars were not resolved at build time.', {
            VITE_REVERB_APP_KEY: config.key,
            VITE_REVERB_HOST: config.host,
            VITE_REVERB_SCHEME: config.scheme,
        })

        return null
    }

    return new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port ?? 80,
        wssPort: config.port ?? 443,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: async (socketId, callback) => {
                try {
                    const response = await axios.post('/broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channel.name,
                    })
                    callback(false, response.data)
                } catch (error) {
                    callback(true, error)
                }
            },
        }),
    })
}
