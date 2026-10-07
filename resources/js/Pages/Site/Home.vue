<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, usePage, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  games: {
    type: Array,
    default: () => [],
  },
  khrRate: {
    type: Number,
    default: 4000,
  },
  currencies: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const adminStaff = computed(() => page.props.auth?.admin_staff);

// Selection States
const selectedGame = ref(props.games.length > 0 ? props.games[0] : null);
const selectedProduct = ref(null);
const userId = ref('');
const zoneId = ref('');

// Player Verification State
const isCheckingPlayer = ref(false);
const verifiedPlayer = ref(null);
const playerCheckError = ref(null);
let checkDebounceTimer = null;

// Checkout & KHQR Modal State
const isSubmitting = ref(false);
const showModal = ref(false);
const orderStatus = ref('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED
const orderErrorMessage = ref('');
const qrInfo = ref(null);
let pollTimer = null;

// Member Auth Modal State (2 options: Google & Telegram)
const showLoginModal = ref(false);

const telegramForm = useForm({
  phone: '',
  username: '',
});

const handleTelegramLogin = () => {
  telegramForm.post(route('auth.telegram.phone'), {
    onSuccess: () => {
      showLoginModal.value = false;
      telegramForm.reset();
    },
  });
};

const handleLogout = () => {
  router.post(route('logout'), {}, {
    preserveScroll: true,
  });
};

// Select Game
const selectGame = (game) => {
  selectedGame.value = game;
  selectedProduct.value = null;
  verifiedPlayer.value = null;
  playerCheckError.value = null;
  if (userId.value) {
    checkPlayerId();
  }
};

// Player ID Verification
const checkPlayerId = async () => {
  if (!selectedGame.value || !userId.value || userId.value.trim().length < 3) {
    verifiedPlayer.value = null;
    playerCheckError.value = null;
    return;
  }

  if (selectedGame.value.has_zone_id && !zoneId.value) {
    verifiedPlayer.value = null;
    playerCheckError.value = 'Please enter Zone ID / Server';
    return;
  }

  isCheckingPlayer.value = true;
  playerCheckError.value = null;

  try {
    const res = await axios.post('/api/check-id', {
      game_id: selectedGame.value.id,
      user_id: userId.value.trim(),
      zone_id: zoneId.value.trim(),
    });

    if (res.data.status === 'success') {
      verifiedPlayer.value = res.data;
      playerCheckError.value = null;
    } else {
      verifiedPlayer.value = null;
      playerCheckError.value = res.data.message || 'Player not found';
    }
  } catch (err) {
    verifiedPlayer.value = null;
    playerCheckError.value = err.response?.data?.message || 'Player lookup failed';
  } finally {
    isCheckingPlayer.value = false;
  }
};

// Auto check debounce when user types ID
watch([userId, zoneId], () => {
  clearTimeout(checkDebounceTimer);
  if (!userId.value) {
    verifiedPlayer.value = null;
    playerCheckError.value = null;
    return;
  }
  checkDebounceTimer = setTimeout(() => {
    checkPlayerId();
  }, 600);
});

// Checkout Action
const handleBuyNow = async () => {
  if (!selectedProduct.value || !userId.value) return;
  if (selectedGame.value.has_zone_id && !zoneId.value) return;

  isSubmitting.value = true;
  try {
    const res = await axios.post('/api/orders/create', {
      product_id: selectedProduct.value.id,
      user_id: userId.value.trim(),
      zone_id: zoneId.value.trim(),
    });

    qrInfo.value = res.data;
    showModal.value = true;
    orderStatus.value = 'PENDING';

    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(checkPaymentStatus, 3000);
  } catch (error) {
    alert(error.response?.data?.message || 'Failed to initialize order.');
  } finally {
    isSubmitting.value = false;
  }
};

const isCheckingStatus = ref(false);

const checkPaymentStatus = async () => {
  if (!qrInfo.value?.order_number) return;
  try {
    const res = await axios.get(`/api/orders/${qrInfo.value.order_number}/status`);
    if (res.data?.status) {
      orderStatus.value = res.data.status;
    }
    if (res.data?.status === 'COMPLETED' || res.data?.status === 'FAILED') {
      if (pollTimer) clearInterval(pollTimer);
    }
  } catch (e) {
    console.error('Check status error:', e);
  }
};

const forceCheckPayment = async () => {
  if (!qrInfo.value?.order_number || isCheckingStatus.value) return;
  isCheckingStatus.value = true;
  await checkPaymentStatus();
  isCheckingStatus.value = false;
};

const closeModal = () => {
  showModal.value = false;
  if (pollTimer) clearInterval(pollTimer);
  if (orderStatus.value === 'COMPLETED') {
    selectedProduct.value = null;
    userId.value = '';
    zoneId.value = '';
    verifiedPlayer.value = null;
  }
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(val || 0);
};

const formatKhr = (val) => {
  const rate = props.khrRate || 4000;
  const khr = Math.round((Number(val) || 0) * rate);
  return new Intl.NumberFormat('en-US').format(khr) + ' ៛';
};
</script>

<template>
  <Head title="VB-STORE" />

  <div class="min-h-screen bg-black text-slate-100 flex flex-col font-sans selection:bg-amber-500 selection:text-black antialiased relative overflow-x-hidden">
    <!-- Ambient Tactical Lighting & Grid Patterns -->
    <div class="fixed inset-0 bg-[radial-gradient(#18181b_1px,transparent_1px)] [background-size:24px_24px] opacity-40 pointer-events-none -z-20"></div>
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[450px] bg-gradient-to-b from-amber-500/10 via-yellow-500/5 to-transparent rounded-full blur-[160px] pointer-events-none -z-10"></div>
    <div class="fixed bottom-0 right-0 w-[600px] h-[500px] bg-cyan-500/5 rounded-full blur-[180px] pointer-events-none -z-10"></div>

    <!-- ================= TACTICAL NAVBAR ================= -->
    <header class="border-b border-zinc-800/80 bg-zinc-950/90 backdrop-blur-xl sticky top-0 z-40 shadow-2xl">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 h-18 flex justify-between items-center">
        <!-- Brand Logo -->
        <Link :href="route('home')" class="flex items-center gap-3.5 group">
          <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-amber-500 via-yellow-400 to-amber-600 p-[1.5px] shadow-lg shadow-amber-500/25 group-hover:shadow-amber-500/50 transition">
            <div class="w-full h-full bg-black rounded-[10px] flex items-center justify-center border border-amber-500/20">
              <span class="text-2xl group-hover:scale-110 transition-transform">⚡</span>
            </div>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xl font-black bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-500 bg-clip-text text-transparent tracking-widest uppercase">
                VB-STORE
              </span>
            </div>
            <p class="text-[10px] uppercase tracking-widest text-zinc-400 font-bold -mt-0.5">
            GAMING TOP UP
            </p>
          </div>
        </Link>

        <!-- Right Side: Telegram, User Auth, Admin Button -->
        <div class="flex items-center gap-3">
          <!-- Telegram Hotline -->
          <!-- <a
            href="https://t.me/your_telegram"
            target="_blank"
            class="hidden md:inline-flex items-center gap-2 text-xs bg-zinc-900 hover:bg-zinc-800 text-zinc-300 hover:text-white border border-zinc-700/80 px-3.5 py-2 rounded-xl font-bold transition shadow-sm"
          >
            <span class="text-cyan-400">💬</span>
            <span>OPERATOR HOTLINE</span>
          </a> -->

          <!-- Authenticated User Profile -->
          <div v-if="currentUser" class="flex items-center gap-2.5 pl-2 border-l border-zinc-800">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center font-black text-black text-xs shadow-md shadow-amber-500/20">
              {{ (currentUser.username || currentUser.login_name || 'U').charAt(0).toUpperCase() }}
            </div>
            <div class="hidden sm:block text-left">
              <div class="text-xs font-black text-white leading-none">
                {{ currentUser.username || currentUser.login_name }}
              </div>
              <div class="text-[10px] text-emerald-400 font-mono font-bold mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span v-if="currentUser.login_name && currentUser.login_name.includes('@')">GOOGLE MEMBER</span>
                <span v-else-if="currentUser.login_name && (currentUser.login_name.startsWith('+') || currentUser.login_name.startsWith('0') || currentUser.login_name.startsWith('tg_'))">TELEGRAM MEMBER</span>
                <span v-else>MEMBER VERIFIED</span>
              </div>
            </div>
            <button
              @click="handleLogout"
              title="Sign Out"
              class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl border border-transparent hover:border-rose-500/20 transition"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </button>
          </div>

          <!-- Guest Login Buttons (2 Options: Google & Telegram) -->
          <div v-else class="flex items-center gap-2">
            <!-- Google One-Click Button -->
            <a
              :href="route('auth.google')"
              class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold border border-zinc-700/90 shadow-md transition group"
            >
              <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
              </svg>
              <span class="hidden sm:inline">Google</span>
            </a>

            <!-- Telegram / Member Auth Modal Trigger Button -->
            <!-- <button
              @click="showLoginModal = true"
              class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-cyan-500 hover:from-sky-400 hover:to-cyan-400 text-white text-xs font-bold shadow-md shadow-sky-500/20 transition flex items-center gap-1.5 active:scale-95"
            >
              <span>✈️</span>
              <span>TELEGRAM</span>
            </button> -->
          </div>
        </div>
      </div>
    </header>

    <!-- ================= MAIN STORE CONTAINER ================= -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex-1 w-full space-y-8">

      <!-- Flash Notification Alerts -->
      <div v-if="$page.props.flash?.error" class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/40 text-rose-300 text-xs font-bold flex items-center justify-between gap-3 shadow-xl">
        <div class="flex items-center gap-2.5">
          <span class="text-base">⛔</span>
          <span>{{ $page.props.flash.error }}</span>
        </div>
        <button @click="$page.props.flash.error = null" class="text-zinc-500 hover:text-white">✕</button>
      </div>

      <div v-if="$page.props.flash?.success" class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-300 text-xs font-bold flex items-center justify-between gap-3 shadow-xl">
        <div class="flex items-center gap-2.5">
          <span class="text-base">✓</span>
          <span>{{ $page.props.flash.success }}</span>
        </div>
        <button @click="$page.props.flash.success = null" class="text-zinc-500 hover:text-white">✕</button>
      </div>

      <!-- 1. TACTICAL HERO BANNER -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-zinc-900 via-zinc-950 to-black border border-zinc-800 p-6 sm:p-10 shadow-2xl">
        <!-- Amber & Cyan cyber grid accents -->
        <div class="absolute -right-16 -bottom-16 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -top-16 w-80 h-80 bg-cyan-500/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40 tracking-wider uppercase">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                LIVE AUTOMATION // 24/7
              </span>
              <span class="text-xs text-zinc-600">•</span>
              <span class="text-xs font-mono font-bold text-cyan-400 tracking-wider">
                ⚡ 3-SECOND DIRECT TOP-UP
              </span>
            </div>

            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black text-white tracking-tight uppercase leading-none">
              TACTICAL <span class="bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-500 bg-clip-text text-transparent">GAMING ARSENAL</span>
            </h1>

            <p class="text-zinc-400 text-xs sm:text-sm max-w-xl font-medium leading-relaxed">
              Instant in-game diamond delivery via Bakong KHQR. Enter your Game ID, inspect your verified In-Game Nickname, and reload with zero latency.
            </p>

            <!-- Military-Spec Feature Tags -->
            <div class="pt-2 flex flex-wrap items-center gap-3 text-[11px] font-mono font-bold text-zinc-400">
              <span class="flex items-center gap-1.5 text-zinc-300">
                <span class="text-amber-400">✓</span> NO PASSWORD REQUIRED
              </span>
              <span class="text-zinc-700">|</span>
              <span class="flex items-center gap-1.5 text-zinc-300">
                <span class="text-emerald-400">✓</span> LIVE ID VERIFICATION
              </span>
              <span class="text-zinc-700">|</span>
              <span class="flex items-center gap-1.5 text-zinc-300">
                <span class="text-cyan-400">✓</span> OFFICIAL SERVER API
              </span>
            </div>
          </div>

          <!-- Tactical HUD Combat Status Widget -->
          <div class="bg-zinc-950/90 border border-zinc-800 p-4 rounded-2xl shrink-0 space-y-2 text-xs font-mono shadow-inner border-l-2 border-l-amber-500">
            <div class="text-[10px] text-zinc-500 uppercase tracking-widest font-bold">SYSTEM METRICS</div>
            <div class="flex items-center justify-between gap-6 text-zinc-300">
              <span>DISPATCH ENGINE:</span>
              <span class="text-emerald-400 font-bold">OPERATIONAL</span>
            </div>
            <div class="flex items-center justify-between gap-6 text-zinc-300">
              <span>PAYMENT GATEWAY:</span>
              <span class="text-amber-400 font-bold">BAKONG KHQR</span>
            </div>
            <div class="flex items-center justify-between gap-6 text-zinc-300">
              <span>AVERAGE SPEED:</span>
              <span class="text-cyan-400 font-bold">2.4 SECONDS</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. PROTOCOL 01: SELECT GAME CATEGORY -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-lg bg-gradient-to-tr from-amber-500 to-yellow-400 text-black font-black flex items-center justify-center text-xs shadow-md shadow-amber-500/20">
              01
            </span>
            <h2 class="font-black text-white text-base tracking-wider uppercase">
              SELECT COMBAT PROTOCOL // GAME
            </h2>
          </div>
          <span class="text-[11px] font-mono text-zinc-500 uppercase">
            {{ games.length }} TITLES READY
          </span>
        </div>

        <!-- Game Cards Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3.5">
          <div
            v-for="g in games"
            :key="g.id"
            @click="selectGame(g)"
            :class="[
              'p-3.5 rounded-2xl border cursor-pointer transition-all duration-200 relative group overflow-hidden flex flex-col justify-between',
              selectedGame?.id === g.id
                ? 'bg-gradient-to-b from-amber-500/15 via-zinc-900 to-zinc-950 border-amber-500 shadow-xl shadow-amber-500/10 ring-1 ring-amber-500'
                : 'bg-zinc-950/80 border-zinc-800/90 hover:border-zinc-700 hover:bg-zinc-900/60'
            ]"
          >
            <!-- Background Glow for selected -->
            <div
              v-if="selectedGame?.id === g.id"
              class="absolute top-0 right-0 w-24 h-24 bg-amber-500/10 rounded-full blur-xl pointer-events-none"
            ></div>

            <!-- Game Banner / Image -->
            <div class="w-full h-24 sm:h-28 rounded-xl bg-zinc-900 border border-zinc-800 overflow-hidden mb-3 relative flex items-center justify-center">
              <img
                v-if="g.image"
                :src="g.image"
                :alt="g.name"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              />
              <span v-else class="text-3xl">🎮</span>

              <!-- Active Status Dot -->
              <span class="absolute top-2 right-2 px-1.5 py-0.5 rounded bg-black/80 backdrop-blur border border-zinc-700 text-[9px] font-mono font-bold text-emerald-400 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                ACTIVE
              </span>
            </div>

            <!-- Game Title & Meta -->
            <div>
              <h3 class="font-black text-sm text-white group-hover:text-amber-300 transition truncate">
                {{ g.name }}
              </h3>
              <p class="text-[10px] font-mono text-zinc-400 mt-0.5">
                {{ g.products?.length || 0 }} Packages Available
              </p>
            </div>

            <!-- Selected Marker -->
            <div
              v-if="selectedGame?.id === g.id"
              class="mt-2.5 pt-2 border-t border-amber-500/30 flex items-center justify-between text-[10px] font-mono font-black text-amber-400"
            >
              <span>SELECTED</span>
              <span>✓</span>
            </div>
          </div>
        </div>
      </section>

      <!-- 3. PROTOCOL 02 & 03: PLAYER DETAILS & ARSENAL -->
      <div v-if="selectedGame" class="space-y-8">

        <!-- PROTOCOL 02: PLAYER ID IDENTIFICATION WITH LIVE IGN LOOKUP -->
        <section class="bg-zinc-950/90 border border-zinc-800/90 rounded-3xl p-5 sm:p-7 shadow-2xl relative overflow-hidden space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <span class="w-7 h-7 rounded-lg bg-gradient-to-tr from-amber-500 to-yellow-400 text-black font-black flex items-center justify-center text-xs shadow-md shadow-amber-500/20">
                02
              </span>
              <div>
                <h2 class="font-black text-white text-base tracking-wider uppercase">
                ENTER USER ID
                </h2>
                <p class="text-[11px] text-zinc-400">
                  Search & verify your in-game nickname before ordering.
                </p>
              </div>
            </div>
          </div>

          <!-- Input Fields & Verify Trigger -->
          <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <!-- User ID Input -->
            <div :class="selectedGame.has_zone_id ? 'sm:col-span-5' : 'sm:col-span-9'">
              <label class="block text-[10px] font-mono uppercase text-zinc-400 font-bold mb-1.5">
                GAME ID *
              </label>
              <div class="relative">
                <input
                  v-model="userId"
                  type="text"
                  placeholder="Enter User ID (e.g. 12345678)"
                  class="w-full bg-black border rounded-xl px-4 py-3 text-sm font-mono tracking-wider focus:outline-none transition placeholder-zinc-600 text-white"
                  :class="verifiedPlayer ? 'border-emerald-500/80 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400' : 'border-zinc-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400'"
                />
                <div v-if="verifiedPlayer" class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-emerald-400 font-bold text-sm">
                  ✓
                </div>
              </div>
            </div>

            <!-- Zone ID Input (Conditional) -->
            <div v-if="selectedGame.has_zone_id" class="sm:col-span-4">
              <label class="block text-[10px] font-mono uppercase text-zinc-400 font-bold mb-1.5">
                ZONE / SERVER ID *
              </label>
              <input
                v-model="zoneId"
                type="text"
                placeholder="Zone ID (e.g. 2001)"
                class="w-full bg-black border rounded-xl px-4 py-3 text-sm font-mono tracking-wider focus:outline-none transition placeholder-zinc-600 text-white"
                :class="verifiedPlayer ? 'border-emerald-500/80 focus:border-emerald-400' : 'border-zinc-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400'"
              />
            </div>

            <!-- Check Button -->
            <div class="sm:col-span-3 flex items-end">
              <button
                @click="checkPlayerId"
                :disabled="isCheckingPlayer || !userId"
                class="w-full py-3 px-4 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 shadow-lg"
                :class="verifiedPlayer
                  ? 'bg-zinc-900 hover:bg-zinc-800 text-emerald-400 border border-emerald-500/30'
                  : 'bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 hover:to-yellow-300 text-black shadow-amber-500/20 active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed'"
              >
                <svg v-if="isCheckingPlayer" class="w-4 h-4 animate-spin text-current" viewBox="0 0 24 24" fill="none">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span v-else>🔍</span>
                <span>{{ isCheckingPlayer ? 'SCANNING...' : (verifiedPlayer ? 'RE-CHECK ID' : 'CHECK ID') }}</span>
              </button>
            </div>
          </div>

          <!-- VERIFIED PLAYER OPERATOR CARD -->
          <div
            v-if="verifiedPlayer"
            class="p-4 rounded-2xl bg-gradient-to-r from-emerald-950/40 via-zinc-950 to-emerald-950/20 border border-emerald-500/40 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-fade-in"
          >
            <div class="flex items-center gap-3.5">
              <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 p-[1.5px] shadow-lg shadow-emerald-500/20 shrink-0">
                <div class="w-full h-full bg-black rounded-[10px] flex items-center justify-center text-xl font-black text-emerald-400">
                  ✓
                </div>
              </div>
              <div>
                 
                <div class="text-base sm:text-lg font-black text-white tracking-wide">
                  {{ verifiedPlayer.player_name }}
                </div>
                <div class="text-[11px] font-mono text-zinc-400">
                  <span>ID: {{ verifiedPlayer.user_id }}</span>
                  <span v-if="verifiedPlayer.zone_id" class="ml-2 text-zinc-500">({{ verifiedPlayer.server }})</span>
                </div>
              </div>
            </div>

       
          </div>

          <!-- Error Alert if player check fails -->
          <div
            v-if="playerCheckError"
            class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-semibold flex items-center gap-2"
          >
            <span>⚠️</span>
            <span>{{ playerCheckError }}</span>
          </div>
        </section>

        <!-- PROTOCOL 03: DIAMOND LOADOUT PACKAGES -->
        <section class="bg-zinc-950/90 border border-zinc-800/90 rounded-3xl p-5 sm:p-7 shadow-2xl space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <span class="w-7 h-7 rounded-lg bg-gradient-to-tr from-amber-500 to-yellow-400 text-black font-black flex items-center justify-center text-xs shadow-md shadow-amber-500/20">
                03
              </span>
              <div>
                <h2 class="font-black text-white text-base tracking-wider uppercase">
                  SELECT DIAMOND LOADOUT // SUPPLY CRATE
                </h2>
                <p class="text-[11px] text-zinc-400">
                  Select package tier to generate immediate KHQR barcode.
                </p>
              </div>
            </div>
            <span class="text-[11px] font-mono text-amber-400 font-bold">
              BEST VALUE GUARANTEED
            </span>
          </div>

          <!-- Product Packages Grid -->
          <div v-if="selectedGame.products && selectedGame.products.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3.5">
            <div
              v-for="item in selectedGame.products"
              :key="item.id"
              @click="selectedProduct = item"
              :class="[
                'p-4 rounded-2xl border cursor-pointer transition-all duration-200 flex flex-col justify-between relative group overflow-hidden',
                selectedProduct?.id === item.id
                  ? 'border-amber-400 bg-gradient-to-b from-amber-500/15 via-zinc-900 to-black shadow-xl shadow-amber-500/10 ring-1 ring-amber-400 scale-[1.02]'
                  : 'border-zinc-800/90 bg-black/80 hover:border-zinc-700 hover:bg-zinc-900/60'
              ]"
            >
              <!-- Glowing corner indicator -->
              <div
                v-if="selectedProduct?.id === item.id"
                class="absolute -top-6 -right-6 w-16 h-16 bg-amber-500/20 rounded-full blur-md pointer-events-none"
              ></div>

              <!-- Package Icon & Name -->
              <div>
                <div class="flex items-center justify-between mb-2">
                  <span class="text-2xl">💎</span>
                  <span
                    v-if="selectedProduct?.id === item.id"
                    class="text-[9px] font-mono font-black px-1.5 py-0.5 rounded bg-amber-400 text-black"
                  >
                    SELECTED
                  </span>
                </div>
                <h4 class="font-black text-sm text-white group-hover:text-amber-300 transition line-clamp-2">
                  {{ item.name }}
                </h4>
              </div>

              <!-- Prices & KHR Equivalent -->
              <div class="mt-4 pt-3 border-t border-zinc-800/80 flex items-baseline justify-between">
                <div>
                  <div class="text-[10px] font-mono text-zinc-500">USD</div>
                  <div class="text-base font-black text-amber-400">
                    {{ formatCurrency(item.selling_price) }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-[10px] font-mono text-zinc-500">KHR</div>
                  <div class="text-xs font-mono font-bold text-zinc-300">
                    {{ formatKhr(item.selling_price) }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-10 text-zinc-500 text-sm font-mono">
            ⚠️ No packages currently in stock for this game. Please check back shortly.
          </div>
        </section>

        <!-- PROTOCOL 04: INSTANT DEPLOY (BUY BUTTON) -->
        <div class="space-y-3">
          <button
            @click="handleBuyNow"
            :disabled="!selectedProduct || !userId || (selectedGame.has_zone_id && !zoneId) || isSubmitting"
            class="w-full py-5 bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 hover:from-amber-400 hover:to-yellow-300 text-black font-black text-base sm:text-lg rounded-2xl shadow-2xl shadow-amber-500/25 transition-all transform active:scale-[0.99] disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-3 uppercase tracking-wider"
          >
            <span class="text-xl">⚡</span>
            <span>{{ isSubmitting ? 'GENERATING KHQR BARCODE...' : (selectedProduct ? `INSTANT RECHARGE — ${formatCurrency(selectedProduct.selling_price)}` : 'SELECT DIAMOND PACKAGE TO PROCEED') }}</span>
          </button>

          <p class="text-center text-[11px] font-mono text-zinc-500">
            🔒 Protected by Bakong National Payment Switch. Instant 3-Second API Fulfillment.
          </p>
        </div>

      </div>
    </main>

    <!-- ================= FOOTER ================= -->
    <footer class="mt-auto border-t border-zinc-800/80 bg-black py-8 text-xs text-zinc-500">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row items-center justify-between gap-4 text-center md:text-left">
        <div class="flex items-center gap-3">
          <span class="font-black text-zinc-300 tracking-wider">KHMER TOPUP</span>
          <span>•</span>
          <span>Tactical Gaming Vault</span>
          <span>•</span>
          <span class="text-emerald-400 font-mono">API STATUS: ONLINE</span>
        </div>

        <div class="flex items-center gap-4 text-zinc-400 font-medium">
          <a href="https://t.me/your_telegram" target="_blank" class="hover:text-amber-400 transition">Telegram Support</a>
          <span>•</span>
          <Link :href="route('admin.dashboard')" class="hover:text-amber-400 transition">Staff Portal</Link>
        </div>
      </div>
    </footer>

    <!-- ================= REAL-TIME KHQR PAYMENT MODAL ================= -->
    <div v-if="showModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-50 flex items-center justify-center p-4">
      <div class="bg-zinc-950 border border-zinc-800 p-6 sm:p-7 rounded-3xl max-w-md w-full text-center space-y-5 shadow-2xl relative border-t-2 border-t-amber-500">
        <!-- Close Button -->
        <button @click="closeModal" class="absolute top-4 right-4 text-zinc-500 hover:text-white p-1 text-sm">
          ✕
        </button>

        <!-- PENDING STATE -->
        <div v-if="orderStatus === 'PENDING'" class="space-y-4">
          <div class="space-y-1">
            <div class="text-[10px] font-mono uppercase tracking-widest text-amber-400 font-bold">
              ORDER #{{ qrInfo?.order_number }}
            </div>
            <h3 class="font-black text-xl text-white">SCAN TO COMPLETE RECHARGE</h3>
            <p v-if="verifiedPlayer" class="text-xs text-emerald-400 font-mono font-bold">
              Target: {{ verifiedPlayer.player_name }} ({{ userId }})
            </p>
          </div>

          <!-- QR Code Container -->
          <div class="bg-white p-4 rounded-2xl inline-block shadow-2xl relative group">
            <img
              v-if="qrInfo?.qr_image"
              :src="qrInfo.qr_image"
              alt="Bakong KHQR"
              class="w-52 h-52 mx-auto object-contain"
            />
            <div v-else class="w-52 h-52 flex items-center justify-center text-zinc-800 text-xs font-mono">
              GENERATING KHQR...
            </div>
          </div>

          <!-- Payment Instructions -->
          <div class="space-y-1.5 text-xs">
            <div class="text-base font-black text-white font-mono">
              AMOUNT: <span class="text-amber-400">${{ qrInfo?.amount }}</span>
            </div>
            <p class="text-zinc-400 text-[11px]">
              Open any mobile banking app (ABA, Wing, Bakong, Acleda) and scan this QR code.
            </p>
          </div>

          <!-- Radar Pulse -->
          <div class="flex items-center justify-center gap-2 text-amber-400 text-xs font-mono font-bold animate-pulse">
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
            Awaiting bank confirmation...
          </div>

          <div class="flex gap-2 pt-1">
            <button
              @click="forceCheckPayment"
              :disabled="isCheckingStatus"
              class="flex-1 py-2.5 bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 hover:to-yellow-300 text-black rounded-xl text-xs font-black shadow-lg shadow-amber-500/20 transition disabled:opacity-50 flex items-center justify-center gap-1.5"
            >
              <svg v-if="isCheckingStatus" class="animate-spin h-3.5 w-3.5 text-black" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              <span>{{ isCheckingStatus ? 'CHECKING...' : 'I HAVE PAID // CHECK STATUS' }}</span>
            </button>
            <button
              @click="closeModal"
              class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white rounded-xl text-xs font-bold border border-zinc-800 transition"
            >
              Cancel
            </button>
          </div>
        </div>

        <!-- PROCESSING / PAID STATE (TOKOVOUCHER TOP-UP IN PROGRESS) -->
        <div v-else-if="orderStatus === 'PROCESSING' || orderStatus === 'PAID'" class="space-y-5 py-3">
          <div class="relative w-20 h-20 mx-auto">
            <div class="absolute inset-0 rounded-full bg-cyan-500/20 blur-xl animate-pulse"></div>
            <div class="w-20 h-20 rounded-full border-2 border-cyan-400/30 border-t-cyan-400 animate-spin flex items-center justify-center">
              <span class="text-2xl animate-pulse">💎</span>
            </div>
          </div>
          <div class="space-y-1.5">
            <div class="text-[10px] font-mono uppercase tracking-widest text-cyan-400 font-bold">
              PAYMENT VERIFIED // DELIVERING RECHARGE
            </div>
            <h3 class="font-black text-2xl text-white">RECHARGING DIAMONDS</h3>
            <p class="text-xs text-zinc-300 max-w-xs mx-auto">
              Delivering to <strong class="text-cyan-400">{{ verifiedPlayer?.player_name || userId }}</strong> via Tokovoucher. Please wait a moment...
            </p>
          </div>
          <div class="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-xl text-[11px] font-mono text-cyan-300 text-left space-y-1">
            <div class="flex justify-between">
              <span class="text-zinc-400">Order:</span>
              <span class="text-white font-bold">#{{ qrInfo?.order_number }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-zinc-400">Target ID:</span>
              <span class="text-emerald-400 font-bold">{{ userId }} (Zone {{ zoneId }})</span>
            </div>
            <div class="flex justify-between">
              <span class="text-zinc-400">Package:</span>
              <span class="text-amber-400 font-bold">{{ selectedProduct?.name }}</span>
            </div>
          </div>
          <div class="flex items-center justify-center gap-2 text-cyan-400 text-xs font-mono font-bold animate-pulse">
            <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
            Awaiting provider confirmation...
          </div>
        </div>

        <!-- COMPLETED STATE -->
        <div v-else-if="orderStatus === 'COMPLETED'" class="space-y-4 py-3">
          <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 rounded-full flex items-center justify-center mx-auto text-3xl font-black shadow-lg shadow-emerald-500/30">
            ✓
          </div>
          <h3 class="text-2xl font-black text-white">RECHARGE CONFIRMED!</h3>
          <p class="text-xs text-zinc-300 max-w-xs mx-auto">
            Payment verified. Diamonds have been instantly delivered to <strong class="text-emerald-400">{{ verifiedPlayer?.player_name || userId }}</strong>.
          </p>
          <button
            @click="closeModal"
            class="w-full py-3.5 bg-gradient-to-r from-amber-500 to-yellow-400 text-black font-black rounded-xl text-sm shadow-lg shadow-amber-500/20"
          >
            DONE // MAKE ANOTHER PURCHASE
          </button>
        </div>

        <!-- FAILED STATE -->
        <div v-else-if="orderStatus === 'FAILED'" class="space-y-4 py-3">
          <div class="w-16 h-16 bg-rose-500/20 text-rose-400 border border-rose-500/40 rounded-full flex items-center justify-center mx-auto text-3xl font-black shadow-lg shadow-rose-500/30">
            ✕
          </div>
          <h3 class="text-xl font-black text-white">TRANSACTION ISSUE</h3>
          <p class="text-xs text-zinc-300">
            {{ orderErrorMessage || 'Order verification timed out or was declined by provider.' }} (Order #{{ qrInfo?.order_number }}).
          </p>
          <button
            @click="closeModal"
            class="w-full py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-xl text-xs transition"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- ================= CUSTOMER LOGIN MODAL ================= -->
    <div v-if="showLoginModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-50 flex items-center justify-center p-4">
      <div class="bg-zinc-950 border border-zinc-800 p-6 sm:p-7 rounded-3xl max-w-md w-full space-y-5 shadow-2xl relative border-t-2 border-t-amber-500">
        <!-- Close Button -->
        <button @click="showLoginModal = false" class="absolute top-4 right-4 text-zinc-500 hover:text-white p-1 text-sm">
          ✕
        </button>

        <div class="text-center space-y-1.5">
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-500 to-yellow-400 mx-auto flex items-center justify-center text-xl font-black text-black mb-1 shadow-lg shadow-amber-500/25">
            ⚡
          </div>
          <h3 class="text-xl font-black text-white uppercase tracking-wider">MEMBER ACCESS</h3>
          <p class="text-xs text-zinc-400 font-medium">Connect 1 account via Google or Telegram to reload diamonds</p>
        </div>

        <div class="space-y-4">
          <!-- OPTION 1: CONNECT WITH GOOGLE -->
          <div class="p-4 rounded-2xl bg-zinc-900/80 border border-zinc-800 hover:border-zinc-700 transition space-y-2.5">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-mono font-black text-amber-400 uppercase tracking-wider">OPTION 01</span>
              <span class="text-[10px] font-mono text-emerald-400 font-bold">1-TAP SIGN IN</span>
            </div>
            <a
              :href="route('auth.google')"
              class="w-full py-3.5 px-4 rounded-xl bg-white hover:bg-zinc-100 text-zinc-900 font-bold text-xs flex items-center justify-center gap-3 transition shadow-lg group font-sans"
            >
              <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
              </svg>
              <span>Connect with Google Account</span>
            </a>
          </div>

          <!-- DIVIDER -->
          <div class="relative flex items-center justify-center my-1">
            <div class="border-t border-zinc-800/80 w-full"></div>
            <span class="bg-zinc-950 px-3 text-[10px] font-mono uppercase text-zinc-500 font-bold">OR</span>
          </div>

          <!-- OPTION 2: CONNECT WITH TELEGRAM NUMBER -->
          <div class="p-4 rounded-2xl bg-zinc-900/80 border border-sky-500/30 hover:border-sky-500/50 transition space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-mono font-black text-sky-400 uppercase tracking-wider">OPTION 02</span>
              <span class="text-[10px] font-mono text-zinc-400 font-bold">TELEGRAM NUMBER</span>
            </div>

            <form @submit.prevent="handleTelegramLogin" class="space-y-3">
              <div>
                <label class="block text-[10px] font-mono uppercase text-zinc-300 font-bold mb-1">
                  Telegram Phone Number *
                </label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-sky-400 text-sm">
                    📱
                  </div>
                  <input
                    v-model="telegramForm.phone"
                    type="tel"
                    required
                    placeholder="+855 12 345 678 or 012345678"
                    class="w-full bg-black border border-zinc-800 text-white rounded-xl pl-9 pr-3.5 py-2.5 text-xs font-mono focus:outline-none focus:border-sky-400 transition placeholder-zinc-600"
                    :class="{ 'border-rose-500': telegramForm.errors.phone }"
                  />
                </div>
                <p v-if="telegramForm.errors.phone" class="text-rose-400 text-[10px] mt-1 font-bold">{{ telegramForm.errors.phone }}</p>
              </div>

              <div>
                <label class="block text-[10px] font-mono uppercase text-zinc-400 font-bold mb-1">
                  Nickname / Gamer Name (Optional)
                </label>
                <input
                  v-model="telegramForm.username"
                  type="text"
                  placeholder="e.g. ProGamer99"
                  class="w-full bg-black border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-sky-400 transition placeholder-zinc-600"
                />
              </div>

              <button
                type="submit"
                :disabled="telegramForm.processing"
                class="w-full py-3 bg-gradient-to-r from-sky-500 to-cyan-500 hover:from-sky-400 hover:to-cyan-400 text-white font-black rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/25 active:scale-95 transition flex items-center justify-center gap-2"
              >
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.52 2.77-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                </svg>
                <span>{{ telegramForm.processing ? 'CONNECTING...' : 'CONNECT WITH TELEGRAM' }}</span>
              </button>
            </form>
          </div>
        </div>

        <p class="text-center text-[10px] font-mono text-zinc-500">
          🔒 1 Member Account per Google or Telegram connection. No password needed.
        </p>
      </div>
    </div>

  </div>
</template>