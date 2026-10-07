<script setup>
import { ref, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    currencies: {
        type: Array,
        required: true,
    },
    defaultCurrency: {
        type: Object,
        default: () => ({ currency_code: 'USD', symbol: '$', exchange_rate: 1 }),
    },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.can || {});

// Search query
const searchQuery = ref('');

// Live Currency Converter Sandbox
const testAmount = ref(1.0);
const testFromCode = ref('USD');

const convertedValues = computed(() => {
    const fromCurr = props.currencies.find(
        (c) => c.currency_code === testFromCode.value
    ) || { exchange_rate: 1 };

    const amountInUsd = (Number(testAmount.value) || 0) / (fromCurr.exchange_rate || 1);

    return props.currencies.map((c) => {
        const converted = amountInUsd * (c.exchange_rate || 1);
        return {
            ...c,
            convertedFormatted: new Intl.NumberFormat('en-US', {
                maximumFractionDigits: c.currency_code === 'KHR' || c.currency_code === 'IDR' ? 0 : 2,
            }).format(converted),
        };
    });
});

// Filtered currencies
const filteredCurrencies = computed(() => {
    if (!searchQuery.value.trim()) return props.currencies;
    const q = searchQuery.value.toLowerCase();
    return props.currencies.filter(
        (c) =>
            c.currency.toLowerCase().includes(q) ||
            c.currency_code.toLowerCase().includes(q) ||
            c.symbol.toLowerCase().includes(q)
    );
});

// Modal state
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    currency: '',
    currency_code: '',
    symbol: '',
    exchange_rate: 1.0,
    is_default: false,
    is_active: true,
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.is_default = false;
    form.is_active = true;
    form.exchange_rate = 1.0;
    isModalOpen.value = true;
};

const openEditModal = (curr) => {
    isEditing.value = true;
    editingId.value = curr.id;
    form.reset();
    form.clearErrors();
    form.currency = curr.currency;
    form.currency_code = curr.currency_code;
    form.symbol = curr.symbol;
    form.exchange_rate = curr.exchange_rate;
    form.is_default = Boolean(curr.is_default);
    form.is_active = Boolean(curr.is_active);
    isModalOpen.value = true;
};

