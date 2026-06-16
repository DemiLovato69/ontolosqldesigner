import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import axios from '@/axios.js'

window.Pusher = Pusher

export function createEcho() {
    return new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
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
