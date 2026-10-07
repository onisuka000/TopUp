<script setup>
import { ref, computed } from 'vue';
import { useForm, router, usePage, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    games: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', game_id: '', status: 'all' }),
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, inactive: 0 }),
    },
    idrRate: {
        type: Number,
        default: 16000,
    },
    khrRate: {
        type: Number,
        default: 4000,
    },
});

const page = usePage();
const permissions = page.props.auth?.can || {};

// Search & Filter state
const search = ref(props.filters.search || '');
const selectedGameId = ref(props.filters.game_id || '');
const status = ref(props.filters.status || 'all');

const applyFilter = () => {
    router.get(
        route('admin.products.index'),
        {
            search: search.value,
            game_id: selectedGameId.value,
            status: status.value,
        },
        { preserveState: true, replace: true }
    );
};

// Currency Formatters
const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(val || 0);
};

const formatKhr = (val) => {
    const khr = Math.round((Number(val) || 0) * (props.khrRate || 4000));
    return new Intl.NumberFormat('en-US').format(khr) + ' ៛';
};

const formatIdr = (val) => {
    return new Intl.NumberFormat('en-US').format(Math.round(Number(val) || 0));
};

// Modal State
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingProductId = ref(null);

const form = useForm({
    game_id: '',
    name: '',
    provider_code: '',
    tokovoucher_price: '',
    cost_price: '',
    selling_price: '',
    is_active: true,
});

// Auto-conversions between Tokovoucher IDR and USD Cost Price
const onTokovoucherPriceInput = (e) => {
    const val = parseFloat(e.target.value);
    const rate = props.idrRate || 16000;
    if (!isNaN(val) && val > 0 && rate > 0) {
        form.cost_price = Number((val / rate).toFixed(2));
    }
};

const onCostPriceInput = (e) => {
    const val = parseFloat(e.target.value);
    const rate = props.idrRate || 16000;
    if (!isNaN(val) && val > 0) {
        form.tokovoucher_price = Math.round(val * rate);
    }
};

// Live margin calculation in modal
const liveProfit = computed(() => {
    const cost = parseFloat(form.cost_price) || 0;
    const sell = parseFloat(form.selling_price) || 0;
    return sell - cost;
});

const liveMarginPercent = computed(() => {
    const cost = parseFloat(form.cost_price) || 0;
    const sell = parseFloat(form.selling_price) || 0;
    if (cost <= 0) return 0;
    return Math.round(((sell - cost) / cost) * 100);
});

const openCreateModal = () => {
    isEditing.value = false;
    editingProductId.value = null;
    form.reset();
    form.clearErrors();
    form.game_id = props.games.length > 0 ? props.games[0].id : '';
    form.tokovoucher_price = '';
    form.cost_price = '';
    form.selling_price = '';
    form.is_active = true;
    isModalOpen.value = true;
};

