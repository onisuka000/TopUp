<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

// ទទួលទិន្នន័យ games & products ពី Laravel Controller
const props = defineProps({
  games: Array,
});

console.log(props.games)

// State គ្រប់គ្រងការជ្រើសរើស
const selectedGame = ref(props.games.length > 0 ? props.games[0] : null);
const selectedProduct = ref(null);
const userId = ref('');
const zoneId = ref('');

// State សម្រាប់ដំណើរការ Checkout & Modal KHQR
const isSubmitting = ref(false);
const showModal = ref(false);
const orderStatus = ref('PENDING'); // PENDING, COMPLETED, FAILED
const qrInfo = ref(null);
let pollTimer = null;

// ប្តូរហ្គេម
const selectGame = (game) => {
  selectedGame.value = game;
  selectedProduct.value = null; // reset កញ្ចប់ពេជ្រ
};

// បញ្ជាទិញ និងទាញយក KHQR
const handleBuyNow = async () => {
  if (!selectedProduct.value || !userId.value) return;
  if (selectedGame.value.has_zone_id && !zoneId.value) return;

  isSubmitting.value = true;
  try {
    const res = await axios.post('/api/orders/create', {
      product_id: selectedProduct.value.id,
      user_id: userId.value,
      zone_id: zoneId.value,
    });

    qrInfo.value = res.data;
    showModal.value = true;
    orderStatus.value = 'PENDING';

    // ចាប់ផ្តើម Polling ឆែកស្ថានភាពបង់ប្រាក់រៀងរាល់ 3 វិនាទី
    pollTimer = setInterval(checkPaymentStatus, 3000);
  } catch (error) {
    alert(error.response?.data?.message || 'មានបញ្ហាក្នុងការបង្កើត Order');
  } finally {
    isSubmitting.value = false;
  }
};

const checkPaymentStatus = async () => {
  if (!qrInfo.value?.order_number) return;
  try {
    const res = await axios.get(`/api/orders/${qrInfo.value.order_number}/status`);
    if (res.data.status === 'COMPLETED') {
      orderStatus.value = 'COMPLETED';
      clearInterval(pollTimer);
    } else if (res.data.status === 'FAILED') {
      orderStatus.value = 'FAILED';
      clearInterval(pollTimer);
    }
  } catch (e) {
    console.error('Check status error:', e);
  }
};

const closeModal = () => {
  showModal.value = false;
  if (pollTimer) clearInterval(pollTimer);
  if (orderStatus.value === 'COMPLETED') {
    selectedProduct.value = null;
    userId.value = '';
    zoneId.value = '';
  }
};
</script>

