<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { createEcho } from './echo.js';
import { api, setSocketIdGetter } from './api.js';

const labels = { rock: 'グー', paper: 'パー', scissors: 'チョキ' };

const token = ref(localStorage.getItem('player_token') || '');
const roomId = ref(Number(localStorage.getItem('room_id')) || 0);
const gameId = ref(Number(localStorage.getItem('game_id')) || 0);
const roomCode = ref(localStorage.getItem('room_code') || '');
const slot = ref(Number(localStorage.getItem('slot')) || 0);
const joinCodeInput = ref('');
const error = ref('');
const state = ref(null);
const battleInfo = ref(null);
const echoRef = ref(null);
/** @type {ReturnType<typeof setInterval> | null} */
let pollTimer = null;

const isMyTurn = computed(() => state.value?.current_turn_slot === state.value?.your_slot);

function updateSocketIdGetter() {
  setSocketIdGetter(() => {
    try {
      return echoRef.value?.socketId?.() ?? null;
    } catch {
      return null;
    }
  });
}

function startPolling() {
  stopPolling();
  pollTimer = setInterval(() => {
    if (token.value && gameId.value) {
      refreshState();
    }
  }, 2000);
}

function stopPolling() {
  if (pollTimer != null) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

async function refreshState() {
  if (!token.value || !gameId.value) {
    return;
  }
  try {
    const next = await api(`/games/${gameId.value}`, { method: 'GET' });
    await nextTick();
    state.value = next;
  } catch (e) {
    console.error('[refreshState]', e);
  }
}

function persistSession(payload) {
  token.value = payload.player_token;
  roomId.value = payload.room_id;
  gameId.value = payload.game_id;
  roomCode.value = payload.room_code;
  slot.value = payload.slot;
  localStorage.setItem('player_token', payload.player_token);
  localStorage.setItem('room_id', String(payload.room_id));
  localStorage.setItem('game_id', String(payload.game_id));
  localStorage.setItem('room_code', payload.room_code);
  localStorage.setItem('slot', String(payload.slot));
}

async function createRoom() {
  error.value = '';
  try {
    const data = await api('/rooms', { method: 'POST' });
    persistSession(data);
    await connectEcho();
    await refreshState();
  } catch (e) {
    error.value = e.message;
  }
}

async function joinRoom() {
  error.value = '';
  try {
    const code = joinCodeInput.value.trim().toUpperCase();
    const data = await api(`/rooms/${code}/join`, { method: 'POST' });
    persistSession(data);
    await connectEcho();
    await refreshState();
  } catch (e) {
    error.value = e.message;
  }
}

function subscribeChannel(ec, rid) {
  const name = `room.${rid}`;
  const ch = ec.private(name);

  ch.error((err) => {
    console.error('[Echo] private channel error', name, err);
  });

  ch.subscribed(() => {
    refreshState();
  });

  // イベント名の取り違えでも状態を同期する（listenToAll は pusher: 系を除く全イベントを受ける）
  ch.listenToAll((eventName, data) => {
    const ev = String(eventName);
    if (ev.includes('BattleResult') && data && typeof data === 'object') {
      battleInfo.value = data;
    }
    refreshState();
  });
}

async function connectEcho() {
  if (!token.value || !roomId.value) {
    return;
  }
  if (echoRef.value) {
    echoRef.value.disconnect();
    echoRef.value = null;
  }
  const ec = createEcho(token.value);
  echoRef.value = ec;
  updateSocketIdGetter();

  const pusher = ec.connector?.pusher;
  if (pusher?.connection) {
    pusher.connection.bind('connected', updateSocketIdGetter);
    pusher.connection.bind('disconnected', updateSocketIdGetter);
  }

  subscribeChannel(ec, roomId.value);
  startPolling();
}

async function playCard(card) {
  error.value = '';
  try {
    await api(`/games/${gameId.value}/play`, {
      method: 'POST',
      body: JSON.stringify({ card }),
    });
    await refreshState();
  } catch (e) {
    error.value = e.message;
  }
}

async function endTurn() {
  error.value = '';
  try {
    await api(`/games/${gameId.value}/end-turn`, { method: 'POST' });
    battleInfo.value = null;
    await refreshState();
  } catch (e) {
    error.value = e.message;
  }
}

function clearSession() {
  stopPolling();
  setSocketIdGetter(() => null);
  localStorage.clear();
  token.value = '';
  roomId.value = 0;
  gameId.value = 0;
  roomCode.value = '';
  slot.value = 0;
  state.value = null;
  battleInfo.value = null;
  if (echoRef.value) {
    echoRef.value.disconnect();
    echoRef.value = null;
  }
}

onMounted(async () => {
  if (token.value && gameId.value) {
    await connectEcho();
    await refreshState();
  }
});

onBeforeUnmount(() => {
  stopPolling();
  setSocketIdGetter(() => null);
  if (echoRef.value) {
    echoRef.value.disconnect();
  }
});

function cardLabel(c) {
  if (!c || c === 'hidden') {
    return c === 'hidden' ? '？？' : '—';
  }
  return labels[c] || c;
}
</script>

<template>
  <div class="app">
    <header class="header">
      <h1>じゃんけんカード（1vs1）</h1>
    </header>

    <main class="main">
      <p v-if="error" class="error">{{ error }}</p>

      <section v-if="!token" class="panel">
        <h2>参加</h2>
        <div class="row">
          <button type="button" class="btn primary" @click="createRoom">ルームを作成</button>
        </div>
        <div class="row join-row">
          <input v-model="joinCodeInput" type="text" maxlength="8" placeholder="ルームコード" class="input" />
          <button type="button" class="btn" @click="joinRoom">入室</button>
        </div>
      </section>

      <section v-else class="panel">
        <div class="toolbar">
          <span>ルーム <strong>{{ roomCode }}</strong> / あなたはプレイヤー{{ slot }}</span>
          <button type="button" class="btn ghost" @click="clearSession">退出</button>
        </div>

        <div v-if="state" class="game">
          <p class="phase">
            フェーズ: <strong>{{ state.phase }}</strong>
            <template v-if="state.current_turn_slot"> — 手番: プレイヤー{{ state.current_turn_slot }}</template>
          </p>

          <div class="field">
            <div class="field-card">
              <span class="label">プレイヤー1</span>
              <span class="card">{{ cardLabel(state.field?.player1_card) }}</span>
            </div>
            <div class="vs">VS</div>
            <div class="field-card">
              <span class="label">プレイヤー2</span>
              <span class="card">{{ cardLabel(state.field?.player2_card) }}</span>
            </div>
          </div>

          <div v-if="battleInfo" class="battle-banner">
            <template v-if="battleInfo.outcome === 'draw'">引き分け — もう一度先攻から</template>
            <template v-else>結果: {{ battleInfo.outcome === 'player1' ? 'プレイヤー1' : 'プレイヤー2' }} の勝ち</template>
          </div>

          <div v-if="state.phase === 'waiting_players'" class="wait">相手の入室を待っています…</div>

          <div v-else-if="state.phase === 'finished'" class="result">
            <p v-if="state.winner === 'player1'">プレイヤー1 の勝ち！</p>
            <p v-else-if="state.winner === 'player2'">プレイヤー2 の勝ち！</p>
          </div>

          <div v-else class="actions">
            <h3>あなたの手札</h3>
            <div class="hand">
              <button
                v-for="(ok, key) in state.your_hand"
                :key="key"
                type="button"
                class="btn card-btn"
                :disabled="!ok || !isMyTurn || state.phase === 'battle' || state.phase === 'finished'"
                @click="playCard(key)"
              >
                {{ labels[key] }}
              </button>
            </div>
            <button
              type="button"
              class="btn primary"
              :disabled="!isMyTurn || state.phase === 'battle' || state.phase === 'finished'"
              @click="endTurn"
            >
              ターン終了
            </button>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<style>
:root {
  font-family: system-ui, sans-serif;
  color: #1a1a2e;
  background: #f0f4f8;
}
.app {
  max-width: 640px;
  margin: 0 auto;
  padding: 1rem;
}
.header h1 {
  font-size: 1.25rem;
  margin: 0 0 1rem;
}
.panel {
  background: #fff;
  border-radius: 12px;
  padding: 1rem 1.25rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}
.row {
  margin: 0.75rem 0;
}
.join-row {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.input {
  flex: 1;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ccc;
  border-radius: 8px;
}
.btn {
  padding: 0.5rem 1rem;
  border-radius: 8px;
  border: 1px solid #ccc;
  background: #fff;
  cursor: pointer;
}
.btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.btn.primary {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}
.btn.ghost {
  border: none;
  color: #64748b;
}
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.error {
  color: #b91c1c;
  margin-bottom: 0.75rem;
}
.phase {
  font-size: 0.95rem;
}
.field {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin: 1rem 0;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 10px;
}
.field-card {
  flex: 1;
  text-align: center;
}
.field-card .label {
  display: block;
  font-size: 0.8rem;
  color: #64748b;
  margin-bottom: 0.35rem;
}
.field-card .card {
  font-size: 1.5rem;
  font-weight: 700;
}
.vs {
  font-weight: 800;
  color: #94a3b8;
}
.wait,
.result {
  text-align: center;
  padding: 1rem;
}
.battle-banner {
  text-align: center;
  padding: 0.75rem;
  background: #fef3c7;
  border-radius: 8px;
  margin-bottom: 0.75rem;
}
.hand {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin: 0.5rem 0 1rem;
}
.card-btn {
  min-width: 4rem;
}
.actions h3 {
  font-size: 1rem;
  margin: 0 0 0.5rem;
}
</style>
