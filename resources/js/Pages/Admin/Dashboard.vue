<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    weekly_trends: {
        type: Array,
        default: () => [],
    },
    recent_orders: {
        type: Array,
        default: () => [],
    },
    top_games: {
        type: Array,
        default: () => [],
    },
    top_products: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    user: {
        type: Object,
        required: true,
    },
});

// UI State
const selectedFilter = ref('ALL');
const copiedOrderId = ref(null);

// Formatters
const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(val || 0);
};

// Filtered Orders
const filteredOrders = computed(() => {
    if (selectedFilter.value === 'ALL') {
        return props.recent_orders;
    }
    return props.recent_orders.filter((order) => {
        if (selectedFilter.value === 'COMPLETED') {
            return order.status === 'COMPLETED' || order.status === 'PAID';
        }
        return order.status === selectedFilter.value;
    });
});

// Weekly Trends Calculations for Bar Heights
const maxWeeklyRevenue = computed(() => {
    const max = Math.max(...props.weekly_trends.map((t) => t.revenue || 0), 1);
    return max <= 0 ? 1 : max;
});

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
const getStatusBadgeClass = (status) => {
    const s = (status || '').toUpperCase();
    if (s === 'COMPLETED' || s === 'PAID') {
        return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
    }
    if (s === 'PENDING') {
        return 'bg-amber-500/10 text-amber-400 border border-amber-500/30 animate-pulse';
    }
    if (s === 'FAILED') {
        return 'bg-rose-500/10 text-rose-400 border border-rose-500/30';
    }
    return 'bg-slate-700/50 text-slate-300 border border-slate-600/50';
};
</script>

