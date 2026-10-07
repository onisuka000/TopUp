<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '', status: 'all' }),
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, completed: 0, pending: 0, failed: 0 }),
    },
});

const page = usePage();
const permissions = page.props.auth?.can || {};

// Search & Filter state
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const copiedOrderId = ref(null);

const applyFilter = () => {
    router.get(
        route('admin.orders.index'),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    );
};

// Currency Formatter
const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(val || 0);
};

// Copy Order Number
const copyOrderNumber = (orderNumber) => {
    navigator.clipboard.writeText(orderNumber);
    copiedOrderId.value = orderNumber;
    setTimeout(() => {
        if (copiedOrderId.value === orderNumber) {
            copiedOrderId.value = null;
        }
    }, 2000);
};

// Status Badge Helpers
const getStatusBadgeClass = (s) => {
    const statusUpper = (s || '').toUpperCase();
    if (statusUpper === 'COMPLETED' || statusUpper === 'PAID') {
        return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
    }
    if (statusUpper === 'PENDING') {
        return 'bg-amber-500/10 text-amber-400 border border-amber-500/30 animate-pulse';
    }
    if (statusUpper === 'FAILED') {
        return 'bg-rose-500/10 text-rose-400 border border-rose-500/30';
    }
    return 'bg-slate-700/50 text-slate-300 border border-slate-600/50';
};

// Order Details / Edit Status Modal
const isDetailsModalOpen = ref(false);
const activeOrder = ref(null);

const statusForm = useForm({
    status: 'PENDING',
    error_message: '',
});

const openDetailsModal = (order) => {
    activeOrder.value = order;
    statusForm.reset();
    statusForm.clearErrors();
    statusForm.status = order.status;
    statusForm.error_message = order.error_message || '';
    isDetailsModalOpen.value = true;
};

const updateOrderStatus = () => {
    if (!activeOrder.value) return;
    statusForm.patch(route('admin.orders.update-status', activeOrder.value.id), {
        onSuccess: () => {
            isDetailsModalOpen.value = false;
        },
    });
};

const isExecutingProvider = ref(false);

const retryTokovoucherTopUp = () => {
    if (!activeOrder.value || isExecutingProvider.value) return;
    if (!confirm(`Are you sure you want to trigger Tokovoucher top-up for Order #${activeOrder.value.order_number}?`)) return;

    isExecutingProvider.value = true;
    router.post(route('admin.orders.retry-tokovoucher', activeOrder.value.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isExecutingProvider.value = false;
            isDetailsModalOpen.value = false;
        },
    });
};

const checkTokovoucherStatus = () => {
    if (!activeOrder.value || isExecutingProvider.value) return;

    isExecutingProvider.value = true;
    router.post(route('admin.orders.check-tokovoucher', activeOrder.value.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isExecutingProvider.value = false;
            isDetailsModalOpen.value = false;
        },
    });
};

// Delete Order Modal
const isDeleteModalOpen = ref(false);
const orderToDelete = ref(null);