const handleSubmit = () => {
    if (isEditing.value) {
        form.put(route('admin.exchange-rates.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    } else {
        form.post(route('admin.exchange-rates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    }
};

// Delete currency
const isDeleteModalOpen = ref(false);
const itemToDelete = ref(null);

const confirmDelete = (curr) => {
    itemToDelete.value = curr;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) return;
    router.delete(route('admin.exchange-rates.destroy', itemToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            itemToDelete.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout title="Currencies & Exchange Rates">
        <div class="space-y-7">
            <!-- ================= TOP HEADER BANNER ================= -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-slate-900/95 to-amber-950/40 border border-slate-800 p-6 shadow-2xl">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-black tracking-wider uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                💱 Multi-Currency System
                            </span>
                            <span class="text-xs text-slate-400 font-mono">
                                Base: <strong class="text-amber-400 font-bold">1 USD ($)</strong>
                            </span>
                        </div>
                        <h1 class="text-2xl lg:text-3xl font-black text-white tracking-tight">
                            Exchange Rates & Currency Settings
                        </h1>
                        <p class="text-xs lg:text-sm text-slate-400 mt-1 max-w-2xl leading-relaxed">
                            Default currency is <strong class="text-amber-400">USD</strong>. Store prices are kept in base currency and dynamically converted to <strong class="text-emerald-400">Khmer Riel (KHR)</strong> for customers and <strong class="text-cyan-400">Indonesian Rupiah (IDR)</strong> for Tokovoucher supplier cost tracking.
                        </p>
                    </div>

                    <button
                        @click="openCreateModal"
                        class="px-4 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-bold text-xs uppercase tracking-wider flex items-center gap-2 transition shadow-lg shadow-amber-500/20 shrink-0"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add New Currency</span>
                    </button>
                </div>
            </div>

            <!-- ================= LIVE CONVERTER SANDBOX ================= -->
            <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 shadow-xl">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
                            🔄
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-100">
                                Live Currency Conversion Calculator
                            </h3>
                            <p class="text-[11px] text-slate-400">
                                Test real-time conversion rates across active currencies
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="relative w-36">
                            <input
                                v-model.number="testAmount"
                                type="number"
                                step="0.5"
                                min="0"
                                class="w-full pl-3 pr-2 py-1.5 rounded-xl bg-slate-950 border border-slate-700 text-xs text-amber-400 font-mono font-bold focus:border-amber-400"
                                placeholder="1.00"
                            />
                        </div>
                        <select
                            v-model="testFromCode"
                            class="px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-700 text-xs text-slate-200 font-bold"
                        >
                            <option
                                v-for="c in currencies"
                                :key="c.id"
                                :value="c.currency_code"
                            >
                                {{ c.currency_code }} ({{ c.symbol }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Preview Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div
                        v-for="item in convertedValues"
                        :key="item.currency_code"
                        class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800/80 hover:border-slate-700 transition"
                    >
                        <div class="flex items-center justify-between text-[11px] text-slate-400 mb-1">
                            <span class="font-bold">{{ item.currency }}</span>
                            <span class="font-mono text-[10px] text-slate-500">
                                {{ item.currency_code }}
                            </span>
                        </div>
                        <div class="text-base font-black text-white font-mono flex items-baseline gap-1">
                            <span class="text-amber-400 text-xs">{{ item.symbol }}</span>
                            <span>{{ item.convertedFormatted }}</span>
                        </div>
                        <div class="text-[10px] text-slate-500 font-mono mt-0.5">
                            Rate: {{ Number(item.exchange_rate).toLocaleString() }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= CURRENCIES & EXCHANGE RATES TABLE ================= -->
            <div class="rounded-2xl bg-slate-900/80 border border-slate-800 overflow-hidden shadow-2xl">
                <!-- Header search & count -->
                <div class="px-6 py-4 border-b border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-base font-bold text-white tracking-wide">
                            Configured Currencies ({{ filteredCurrencies.length }})
                        </h2>
                    </div>

                    <div class="w-64">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search currency, code or symbol..."
                            class="w-full px-3.5 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-200 placeholder-slate-500 focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                        />
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-950/80 text-slate-400 uppercase text-[10px] tracking-wider font-bold border-b border-slate-800">
                            <tr>
                                <th class="py-3.5 px-4">Currency</th>
                                <th class="py-3.5 px-4">Code</th>
                                <th class="py-3.5 px-4 text-center">Symbol</th>
                                <th class="py-3.5 px-4 text-right">Exchange Rate (per 1 USD)</th>
                                <th class="py-3.5 px-4 text-center">Base / Default</th>
                                <th class="py-3.5 px-4 text-center">Status</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-medium">
                            <tr
                                v-for="curr in filteredCurrencies"
                                :key="curr.id"
                                class="hover:bg-slate-800/30 transition group"
                            >
                                <!-- Name -->
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-100 flex items-center gap-2">
                                        <span>{{ curr.currency }}</span>
                                        <span
                                            v-if="curr.currency_code === 'KHR'"
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/20"
                                        >
                                            Storefront Customer
                                        </span>
                                        <span
                                            v-if="curr.currency_code === 'IDR'"
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-cyan-500/10 text-cyan-300 border border-cyan-500/20"
                                        >
                                            Tokovoucher Cost
                                        </span>
                                    </div>
                                </td>

                                <!-- Code -->
                                <td class="py-3.5 px-4 font-mono font-bold text-amber-400">
                                    <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-[11px]">
                                        {{ curr.currency_code }}
                                    </span>
                                </td>

                                <!-- Symbol -->
                                <td class="py-3.5 px-4 text-center font-bold text-lg text-slate-200">
                                    {{ curr.symbol }}
                                </td>

                                <!-- Exchange Rate -->
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-400 text-sm">
                                    {{ Number(curr.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 4 }) }}
                                    <span class="text-[10px] text-slate-500 font-sans ml-1">
                                        {{ curr.symbol }} / $1 USD
                                    </span>
                                </td>

                                <!-- Is Default -->
                                <td class="py-3.5 px-4 text-center">
                                    <span
                                        v-if="curr.is_default"
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/40 shadow-sm"
                                    >
                                        ⭐ Base (1.0)
                                    </span>
                                    <span v-else class="text-slate-600 text-xs">-</span>
                                </td>

                                <!-- Status -->
                                <td class="py-3.5 px-4 text-center">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                            curr.is_active
                                                ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                                : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                        ]"
                                    >
                                        {{ curr.is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="py-3.5 px-4 text-right space-x-1.5">
                                    <button
                                        @click="openEditModal(curr)"
                                        class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition font-semibold text-[11px]"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="!curr.is_default"
                                        @click="confirmDelete(curr)"
                                        class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition font-semibold text-[11px] border border-rose-500/20"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="filteredCurrencies.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    No currencies found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= CREATE / EDIT CURRENCY MODAL ================= -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-6 relative">
                <button
                    @click="isModalOpen = false"
                    class="absolute top-4 right-4 text-slate-400 hover:text-white p-1"
                >
                    ✕
                </button>

                <h3 class="text-lg font-black text-white mb-1">
                    {{ isEditing ? 'Edit Currency & Rate' : 'Add New Currency' }}
                </h3>
                <p class="text-xs text-slate-400 mb-5">
                    Configure currency name, code, symbol, and exchange rate relative to USD.
                </p>

                <form @submit.prevent="handleSubmit" class="space-y-4">
                    <!-- Currency Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            Currency Name
                        </label>
                        <input
                            v-model="form.currency"
                            type="text"
                            placeholder="e.g. Khmer Riel, Indonesian Rupiah"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-200 text-xs focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                            required
                        />
                        <span v-if="form.errors.currency" class="text-rose-400 text-[10px]">
                            {{ form.errors.currency }}
                        </span>
                    </div>

                    <!-- Code & Symbol -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">
                                Currency Code
                            </label>
                            <input
                                v-model="form.currency_code"
                                type="text"
                                placeholder="e.g. KHR, IDR, USD"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-amber-400 font-mono text-xs uppercase focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                                required
                            />
                            <span v-if="form.errors.currency_code" class="text-rose-400 text-[10px]">
                                {{ form.errors.currency_code }}
                            </span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">
                                Symbol
                            </label>
                            <input
                                v-model="form.symbol"
                                type="text"
                                placeholder="e.g. ៛, $, Rp"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-200 font-bold text-xs focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                                required
                            />
                            <span v-if="form.errors.symbol" class="text-rose-400 text-[10px]">
                                {{ form.errors.symbol }}
                            </span>
                        </div>
                    </div>

                    <!-- Exchange Rate -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            Exchange Rate (per 1 USD)
                        </label>
                        <div class="relative">
                            <input
                                v-model.number="form.exchange_rate"
                                type="number"
                                step="any"
                                min="0.0001"
                                class="w-full pl-3.5 pr-14 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-emerald-400 font-mono font-bold text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                                placeholder="4000"
                                required
                            />
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 text-xs font-mono font-bold">
                                {{ form.symbol || 'Rate' }}
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-500 mt-1">
                            Example: For Khmer Riel, enter <code class="text-amber-400">4000</code> (1 USD = 4,000 ៛).
                        </p>
                        <span v-if="form.errors.exchange_rate" class="text-rose-400 text-[10px]">
                            {{ form.errors.exchange_rate }}
                        </span>
                    </div>

                    <!-- Is Active & Is Default -->
                    <div class="space-y-2 pt-1">
                        <label class="flex items-center gap-2.5 text-xs text-slate-300 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded bg-slate-950 border-slate-700 text-amber-500 focus:ring-amber-400"
                            />
                            <span>Active in system</span>
                        </label>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 text-xs font-black uppercase tracking-wider shadow-lg shadow-amber-500/20 transition disabled:opacity-50"
                        >
                            {{ isEditing ? 'Save Changes' : 'Create Currency' }}
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
            <div class="w-full max-w-sm rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center mx-auto mb-3 text-xl font-bold">
                    ⚠️
                </div>
                <h3 class="text-base font-bold text-white mb-1">
                    Delete {{ itemToDelete?.currency }}?
                </h3>
                <p class="text-xs text-slate-400 mb-5">
                    Are you sure you want to remove {{ itemToDelete?.currency_code }}? This action cannot be undone.
                </p>
                <div class="flex items-center justify-center gap-3">
                    <button
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold"
                    >
                        Cancel
                    </button>
                    <button
                        @click="executeDelete"
                        class="px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold shadow-lg shadow-rose-500/20"
                    >
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