<template>
  <Head title="Top Up Diamond - បញ្ចូលពេជ្រស្វ័យប្រវត្តិ" />

  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans">
    <!-- Navbar -->
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-40">
      <div class="max-w-4xl mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="text-2xl">⚡</span>
          <span class="text-xl font-black bg-gradient-to-r from-amber-400 to-yellow-500 bg-clip-text text-transparent tracking-wider">
            KHMER TOPUP
          </span>
        </div>
        <a href="https://t.me/your_telegram" target="_blank" class="text-xs bg-slate-800 hover:bg-slate-700 text-amber-400 border border-slate-700 px-3 py-1.5 rounded-full font-semibold transition">
          💬 Telegram Support
        </a>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-6 flex-1 w-full space-y-6">

      <!-- Banner -->
      <div class="rounded-3xl bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 p-6 shadow-xl relative overflow-hidden">
        <div class="relative z-10">
          <span class="bg-amber-400/20 text-amber-300 border border-amber-400/30 text-xs px-3 py-1 rounded-full font-bold uppercase tracking-wider">
            ស្វ័យប្រវត្តិ ២៤ ម៉ោង
          </span>
          <h1 class="text-2xl sm:text-3xl font-black text-white mt-3">បញ្ចូលពេជ្រហ្គេមរហ័សទាន់ចិត្ត</h1>
          <p class="text-indigo-100 text-xs sm:text-sm mt-1">ស្កេនទូទាត់ប្រាក់តាម Bakong KHQR ពេជ្រចូលភ្លាមៗក្នុងរយៈពេល ៣ វិនាទី</p>
        </div>
      </div>

      <!-- ជ្រើសរើសហ្គេម (Game Tabs) -->
      <div v-if="games.length > 0" class="flex gap-3 overflow-x-auto pb-2 scrollbar-none">
        <button v-for="g in games" :key="g.id"
                @click="selectGame(g)"
                :class="['px-5 py-3 rounded-2xl font-bold text-sm whitespace-nowrap transition-all flex items-center gap-2 border',
                         selectedGame?.id === g.id 
                           ? 'bg-amber-400 text-slate-950 border-amber-400 shadow-md shadow-amber-400/20' 
                           : 'bg-slate-900 text-slate-300 border-slate-800 hover:border-slate-700']">
          🎮 {{ g.name }}
        </button>
      </div>

      <div v-if="selectedGame" class="space-y-6">
        
        <!-- ជំហានទី ១: បញ្ចូល ID -->
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl space-y-3">
          <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-amber-400 text-slate-950 font-black flex items-center justify-center text-xs">1</span>
            <h2 class="font-bold text-slate-200 text-sm">បញ្ចូលព័ត៌មានគណនី (Player Details)</h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div :class="selectedGame.has_zone_id ? 'sm:col-span-2' : 'sm:col-span-3'">
              <input v-model="userId" type="text" placeholder="User ID (ឧ. 12345678)"
                     class="w-full bg-slate-950 border border-slate-700/80 rounded-2xl px-4 py-3 text-sm focus:border-amber-400 outline-none text-white transition placeholder-slate-500" />
            </div>
            <div v-if="selectedGame.has_zone_id">
              <input v-model="zoneId" type="text" placeholder="Zone ID (ឧ. 2001)"
                     class="w-full bg-slate-950 border border-slate-700/80 rounded-2xl px-4 py-3 text-sm focus:border-amber-400 outline-none text-white transition placeholder-slate-500" />
            </div>
          </div>
        </div>

        <!-- ជំហានទី ២: ជ្រើសរើសកញ្ចប់ពេជ្រ -->
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl space-y-3">
          <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-amber-400 text-slate-950 font-black flex items-center justify-center text-xs">2</span>
            <h2 class="font-bold text-slate-200 text-sm">ជ្រើសរើសកញ្ចប់ពេជ្រ (Select Package)</h2>
          </div>

          <div v-if="selectedGame.products.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div v-for="item in selectedGame.products" :key="item.id"
                 @click="selectedProduct = item"
                 :class="['p-4 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between relative',
                          selectedProduct?.id === item.id 
                            ? 'border-amber-400 bg-amber-400/10 shadow-lg shadow-amber-400/5 ring-1 ring-amber-400' 
                            : 'border-slate-800 bg-slate-950/70 hover:border-slate-700']">
              <span class="text-sm font-semibold text-slate-200">{{ item.name }}</span>
              <div class="mt-3 flex items-baseline justify-between">
                <span class="text-xs text-slate-400">តម្លៃ</span>
                <span class="text-base font-black text-amber-400">${{ Number(item.selling_price).toFixed(2) }}</span>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6 text-slate-500 text-sm">
            មិនទាន់មានកញ្ចប់ពេជ្រសម្រាប់ហ្គេមនេះនៅឡើយទេ
          </div>
        </div>

        <!-- ប៊ូតុងទិញ -->
        <button @click="handleBuyNow"
                :disabled="!selectedProduct || !userId || (selectedGame.has_zone_id && !zoneId) || isSubmitting"
                class="w-full py-4 bg-gradient-to-r from-amber-400 to-yellow-500 hover:from-amber-300 hover:to-yellow-400 text-slate-950 font-black text-base rounded-2xl shadow-xl shadow-amber-400/10 transition-transform active:scale-[0.99] disabled:opacity-40 disabled:cursor-not-allowed">
          {{ isSubmitting ? 'កំពុងដំណើរការ...' : 'ទិញឥឡូវនេះ (Buy Now)' }}
        </button>

      </div>
    </main>

    <!-- Modal ស្កេន KHQR (SPA Real-time) -->
    <div v-if="showModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl max-w-sm w-full text-center space-y-4 shadow-2xl relative">
        
        <!-- PENDING -->
        <div v-if="orderStatus === 'PENDING'" class="space-y-4">
          <h3 class="font-bold text-lg text-white">ស្កេនទូទាត់ប្រាក់ (KHQR)</h3>
          <div class="bg-white p-3 rounded-2xl inline-block shadow-inner">
            <img v-if="qrInfo?.qr_image" :src="qrInfo.qr_image" alt="Bakong KHQR" class="w-48 h-48 mx-auto object-contain" />
            <div v-else class="w-48 h-48 flex items-center justify-center text-slate-800 text-xs">កំពុងបង្កើត QR...</div>
          </div>
          <div class="text-xs text-slate-400 space-y-1">
            <p>ចំនួនទឹកប្រាក់: <strong class="text-amber-400">${{ qrInfo?.amount }}</strong></p>
            <p>បើក App ធនាគារ (ABA, Wing, Bakong) ដើម្បីស្កេន</p>
          </div>
          <div class="flex items-center justify-center gap-2 text-amber-400 text-xs animate-pulse">
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
            កំពុងរង់ចាំការទូទាត់ប្រាក់...
          </div>
          <button @click="closeModal" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold">បោះបង់ (Cancel)</button>
        </div>

        <!-- COMPLETED -->
        <div v-else-if="orderStatus === 'COMPLETED'" class="space-y-4 py-2">
          <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-full flex items-center justify-center mx-auto text-3xl font-black">✓</div>
          <h3 class="text-xl font-bold text-white">ជោគជ័យ!</h3>
          <p class="text-sm text-slate-300">ពេជ្របានបញ្ចូលទៅកាន់គណនីរបស់អ្នករួចរាល់ហើយ។</p>
          <button @click="closeModal" class="w-full py-3 bg-amber-400 text-slate-950 font-bold rounded-xl text-sm">ទិញម្តងទៀត</button>
        </div>

        <!-- FAILED -->
        <div v-else-if="orderStatus === 'FAILED'" class="space-y-4 py-2">
          <div class="w-16 h-16 bg-red-500/20 text-red-400 border border-red-500/30 rounded-full flex items-center justify-center mx-auto text-3xl font-black">✕</div>
          <h3 class="text-xl font-bold text-white">ការទូទាត់មានបញ្ហា</h3>
          <p class="text-xs text-slate-300">សូមទាក់ទងមកកាន់ក្រុមការងារ Telegram ដោយភ្ជាប់ Order Number: {{ qrInfo?.order_number }}</p>
          <button @click="closeModal" class="w-full py-3 bg-slate-800 text-white font-bold rounded-xl text-sm">បិទ</button>
        </div>

      </div>
    </div>
  </div>
</template>