/**
 * Echo のソケット ID（Laravel の broadcast toOthers 用）。App.vue で設定する。
 * @type {() => string|null|undefined}
 */
let getSocketId = () => null;

export function setSocketIdGetter(fn) {
  getSocketId = typeof fn === 'function' ? fn : () => null;
}

/**
 * @param {string} path
 * @param {RequestInit} [init]
 */
export async function api(path, init = {}) {
  const token = localStorage.getItem('player_token');
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...init.headers,
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  const sid = getSocketId?.();
  if (sid) {
    headers['X-Socket-ID'] = sid;
  }

  // 常に現在のページと同一オリジン（/api）。localhost 固定の VITE_API_URL は使わない。
  // 127.0.0.1 と localhost の混在や、別ポートでの CORS 問題を避ける。
  const res = await fetch(`/api${path}`, { ...init, headers });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = new Error(data.message || res.statusText);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}
