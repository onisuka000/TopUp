<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        default: 'Admin Console',
    },
});

const page = usePage();
const isSidebarOpen = ref(false);

const currentUser = computed(() => page.props.auth?.user || {});
const userPermissions = computed(() => page.props.auth?.can || {});

// Flash Toast State
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const showFlash = ref(false);
const flashMessage = ref('');
const flashType = ref('success');

watch([flashSuccess, flashError], ([newSuccess, newError]) => {
    if (newSuccess) {
        flashMessage.value = newSuccess;
        flashType.value = 'success';
        showFlash.value = true;
        setTimeout(() => { showFlash.value = false; }, 4000);
    } else if (newError) {
        flashMessage.value = newError;
        flashType.value = 'error';
        showFlash.value = true;
        setTimeout(() => { showFlash.value = false; }, 5000);
    }
}, { immediate: true });

const handleLogout = () => {
    router.post(route('logout'));
};

const getRoleBadgeClass = (type) => {
    const t = (type || '').toLowerCase();
    if (t === 'root') {
        return 'bg-rose-500/20 text-rose-300 border border-rose-500/40 shadow-sm shadow-rose-500/20';
    }
    if (t === 'super_senior' || t === 'super senior') {
        return 'bg-amber-500/20 text-amber-300 border border-amber-500/40 shadow-sm shadow-amber-500/20';
    }
    if (t === 'senior') {
        return 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 shadow-sm shadow-cyan-500/20';
    }
    return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-sm shadow-emerald-500/20';
};