const openEditModal = (product) => {
    isEditing.value = true;
    editingProductId.value = product.id;
    form.reset();
    form.clearErrors();
    form.game_id = product.game_id;
    form.name = product.name;
    form.provider_code = product.provider_code;
    form.tokovoucher_price = product.tokovoucher_price ?? (product.cost_price ? Math.round(product.cost_price * (props.idrRate || 16000)) : '');
    form.cost_price = product.cost_price;
    form.selling_price = product.selling_price;
    form.is_active = Boolean(product.is_active);
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('admin.products.update', editingProductId.value), {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    } else {
        form.post(route('admin.products.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    }
};

// Live Sync from Tokovoucher catalog
const isSyncing = ref(false);
const syncTokovoucherCosts = () => {
    if (isSyncing.value) return;
    isSyncing.value = true;
    router.post(route('admin.products.sync-tokovoucher'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncing.value = false;
        },
    });
};

// Toggle status
const toggleStatus = (product) => {
    router.patch(route('admin.products.toggle', product.id), {}, { preserveScroll: true });
};

// Delete confirmation modal
const isDeleteModalOpen = ref(false);
const productToDelete = ref(null);

const confirmDelete = (product) => {
    productToDelete.value = product;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!productToDelete.value) return;
    router.delete(route('admin.products.destroy', productToDelete.value.id), {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            productToDelete.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout title="Products Management">
        <!-- Top Title & Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                    <span>💎 Product Packages</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono font-bold">
                        {{ stats.total }} SKUs
                    </span>
                </h1>
                <div class="flex flex-wrap items-center gap-3 text-slate-400 text-xs mt-1">
                    <span>Cost price calculated from Tokovoucher IDR ÷ Exchange Rate.</span>
                    <span class="hidden sm:inline text-slate-600">•</span>
                    <Link
                        :href="route('admin.exchange-rates.index')"
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 text-[11px] font-mono border border-amber-500/20 transition"
                        title="Configure Currency Exchange Rates"
                    >
                        <span>Rates: 1$ = {{ formatIdr(props.idrRate) }} IDR | 1$ = {{ formatIdr(props.khrRate) }} ៛</span>
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <button
                    v-if="permissions.edit_product"
                    @click="syncTokovoucherCosts"
                    :disabled="isSyncing"
                    class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-500/30 text-xs font-bold transition disabled:opacity-50 active:scale-95 shadow-sm"
                    title="Fetch and update cost prices for all SKUs from Tokovoucher live catalog"
                >
                    <svg
                        class="w-4 h-4"
                        :class="{ 'animate-spin': isSyncing }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>{{ isSyncing ? 'Syncing...' : 'Sync Tokovoucher Costs' }}</span>
                </button>

                <button
                    v-if="permissions.create_product"
                    @click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 hover:to-yellow-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition transform active:scale-95"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Product Package</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 shadow-xl">
            <!-- Game Dropdown Filter -->
            <div class="w-full md:w-56">
                <select
                    v-model="selectedGameId"
                    @change="applyFilter"
                    class="w-full bg-slate-950 border border-slate-800 text-slate-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-amber-400 transition"
                >
                    <option value="">All Game Categories</option>
                    <option v-for="g in games" :key="g.id" :value="g.id">
                        {{ g.name }}
                    </option>
                </select>
            </div>

            <!-- Search Input -->
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    v-model="search"
                    @keyup.enter="applyFilter"
                    type="text"
                    placeholder="Search package name, provider code..."
                    class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-4 py-2 pl-10 text-xs focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition placeholder-slate-500"
                />
            </div>

            <!-- Status Tabs & Button -->
            <div class="flex items-center gap-3">
                <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800">
                    <button
                        @click="status = 'all'; applyFilter()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'all' ? 'bg-amber-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        All ({{ stats.total }})
                    </button>
                    <button
                        @click="status = 'active'; applyFilter()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'active' ? 'bg-emerald-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Active ({{ stats.active }})
                    </button>
                    <button
                        @click="status = 'inactive'; applyFilter()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'inactive' ? 'bg-rose-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Inactive ({{ stats.inactive }})
                    </button>
                </div>

                <button
                    @click="applyFilter"
                    class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition"
                >
                    Apply
                </button>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950/70 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-4">Game</th>
                            <th class="py-3.5 px-4">Package Name</th>
                            <th class="py-3.5 px-4">Provider Code</th>
                            <th class="py-3.5 px-4">Cost Price</th>
                            <th class="py-3.5 px-4">Selling Price</th>
                            <th class="py-3.5 px-4">Margin</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-200">
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="hover:bg-slate-800/40 transition group"
                        >
                            <!-- Game Title -->
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-amber-300">
                                    {{ product.game?.name || 'Unassigned' }}
                                </span>
                            </td>

                            <!-- Package Name -->
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-white text-sm">{{ product.name }}</div>
                                <div class="text-[10px] text-slate-500 font-mono">ID: #{{ product.id }}</div>
                            </td>

                            <!-- Provider Code -->
                            <td class="py-3.5 px-4 font-mono text-slate-400">
                                <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-[11px]">
                                    {{ product.provider_code }}
                                </span>
                            </td>

                            <!-- Cost Price (From Tokovoucher + Exchange Rate) -->
                            <td class="py-3.5 px-4 font-mono">
                                <div class="font-bold text-white text-sm">
                                    {{ formatCurrency(product.cost_price) }}
                                </div>
                                <div class="text-[10px] text-amber-400/90 flex items-center gap-1 mt-0.5">
                                    <span class="text-slate-500 font-sans">Supplier:</span>
                                    <span v-if="product.tokovoucher_price" class="font-semibold">
                                        Rp {{ formatIdr(product.tokovoucher_price) }}
                                    </span>
                                    <span v-else class="text-slate-400">
                                        ≈ Rp {{ formatIdr(product.cost_price * props.idrRate) }}
                                    </span>
                                    <span class="text-slate-500 text-[9px]">(÷{{ formatIdr(props.idrRate) }})</span>
                                </div>
                                <div v-if="product.last_synced_at" class="text-[9px] text-slate-500 mt-0.5">
                                    Synced: {{ new Date(product.last_synced_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                </div>
                            </td>

                            <!-- Selling Price (USD + Customer KHR Equivalent) -->
                            <td class="py-3.5 px-4 font-mono">
                                <div class="font-bold text-emerald-400 text-sm">
                                    {{ formatCurrency(product.selling_price) }}
                                </div>
                                <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                                    <span class="text-slate-500 font-sans">Customer:</span>
                                    <span class="text-emerald-300 font-medium">
                                        {{ formatKhr(product.selling_price) }}
                                    </span>
                                </div>
                            </td>

                            <!-- Margin / Profit -->
                            <td class="py-3.5 px-4 font-mono">
                                <div class="flex items-center gap-1.5">
                                    <span :class="product.selling_price >= product.cost_price ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'">
                                        +{{ formatCurrency(product.selling_price - product.cost_price) }}
                                    </span>
                                    <span
                                        v-if="product.cost_price > 0"
                                        class="text-[10px] px-1.5 py-0.2 rounded bg-slate-800 text-slate-400"
                                    >
                                        {{ Math.round(((product.selling_price - product.cost_price) / product.cost_price) * 100) }}%
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    +{{ formatKhr(product.selling_price - product.cost_price) }}
                                </div>
                            </td>

                            <!-- Status Toggle -->
                            <td class="py-3.5 px-4">
                                <button
                                    v-if="permissions.edit_product"
                                    @click="toggleStatus(product)"
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-bold border transition flex items-center gap-1.5',
                                        product.is_active
                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20'
                                            : 'bg-rose-500/10 text-rose-400 border-rose-500/30 hover:bg-rose-500/20'
                                    ]"
                                >
                                    <span :class="['w-1.5 h-1.5 rounded-full', product.is_active ? 'bg-emerald-400' : 'bg-rose-400']"></span>
                                    <span>{{ product.is_active ? 'Active' : 'Inactive' }}</span>
                                </button>
                                <span
                                    v-else
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-bold border inline-block',
                                        product.is_active
                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                                            : 'bg-rose-500/10 text-rose-400 border-rose-500/30'
                                    ]"
                                >
                                    {{ product.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right space-x-1.5">
                                <button
                                    v-if="permissions.edit_product"
                                    @click="openEditModal(product)"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition font-medium text-[11px]"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="permissions.delete_product"
                                    @click="confirmDelete(product)"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition font-medium text-[11px] border border-rose-500/20"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="products.data.length === 0">
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-3xl mb-2">💎</span>
                                    <span class="text-sm font-medium">No products found</span>
                                    <span class="text-xs text-slate-600 mt-1">Try adjusting your filters or add a new top-up package.</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="products.links && products.links.length > 3"
                class="p-4 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs"
            >
                <div class="text-slate-400">
                    Showing {{ products.from || 0 }} to {{ products.to || 0 }} of {{ products.total }} products
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-for="(link, idx) in products.links"
                        :key="idx"
                        @click="link.url && router.get(link.url)"
                        :disabled="!link.url"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                            link.active
                                ? 'bg-amber-500 text-slate-950'
                                : 'bg-slate-900 border border-slate-800 text-slate-300 hover:bg-slate-800',
                            !link.url && 'opacity-30 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- ================= CREATE / EDIT MODAL ================= -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 shadow-2xl relative space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>{{ isEditing ? '✏️ Edit Product Package' : '💎 Add New Package' }}</span>
                    </h2>
                    <button @click="isModalOpen = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Target Game -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Game *
                        </label>
                        <select
                            v-model="form.game_id"
                            required
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                            :class="{ 'border-rose-500': form.errors.game_id }"
                        >
                            <option value="" disabled>Select game category</option>
                            <option v-for="g in games" :key="g.id" :value="g.id">
                                {{ g.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.game_id" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.game_id }}</p>
                    </div>

                    <!-- Package Name -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Package Name *
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. 277 Diamonds (+28 Bonus)"
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                            :class="{ 'border-rose-500': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.name }}</p>
                    </div>

                    <!-- Provider Code -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Provider Code / SKU *
                        </label>
                        <input
                            v-model="form.provider_code"
                            type="text"
                            required
                            placeholder="e.g. MLBB_277"
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition font-mono"
                            :class="{ 'border-rose-500': form.errors.provider_code }"
                        />
                        <p v-if="form.errors.provider_code" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.provider_code }}</p>
                    </div>

                    <!-- Tokovoucher IDR Supplier Price -->
                    <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold uppercase tracking-wider text-amber-300">
                                Tokovoucher Price (IDR Rp)
                            </label>
                            <span class="text-[10px] text-slate-400 font-mono">
                                Rate: 1$ = {{ formatIdr(props.idrRate) }} IDR
                            </span>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-xs font-mono font-bold">
                                Rp
                            </div>
                            <input
                                v-model="form.tokovoucher_price"
                                @input="onTokovoucherPriceInput"
                                type="number"
                                step="1"
                                min="0"
                                placeholder="e.g. 17280"
                                class="w-full bg-slate-900 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 pl-10 text-xs focus:outline-none focus:border-amber-400 transition font-mono"
                                :class="{ 'border-rose-500': form.errors.tokovoucher_price }"
                            />
                        </div>
                        <p class="text-[10px] text-slate-500">
                            Entering Tokovoucher IDR calculates Cost Price ($USD) automatically: <span class="font-mono text-slate-400">Rp ÷ {{ formatIdr(props.idrRate) }}</span>
                        </p>
                    </div>

                    <!-- Prices Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Cost Price -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                Cost Price ($ USD) *
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 text-xs font-mono font-bold">
                                    $
                                </div>
                                <input
                                    v-model="form.cost_price"
                                    @input="onCostPriceInput"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    placeholder="0.00"
                                    class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 pl-7 text-xs focus:outline-none focus:border-amber-400 transition font-mono"
                                    :class="{ 'border-rose-500': form.errors.cost_price }"
                                />
                            </div>
                            <p v-if="form.errors.cost_price" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.cost_price }}</p>
                            <p v-else class="text-[10px] text-slate-500 mt-1 font-mono">
                                ≈ Rp {{ formatIdr(Number(form.cost_price || 0) * (props.idrRate || 16000)) }}
                            </p>
                        </div>

                        <!-- Selling Price -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                Selling Price ($ USD) *
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 text-xs font-mono font-bold">
                                    $
                                </div>
                                <input
                                    v-model="form.selling_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    placeholder="0.00"
                                    class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 pl-7 text-xs focus:outline-none focus:border-amber-400 transition font-mono"
                                    :class="{ 'border-rose-500': form.errors.selling_price }"
                                />
                            </div>
                            <p v-if="form.errors.selling_price" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.selling_price }}</p>
                            <p v-else class="text-[10px] text-emerald-400 mt-1 font-mono">
                                ≈ {{ formatKhr(form.selling_price || 0) }}
                            </p>
                        </div>
                    </div>

                    <!-- Live Profit & Markup Preview Banner -->
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between text-xs">
                        <span class="text-slate-400">Profit Margin:</span>
                        <div class="font-mono flex items-center gap-2">
                            <span :class="liveProfit >= 0 ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'">
                                {{ formatCurrency(liveProfit) }} ({{ formatKhr(liveProfit) }})
                            </span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-300">
                                {{ liveMarginPercent }}% Markup
                            </span>
                        </div>
                    </div>

                    <!-- Is Active Switch -->
                    <label class="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center gap-2.5 cursor-pointer hover:border-slate-700">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-amber-400"
                        />
                        <div>
                            <span class="text-xs font-bold text-slate-200 block">Package Active</span>
                            <span class="text-[10px] text-slate-500 block">Customers can purchase this item on store</span>
                        </div>
                    </label>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 text-slate-950 text-xs font-bold shadow-lg shadow-amber-500/20"
                        >
                            {{ isEditing ? 'Save Changes' : 'Create Package' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= DELETE CONFIRMATION MODAL ================= -->
        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 flex items-center justify-center text-xl mb-2">
                    ⚠️
                </div>
                <h3 class="text-base font-bold text-white">
                    Delete '{{ productToDelete?.name }}'?
                </h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    This will permanently delete this package SKU from the database.
                </p>
                <div class="flex items-center justify-end gap-3 pt-3">
                    <button
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
                    >
                        Cancel
                    </button>
                    <button
                        @click="executeDelete"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-lg shadow-rose-600/30"
                    >
                        Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