const confirmDelete = (order) => {
    orderToDelete.value = order;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!orderToDelete.value) return;
    router.delete(route('admin.orders.destroy', orderToDelete.value.id), {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            orderToDelete.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout title="Orders Management">
        <!-- Top Title & Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                    <span>🛒 Customer Orders</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono font-bold">
                        {{ stats.total }} Total
                    </span>
                </h1>
                <p class="text-slate-400 text-xs mt-1">
                    Process incoming top-ups, monitor payment states, and inspect supplier provider tokens.
                </p>
            </div>
        </div>

        <!-- 4 KPI Stat Counters -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Orders</span>
                <div class="text-2xl font-black text-white mt-1">{{ stats.total }}</div>
            </div>
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-400">Completed / Paid</span>
                <div class="text-2xl font-black text-emerald-400 mt-1">{{ stats.completed }}</div>
            </div>
            <div
                class="bg-slate-900/80 border rounded-xl p-4"
                :class="stats.pending > 0 ? 'border-amber-500/40 bg-amber-500/[0.02]' : 'border-slate-800'"
            >
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-400">Pending Queue</span>
                <div class="text-2xl font-black text-amber-400 mt-1">{{ stats.pending }}</div>
            </div>
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-400">Failed / Rejected</span>
                <div class="text-2xl font-black text-rose-400 mt-1">{{ stats.failed }}</div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 shadow-xl">
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
                    placeholder="Search by order #, game user ID, zone ID..."
                    class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-4 py-2 pl-10 text-xs focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition placeholder-slate-500 font-mono"
                />
            </div>

            <!-- Status Tabs & Button -->
            <div class="flex items-center gap-3">
                <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800">
                    <button
                        @click="status = 'all'; applyFilter()"
                        :class="[
                            'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'all' ? 'bg-amber-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        All
                    </button>
                    <button
                        @click="status = 'pending'; applyFilter()"
                        :class="[
                            'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'pending' ? 'bg-amber-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Pending ({{ stats.pending }})
                    </button>
                    <button
                        @click="status = 'completed'; applyFilter()"
                        :class="[
                            'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'completed' ? 'bg-emerald-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Completed
                    </button>
                    <button
                        @click="status = 'failed'; applyFilter()"
                        :class="[
                            'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'failed' ? 'bg-rose-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Failed
                    </button>
                </div>

                <button
                    @click="applyFilter"
                    class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition"
                >
                    Filter
                </button>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950/70 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-4">Order ID</th>
                            <th class="py-3.5 px-4">Item & Game</th>
                            <th class="py-3.5 px-4">Player Details</th>
                            <th class="py-3.5 px-4">Amount</th>
                            <th class="py-3.5 px-4">Method</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">Date</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-200">
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="hover:bg-slate-800/40 transition group"
                        >
                            <!-- Order ID with Copy -->
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-300">
                                <div class="flex items-center gap-1.5">
                                    <span>#{{ order.order_number }}</span>
                                    <button
                                        @click="copyOrderNumber(order.order_number)"
                                        class="opacity-0 group-hover:opacity-100 text-slate-500 hover:text-amber-400 transition"
                                        title="Copy Order ID"
                                    >
                                        <svg v-if="copiedOrderId !== order.order_number" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <span v-else class="text-[10px] text-emerald-400 font-bold">✓</span>
                                    </button>
                                </div>
                            </td>

                            <!-- Item & Game -->
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-white">{{ order.product?.name || 'Custom TopUp' }}</div>
                                <div class="text-[10px] text-amber-400">{{ order.product?.game?.name || 'Game' }}</div>
                            </td>

                            <!-- Player Details -->
                            <td class="py-3.5 px-4 font-mono text-slate-300">
                                <div>User: <span class="text-amber-300 font-bold">{{ order.game_user_id }}</span></div>
                                <div v-if="order.zone_id" class="text-[10px] text-slate-500">Zone: {{ order.zone_id }}</div>
                            </td>

                            <!-- Amount -->
                            <td class="py-3.5 px-4 font-mono font-bold text-white text-sm">
                                {{ formatCurrency(order.amount) }}
                            </td>

                            <!-- Method -->
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-[10px] font-mono text-slate-300">
                                    {{ order.payment_method }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4">
                                <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold inline-block', getStatusBadgeClass(order.status)]">
                                    {{ order.status }}
                                </span>
                            </td>

                            <!-- Date -->
                            <td class="py-3.5 px-4 text-slate-400 text-[11px] whitespace-nowrap font-mono">
                                {{ new Date(order.created_at).toLocaleDateString() }} {{ new Date(order.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right space-x-1.5">
                                <button
                                    @click="openDetailsModal(order)"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition font-medium text-[11px]"
                                >
                                    Details
                                </button>
                                <button
                                    v-if="permissions.delete_order"
                                    @click="confirmDelete(order)"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition font-medium text-[11px] border border-rose-500/20"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="orders.data.length === 0">
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-3xl mb-2">🛒</span>
                                    <span class="text-sm font-medium">No order records match the criteria</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="orders.links && orders.links.length > 3"
                class="p-4 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs"
            >
                <div class="text-slate-400">
                    Showing {{ orders.from || 0 }} to {{ orders.to || 0 }} of {{ orders.total }} orders
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-for="(link, idx) in orders.links"
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

        <!-- ================= ORDER DETAILS & STATUS UPDATE MODAL ================= -->
        <div
            v-if="isDetailsModalOpen && activeOrder"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 shadow-2xl relative space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>Order #{{ activeOrder.order_number }}</span>
                        </h2>
                        <span class="text-[11px] text-slate-400 font-mono">ID: {{ activeOrder.id }}</span>
                    </div>
                    <button @click="isDetailsModalOpen = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">Game Title</span>
                        <span class="font-bold text-white text-sm">{{ activeOrder.product?.game?.name || 'N/A' }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">Package</span>
                        <span class="font-bold text-amber-300 text-sm">{{ activeOrder.product?.name || 'N/A' }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">Player Game ID</span>
                        <span class="font-bold text-slate-100 font-mono text-sm">{{ activeOrder.game_user_id }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">Zone / Server ID</span>
                        <span class="font-bold text-slate-100 font-mono text-sm">{{ activeOrder.zone_id || 'None' }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">Amount</span>
                        <span class="font-bold text-emerald-400 font-mono text-sm">{{ formatCurrency(activeOrder.amount) }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] uppercase text-slate-500 block font-bold">Payment Method</span>
                        <span class="font-bold text-slate-200 font-mono text-sm">{{ activeOrder.payment_method }}</span>
                    </div>
                </div>

                <!-- Provider Reference ID -->
                <div v-if="activeOrder.provider_ref_id" class="p-3 rounded-xl bg-slate-950 border border-slate-800 text-xs">
                    <span class="text-[10px] uppercase text-slate-500 block font-bold">Supplier Provider Ref</span>
                    <span class="font-mono text-slate-300">{{ activeOrder.provider_ref_id }}</span>
                </div>

                <!-- Tokovoucher Actions -->
                <div v-if="permissions.edit_order" class="p-3.5 rounded-xl bg-slate-950/70 border border-amber-500/30 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 font-mono flex items-center gap-1.5">
                            <span>⚡</span> TOKOVOUCHER GATEWAY
                        </span>
                        <span v-if="activeOrder.product?.provider_code" class="text-[10px] font-mono text-slate-400">
                            SKU: <strong class="text-white">{{ activeOrder.product.provider_code }}</strong>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="retryTokovoucherTopUp"
                            :disabled="isExecutingProvider"
                            class="flex-1 py-2 px-3 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-[11px] font-bold transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                        >
                            <span>🚀</span>
                            <span>{{ isExecutingProvider ? 'Processing...' : 'Execute Top-Up' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="checkTokovoucherStatus"
                            :disabled="isExecutingProvider"
                            class="py-2 px-3 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-semibold transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                        >
                            <span>🔄</span>
                            <span>Check Status</span>
                        </button>
                    </div>
                </div>

                <!-- Status Update Form -->
                <form v-if="permissions.edit_order" @submit.prevent="updateOrderStatus" class="space-y-3 pt-2 border-t border-slate-800">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Update Order Status
                        </label>
                        <select
                            v-model="statusForm.status"
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                        >
                            <option value="PENDING">PENDING (In Processing Queue)</option>
                            <option value="COMPLETED">COMPLETED (Diamonds Delivered)</option>
                            <option value="PAID">PAID (Payment Captured)</option>
                            <option value="FAILED">FAILED (Rejected / Canceled)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Remark / Error Message (Optional)
                        </label>
                        <textarea
                            v-model="statusForm.error_message"
                            rows="2"
                            placeholder="Reason for failure or internal processing notes..."
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-amber-400 transition"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="isDetailsModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
                        >
                            Close
                        </button>
                        <button
                            type="submit"
                            :disabled="statusForm.processing"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 text-slate-950 text-xs font-bold shadow-lg shadow-amber-500/20"
                        >
                            Update Status
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
                    Delete Order #{{ orderToDelete?.order_number }}?
                </h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    This will permanently delete this transaction record.
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
