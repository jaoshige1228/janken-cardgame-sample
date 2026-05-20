import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * @param {string} token
 * @returns {Echo}
 */
export function createEcho(token) {
  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
  const forceTLS = scheme === 'https';
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? (forceTLS ? 443 : 80));
  // ビルド時の localhost 固定を避け、実際にアクセスしているホストへ接続する
  const wsHost =
    import.meta.env.VITE_REVERB_HOST || (typeof window !== 'undefined' ? window.location.hostname : 'localhost');

  return new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort: port,
    wssPort: port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    cluster: '',
    // 絶対 URL（例: http://localhost）にすると、127.0.0.1 で開いたタブからは別オリジンになり認証が失敗する
    authEndpoint: '/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    },
    authTransport: 'ajax',
  });
}