const getRoleLabel = (type) => {
    const t = (type || '').toLowerCase();
    if (t === 'root') return '👑 ROOT ADMIN';
    if (t === 'super_senior' || t === 'super senior') return '⭐ SUPER SENIOR';
    if (t === 'senior') return '🛡️ SENIOR STAFF';
    return '👁️ JUNIOR (VIEW ONLY)';
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex font-sans selection:bg-amber-500 selection:text-slate-950">
        <Head :title="`${title} - KHMER TOPUP`" />

        <!-- Ambient Background Glows -->
        <div class="fixed top-0 left-1/3 w-[600px] h-[350px] bg-amber-500/5 rounded-full blur-[140px] pointer-events-none -z-10"></div>
        <div class="fixed top-1/2 right-0 w-[500px] h-[400px] bg-yellow-500/5 rounded-full blur-[150px] pointer-events-none -z-10"></div>
        <div class="fixed bottom-0 left-1/4 w-[600px] h-[400px] bg-blue-500/5 rounded-full blur-[160px] pointer-events-none -z-10"></div>

        <!-- ================= MOBILE BACKDROP ================= -->
        <div
            v-if="isSidebarOpen"
            @click="isSidebarOpen = false"
            class="fixed inset-0 z-40 bg-black/80 backdrop-blur-sm md:hidden transition-opacity"
        ></div>

        <!-- ================= LEFT SIDEBAR ================= -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-slate-950/95 md:bg-slate-950/80 backdrop-blur-2xl border-r border-slate-800/80 flex flex-col transition-transform duration-300 ease-in-out',
                isSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
            ]"
        >
            <!-- Sidebar Header / Logo -->
            <div class="h-16 px-5 border-b border-slate-800/80 flex items-center justify-between shrink-0">
                <Link :href="route('admin.dashboard')" class="flex items-center gap-3 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 p-[1px] shadow-lg shadow-amber-500/20 group-hover:shadow-amber-500/40 transition">
                        <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                            <span class="text-lg group-hover:scale-110 transition-transform">⚡</span>
                        </div>
                    </div>
                    <div>
                        <span class="font-black text-base tracking-wider bg-gradient-to-r from-amber-400 via-amber-300 to-yellow-500 bg-clip-text text-transparent block">
                            KHMER TOPUP
                        </span>
                        <span class="text-[9px] uppercase tracking-widest text-slate-400 font-bold block -mt-0.5">
                            Admin Portal
                        </span>
                    </div>
                </Link>

                <!-- Mobile Close Button -->
                <button
                    @click="isSidebarOpen = false"
                    class="md:hidden text-slate-400 hover:text-white p-1"
                >
                    ✕
                </button>
            </div>

            <!-- Sidebar Navigation Links -->
            <div class="flex-1 overflow-y-auto px-3 py-5 space-y-6">
                <!-- Section 1: Core Navigation -->
                <div>
                    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Core System
                    </div>
                    <nav class="space-y-1">
                        <!-- Dashboard -->
                        <Link
                            :href="route('admin.dashboard')"
                            :class="[
                                'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition group',
                                route().current('admin.dashboard')
                                    ? 'bg-gradient-to-r from-amber-500/15 to-yellow-500/5 text-amber-400 border border-amber-500/30 shadow-sm shadow-amber-500/10'
                                    : 'text-slate-400 hover:text-slate-100 hover:bg-slate-900/80'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span>Dashboard</span>
                        </Link>

                        <!-- Games -->
                        <Link
                            :href="route('admin.games.index')"
                            :class="[
                                'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition group',
                                route().current('admin.games.*')
                                    ? 'bg-gradient-to-r from-amber-500/15 to-yellow-500/5 text-amber-400 border border-amber-500/30 shadow-sm shadow-amber-500/10'
                                    : 'text-slate-400 hover:text-slate-100 hover:bg-slate-900/80'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                            <span>Games</span>
                        </Link>

                        <!-- Products -->
                        <Link
                            :href="route('admin.products.index')"
                            :class="[
                                'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition group',
                                route().current('admin.products.*')
                                    ? 'bg-gradient-to-r from-amber-500/15 to-yellow-500/5 text-amber-400 border border-amber-500/30 shadow-sm shadow-amber-500/10'
                                    : 'text-slate-400 hover:text-slate-100 hover:bg-slate-900/80'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span>Products</span>
                        </Link>

                        <!-- Orders -->
                        <Link
                            :href="route('admin.orders.index')"
                            :class="[
                                'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition group',
                                route().current('admin.orders.*')
                                    ? 'bg-gradient-to-r from-amber-500/15 to-yellow-500/5 text-amber-400 border border-amber-500/30 shadow-sm shadow-amber-500/10'
                                    : 'text-slate-400 hover:text-slate-100 hover:bg-slate-900/80'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span>Orders</span>
                        </Link>

                        <!-- Exchange Rates -->
                        <Link
                            :href="route('admin.exchange-rates.index')"
                            :class="[
                                'w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition group',
                                route().current('admin.exchange-rates.*')
                                    ? 'bg-gradient-to-r from-amber-500/15 to-yellow-500/5 text-amber-400 border border-amber-500/30 shadow-sm shadow-amber-500/10'
                                    : 'text-slate-400 hover:text-slate-100 hover:bg-slate-900/80'
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Exchange Rates</span>
                            </div>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-amber-500/20 text-amber-300 border border-amber-500/30">KHR / USD</span>
                        </Link>
                    </nav>
                </div>

                <!-- Section 2: Management & Stores -->
                <div>
                    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Management
                    </div>
                    <nav class="space-y-1">
                        <!-- Users -->
                        <Link
                            v-if="userPermissions.manage_users"
                            :href="route('admin.users.index')"
                            :class="[
                                'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition group',
                                route().current('admin.users.*')
                                    ? 'bg-gradient-to-r from-amber-500/15 to-yellow-500/5 text-amber-400 border border-amber-500/30 shadow-sm shadow-amber-500/10'
                                    : 'text-slate-400 hover:text-slate-100 hover:bg-slate-900/80'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span>Staff & Users</span>
                        </Link>

                        <!-- Live Storefront Link -->
                        <Link
                            :href="route('home')"
                            target="_blank"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-emerald-400 hover:bg-slate-900/80 transition"
                        >
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                <span>Live Store</span>
                            </div>
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">
                                OPEN ↗
                            </span>
                        </Link>
                    </nav>
                </div>
            </div>

            <!-- Sidebar Footer / Current User & Sign Out -->
            <div class="p-4 border-t border-slate-800/80 bg-slate-950/70 shrink-0 space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center font-bold text-slate-950 text-xs shadow-md shadow-amber-500/20 shrink-0">
                        {{ (currentUser.username || currentUser.login_name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold text-white truncate">
                            {{ currentUser.username || currentUser.login_name }}
                        </div>
                        <div class="mt-0.5">
                            <span :class="['text-[9px] font-bold px-1.5 py-0.5 rounded-full inline-block truncate max-w-full', getRoleBadgeClass(currentUser.type)]">
                                {{ getRoleLabel(currentUser.type) }}
                            </span>
                        </div>
                    </div>
                </div>

                <button
                    @click="handleLogout"
                    class="w-full flex items-center justify-center gap-2 py-2 rounded-xl text-xs font-semibold text-rose-400 hover:text-white bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Sign Out</span>
                </button>
            </div>
        </aside>

        <!-- ================= MAIN CONTENT WRAPPER ================= -->
        <div class="flex-1 md:pl-64 flex flex-col min-w-0">
            <!-- Top Navbar in Main Content -->
            <header class="sticky top-0 z-30 h-16 bg-slate-950/80 backdrop-blur-xl border-b border-slate-800/80 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                <!-- Left: Hamburger on Mobile + Breadcrumb -->
                <div class="flex items-center gap-3">
                    <button
                        @click="isSidebarOpen = true"
                        class="md:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-900 border border-slate-800"
                        title="Open Menu"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-slate-500 hidden sm:inline">Admin</span>
                        <span class="text-slate-600 hidden sm:inline">/</span>
                        <span class="font-bold text-white tracking-wide">{{ title }}</span>
                    </div>
                </div>

                <!-- Right: Status Pill & Store Link -->
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-sm font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="hidden sm:inline">{{ currentUser.username || currentUser.login_name }}</span>
                    </span>

                    <Link
                        :href="route('home')"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white text-xs font-semibold border border-slate-800 transition shadow-sm"
                    >
                        <span>Store</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </Link>
                </div>
            </header>

            <!-- Flash Toast Notification Banner -->
            <div
                v-if="showFlash"
                class="fixed top-20 right-6 z-50 max-w-md shadow-2xl rounded-2xl p-4 flex items-center gap-3 border transition-all animate-bounce"
                :class="flashType === 'success' ? 'bg-emerald-950/95 border-emerald-500/40 text-emerald-200' : 'bg-rose-950/95 border-rose-500/40 text-rose-200'"
            >
                <div
                    class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                    :class="flashType === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'"
                >
                    <svg v-if="flashType === 'success'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1 text-xs font-semibold">
                    {{ flashMessage }}
                </div>
                <button @click="showFlash = false" class="text-slate-400 hover:text-white p-1">
                    ✕
                </button>
            </div>

            <!-- Page Body -->
            <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-8">
                <slot />
            </main>

            <!-- Bottom Footer -->
            <footer class="mt-auto border-t border-slate-800/80 bg-slate-950 py-5 text-center text-xs text-slate-500">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-400">KHMER TOPUP</span>
                    </div>
                    <div>
                        Logged in as <span class="text-slate-300 font-mono font-bold">{{ currentUser.username || currentUser.login_name }}</span> ({{ currentUser.type }})
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>