<template>
    <AdminLayout title="Dashboard">
        <!-- 1. Hero / Welcome Banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-slate-900/95 to-slate-900 border border-slate-800 p-6 sm:p-8 shadow-xl">
            <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Live Payment Gateway
                        </span>
                        <span class="text-xs text-slate-500">•</span>
                        <span class="text-xs text-slate-400 font-mono">
                            Vue 3 + Laravel Pure Architecture
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                        Welcome back, <span class="bg-gradient-to-r from-amber-400 to-yellow-300 bg-clip-text text-transparent">{{ user.username || user.login_name }}</span>!
                    </h1>
                    <p class="text-slate-400 text-sm mt-1 max-w-2xl">
                        Monitor revenue, inspect player top-up orders, and manage game packages.
                    </p>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <Link
                        v-if="permissions.can_manage_games"
                        :href="route('admin.games.index')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 shadow-sm transition"
                    >
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                        <span>Games</span>
                    </Link>

                    <Link
                        v-if="permissions.can_manage_products"
                        :href="route('admin.products.index')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 shadow-sm transition"
                    >
                        <svg class="w-4 h-4 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span>Products</span>
                    </Link>

                    <Link
                        :href="route('admin.orders.index')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 hover:to-yellow-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition transform active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <span>Orders ({{ stats.pending_orders }} Pending)</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- 2. KPI / STATS GRID -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <!-- Card 1: Total Revenue -->
            <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800/90 rounded-2xl p-5 relative overflow-hidden group hover:border-amber-500/40 transition">
                <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl group-hover:bg-amber-500/10 transition"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Revenue</span>
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                        <span class="text-base font-black">$</span>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        {{ formatCurrency(stats.total_revenue) }}
                    </div>
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <span class="text-emerald-400 font-semibold flex items-center gap-0.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                            Today: {{ formatCurrency(stats.today_revenue) }}
                        </span>
                        <span class="text-slate-500">•</span>
                        <span class="text-slate-400">
                            Month: {{ formatCurrency(stats.month_revenue) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Total Orders -->
            <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800/90 rounded-2xl p-5 relative overflow-hidden group hover:border-emerald-500/40 transition">
                <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/5 rounded-full blur-2xl group-hover:bg-emerald-500/10 transition"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Orders</span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        {{ stats.total_orders.toLocaleString() }}
                    </div>
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <span class="text-emerald-400 font-semibold">
                            {{ stats.completed_orders }} Completed
                        </span>
                        <span class="text-slate-500">•</span>
                        <span class="text-slate-400 font-medium">
                            {{ stats.success_rate }}% Success
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card 3: Pending Orders -->
            <div
                class="bg-slate-900/80 backdrop-blur-sm border rounded-2xl p-5 relative overflow-hidden group transition"
                :class="stats.pending_orders > 0 ? 'border-amber-500/40 bg-amber-500/[0.02]' : 'border-slate-800/90 hover:border-slate-700'"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pending Orders</span>
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center"
                        :class="stats.pending_orders > 0 ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30 animate-pulse' : 'bg-slate-800 text-slate-400'"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
                        <span>{{ stats.pending_orders }}</span>
                        <span
                            v-if="stats.pending_orders > 0"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30"
                        >
                            Queue Active
                        </span>
                    </div>
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <Link :href="route('admin.orders.index')" class="text-amber-400 hover:text-amber-300 font-semibold hover:underline flex items-center gap-1">
                            <span>Open Orders</span>
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                        <span class="text-slate-500">•</span>
                        <span class="text-rose-400 font-medium">
                            {{ stats.failed_orders }} Failed
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card 4: Catalog & Games -->
            <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800/90 rounded-2xl p-5 relative overflow-hidden group hover:border-cyan-500/40 transition">
                <div class="absolute top-0 right-0 w-24 h-24 bg-cyan-500/5 rounded-full blur-2xl group-hover:bg-cyan-500/10 transition"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Game Packages</span>
                    <div class="w-9 h-9 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        {{ stats.total_products }} <span class="text-sm font-semibold text-slate-400">SKUs</span>
                    </div>
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <span class="text-cyan-400 font-semibold">
                            {{ stats.active_games }} Active Games
                        </span>
                        <span class="text-slate-500">•</span>
                        <span class="text-slate-400 font-medium">
                            {{ stats.active_products }} Active Items
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. MIDDLE SECTION: 7-DAY REVENUE BAR CHART & SYSTEM OVERVIEW -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: 7-Day Revenue Trend (8 Cols) -->
            <div class="lg:col-span-8 bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-white flex items-center gap-2">
                                <span>📈 Revenue Trend (Past 7 Days)</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Daily sales performance and transaction volumes
                            </p>
                        </div>
                        <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 border border-slate-700">
                            USD ($)
                        </span>
                    </div>

                    <!-- Bar Graph Container -->
                    <div class="h-48 pt-6 pb-2 flex items-end justify-between gap-3 sm:gap-6 border-b border-slate-800/80">
                        <div
                            v-for="(day, index) in weekly_trends"
                            :key="index"
                            class="flex-1 flex flex-col items-center h-full justify-end group relative"
                        >
                            <!-- Hover Tooltip -->
                            <div class="absolute -top-10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none bg-slate-950 border border-slate-700 px-2 py-1 rounded-md text-[10px] text-white shadow-xl whitespace-nowrap z-20">
                                <div class="font-bold text-amber-400">{{ formatCurrency(day.revenue) }}</div>
                                <div class="text-slate-400">{{ day.orders }} orders</div>
                            </div>

                            <!-- Bar Column -->
                            <div class="w-full max-w-[42px] bg-slate-800/60 rounded-t-lg relative overflow-hidden flex items-end h-full">
                                <div
                                    class="w-full bg-gradient-to-t from-amber-600 to-yellow-400 rounded-t-lg transition-all duration-500 group-hover:from-amber-500 group-hover:to-yellow-300"
                                    :style="{
                                        height: day.revenue > 0 ? `${Math.max(12, (day.revenue / maxWeeklyRevenue) * 100)}%` : '4px',
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- X-Axis Labels -->
                    <div class="flex items-center justify-between gap-3 sm:gap-6 pt-2">
                        <div
                            v-for="(day, index) in weekly_trends"
                            :key="index"
                            class="flex-1 text-center"
                        >
                            <span class="text-[11px] font-medium text-slate-400 block truncate">
                                {{ day.day }}
                            </span>
                            <span class="text-[10px] font-mono text-slate-500 block truncate">
                                {{ formatCurrency(day.revenue) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Quick Summary -->
                <div class="mt-6 pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-4 text-xs">
                    <div class="flex items-center gap-4 text-slate-400">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-gradient-to-tr from-amber-600 to-yellow-400"></span>
                            <span>Completed Revenue</span>
                        </div>
                    </div>
                    <Link :href="route('admin.orders.index')" class="text-amber-400 hover:text-amber-300 font-semibold hover:underline flex items-center gap-1">
                        <span>Detailed Orders Management</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Right: Staff Roles & Permissions Summary (4 Cols) -->
            <div class="lg:col-span-4 bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>👥 Admin Team</span>
                        </h2>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-bold">
                            {{ stats.total_users }} Members
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mb-5">
                        Role-based access hierarchy in system:
                    </p>

                    <!-- Role List Breakdown -->
                    <div class="space-y-3">
                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-rose-500/20 text-rose-400 border border-rose-500/30 flex items-center justify-center text-xs font-bold">
                                    👑
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-200">Root Admin</div>
                                    <div class="text-[10px] text-slate-500">Unrestricted Bypass</div>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-300 border border-rose-500/30 font-mono">
                                {{ stats.users_by_type.root }}
                            </span>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center justify-center text-xs font-bold">
                                    ⭐
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-200">Super Senior</div>
                                    <div class="text-[10px] text-slate-500">Full CRUD Controls</div>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-300 border border-amber-500/30 font-mono">
                                {{ stats.users_by_type.super_senior }}
                            </span>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center text-xs font-bold">
                                    🛡️
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-200">Senior Staff</div>
                                    <div class="text-[10px] text-slate-500">View & Edit</div>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-300 border border-cyan-500/30 font-mono">
                                {{ stats.users_by_type.senior }}
                            </span>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-xs font-bold">
                                    👁️
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-200">Junior Staff</div>
                                    <div class="text-[10px] text-slate-500">View-Only Privilege</div>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-500/30 font-mono">
                                {{ stats.users_by_type.junior }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-800">
                    <Link
                        v-if="permissions.can_manage_users"
                        :href="route('admin.users.index')"
                        class="w-full inline-flex items-center justify-center gap-1.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition"
                    >
                        <span>Manage System Users</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                    <div v-else class="text-center text-[11px] text-slate-500 italic">
                        View-only privileges active
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. BOTTOM SECTION: RECENT ORDERS TABLE & POPULAR GAMES -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Recent Orders Table (8 cols) -->
            <div class="lg:col-span-8 bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl shadow-xl overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>🛒 Recent Customer Transactions</span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Real-time order pipeline
                        </p>
                    </div>

                    <!-- Status Filter Tabs -->
                    <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-xl border border-slate-800 self-start sm:self-auto">
                        <button
                            @click="selectedFilter = 'ALL'"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                                selectedFilter === 'ALL'
                                    ? 'bg-amber-500 text-slate-950'
                                    : 'text-slate-400 hover:text-slate-200'
                            ]"
                        >
                            All ({{ recent_orders.length }})
                        </button>
                        <button
                            @click="selectedFilter = 'COMPLETED'"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                                selectedFilter === 'COMPLETED'
                                    ? 'bg-emerald-500 text-slate-950'
                                    : 'text-slate-400 hover:text-slate-200'
                            ]"
                        >
                            Completed
                        </button>
                        <button
                            @click="selectedFilter = 'PENDING'"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                                selectedFilter === 'PENDING'
                                    ? 'bg-amber-500 text-slate-950'
                                    : 'text-slate-400 hover:text-slate-200'
                            ]"
                        >
                            Pending
                        </button>
                        <button
                            @click="selectedFilter = 'FAILED'"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                                selectedFilter === 'FAILED'
                                    ? 'bg-rose-500 text-slate-950'
                                    : 'text-slate-400 hover:text-slate-200'
                            ]"
                        >
                            Failed
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-950/60 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                                <th class="py-3 px-4">Order ID</th>
                                <th class="py-3 px-4">Game & Package</th>
                                <th class="py-3 px-4">Player Details</th>
                                <th class="py-3 px-4">Amount</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Time</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-200">
                            <tr
                                v-for="order in filteredOrders"
                                :key="order.id"
                                class="hover:bg-slate-800/40 transition group"
                            >
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

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white">{{ order.game_name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ order.product_name }}</div>
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-300">
                                    <div class="text-[11px]">ID: <span class="text-amber-300">{{ order.game_user_id }}</span></div>
                                    <div v-if="order.zone_id" class="text-[10px] text-slate-500">Zone: {{ order.zone_id }}</div>
                                </td>

                                <td class="py-3.5 px-4 font-mono font-bold text-white">
                                    {{ formatCurrency(order.amount) }}
                                    <span class="text-[10px] font-normal text-slate-400 block">{{ order.payment_method }}</span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold inline-block', getStatusBadgeClass(order.status)]">
                                        {{ order.status }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-slate-400 text-[11px] whitespace-nowrap">
                                    <div>{{ order.created_at_human }}</div>
                                    <div class="text-[10px] text-slate-500">{{ order.created_at_formatted }}</div>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <Link
                                        :href="route('admin.orders.index')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition font-medium text-[11px]"
                                    >
                                        <span>Inspect</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="filteredOrders.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-10 h-10 text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <span class="text-sm font-medium">No transactions found</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs text-slate-400">
                    <span>Showing {{ filteredOrders.length }} recent orders</span>
                    <Link :href="route('admin.orders.index')" class="text-amber-400 hover:text-amber-300 font-semibold hover:underline flex items-center gap-1">
                        <span>View Orders Ledger</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Top Games & Products (4 cols) -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Top Games Box -->
                <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>🎮 Active Games</span>
                        </h2>
                        <Link :href="route('admin.games.index')" class="text-xs text-amber-400 hover:text-amber-300 font-semibold hover:underline">
                            Manage
                        </Link>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="game in top_games"
                            :key="game.id"
                            class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between hover:border-slate-700 transition"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                                    <img
                                        v-if="game.image"
                                        :src="game.image"
                                        :alt="game.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <span v-else class="text-lg">🎮</span>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-white flex items-center gap-1.5">
                                        <span>{{ game.name }}</span>
                                        <span
                                            v-if="game.is_active"
                                            class="w-1.5 h-1.5 rounded-full bg-emerald-400"
                                            title="Active"
                                        ></span>
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ game.products_count }} packages
                                    </div>
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-xs font-mono font-bold text-amber-400">
                                    {{ formatCurrency(game.revenue) }}
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    {{ game.orders_count }} orders
                                </div>
                            </div>
                        </div>

                        <div v-if="top_games.length === 0" class="text-center py-6 text-slate-500 text-xs">
                            No games configured yet.
                        </div>
                    </div>
                </div>

                <!-- Top Selling Packages Box -->
                <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>💎 Top Product SKUs</span>
                        </h2>
                        <Link :href="route('admin.products.index')" class="text-xs text-amber-400 hover:text-amber-300 font-semibold hover:underline">
                            View All
                        </Link>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="product in top_products"
                            :key="product.id"
                            class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between"
                        >
                            <div>
                                <div class="text-xs font-bold text-white">{{ product.name }}</div>
                                <div class="text-[10px] text-slate-400">{{ product.game_name }}</div>
                            </div>

                            <div class="text-right">
                                <div class="text-xs font-mono font-bold text-emerald-400">
                                    {{ formatCurrency(product.price) }}
                                </div>
                                <div class="text-[10px] text-slate-500 font-mono">
                                    {{ product.orders_count }} sales
                                </div>
                            </div>
                        </div>

                        <div v-if="top_products.length === 0" class="text-center py-6 text-slate-500 text-xs">
                            No active products found.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
