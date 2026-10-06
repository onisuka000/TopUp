<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '', type: 'all', status: 'all' }),
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            banned: 0,
            root: 0,
            super_senior: 0,
            senior: 0,
            junior: 0,
        }),
    },
});

const page = usePage();
const permissions = page.props.auth?.can || {};
const currentAuthUser = page.props.auth?.user || {};

// Search & Filter state
const search = ref(props.filters.search || '');
const selectedType = ref(props.filters.type || 'all');
const selectedStatus = ref(props.filters.status || 'all');

const applyFilter = () => {
    router.get(
        route('admin.users.index'),
        {
            search: search.value,
            type: selectedType.value,
            status: selectedStatus.value,
        },
        { preserveState: true, replace: true }
    );
};

// Role Badge Helpers
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
    if (t === 'member') {
        return 'bg-purple-500/20 text-purple-300 border border-purple-500/40 shadow-sm shadow-purple-500/20';
    }
    return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-sm shadow-emerald-500/20';
};

const getRoleLabel = (type) => {
    const t = (type || '').toLowerCase();
    if (t === 'root') return '👑 ROOT ADMIN';
    if (t === 'super_senior' || t === 'super senior') return '⭐ SUPER SENIOR';
    if (t === 'senior') return '🛡️ SENIOR STAFF';
    if (t === 'member') return '🎮 MEMBER (STOREFRONT)';
    return '👁️ JUNIOR (VIEW ONLY)';
};

// Modal State
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingUserId = ref(null);

const form = useForm({
    username: '',
    login_name: '',
    password: '',
    type: 'junior',
    status: true,
    remark: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    editingUserId.value = null;
    form.reset();
    form.clearErrors();
    form.type = 'junior';
    form.status = true;
    isModalOpen.value = true;
};

const openEditModal = (targetUser) => {
    isEditing.value = true;
    editingUserId.value = targetUser.id;
    form.reset();
    form.clearErrors();
    form.username = targetUser.username;
    form.login_name = targetUser.login_name;
    form.password = '';
    form.type = targetUser.type;
    form.status = Boolean(targetUser.status);
    form.remark = targetUser.remark || '';
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('admin.users.update', editingUserId.value), {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    } else {
        form.post(route('admin.users.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    }
};

// Toggle user ban status
const toggleStatus = (targetUser) => {
    router.patch(route('admin.users.toggle', targetUser.id), {}, { preserveScroll: true });
};

// Delete user modal
const isDeleteModalOpen = ref(false);
const userToDelete = ref(null);

const confirmDelete = (targetUser) => {
    userToDelete.value = targetUser;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!userToDelete.value) return;
    router.delete(route('admin.users.destroy', userToDelete.value.id), {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            userToDelete.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout title="Users Management">
        <!-- Top Title & Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                    <span>👥 Staff & User Management</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono font-bold">
                        {{ stats.total }} Total
                    </span>
                </h1>
                <p class="text-slate-400 text-xs mt-1">
                    Control portal access, assign hierarchical roles, toggle active/ban status, and manage credentials.
                </p>
            </div>

            <button
                v-if="permissions.create_user"
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 hover:to-yellow-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition transform active:scale-95"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                <span>Add New User</span>
            </button>
        </div>

        <!-- 5 Role / Status Summary Badges -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div class="bg-slate-900/80 border border-rose-500/20 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-bold uppercase tracking-wider text-rose-400 block">Root Admin</span>
                    <span class="text-xl font-black text-white">{{ stats.root }}</span>
                </div>
                <span class="text-xl">👑</span>
            </div>
            <div class="bg-slate-900/80 border border-amber-500/20 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-bold uppercase tracking-wider text-amber-400 block">Super Senior</span>
                    <span class="text-xl font-black text-white">{{ stats.super_senior }}</span>
                </div>
                <span class="text-xl">⭐</span>
            </div>
            <div class="bg-slate-900/80 border border-cyan-500/20 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-bold uppercase tracking-wider text-cyan-400 block">Senior Staff</span>
                    <span class="text-xl font-black text-white">{{ stats.senior }}</span>
                </div>
                <span class="text-xl">🛡️</span>
            </div>
            <div class="bg-slate-900/80 border border-emerald-500/20 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-400 block">Junior Staff</span>
                    <span class="text-xl font-black text-white">{{ stats.junior }}</span>
                </div>
                <span class="text-xl">👁️</span>
            </div>
            <div class="bg-slate-900/80 border border-purple-500/20 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-bold uppercase tracking-wider text-purple-400 block">Members (Store)</span>
                    <span class="text-xl font-black text-white">{{ stats.member || 0 }}</span>
                </div>
                <span class="text-xl">🎮</span>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 shadow-xl">
            <!-- Type Dropdown Filter -->
            <div class="w-full md:w-52">
                <select
                    v-model="selectedType"
                    @change="applyFilter"
                    class="w-full bg-slate-950 border border-slate-800 text-slate-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-amber-400 transition"
                >
                    <option value="all">All User Types</option>
                    <option value="root">👑 Root</option>
                    <option value="super_senior">⭐ Super Senior</option>
                    <option value="senior">🛡️ Senior</option>
                    <option value="junior">👁️ Junior (View Only)</option>
                    <option value="member">🎮 Member (Storefront Topup)</option>
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
                    placeholder="Search username, login_name, remark..."
                    class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-4 py-2 pl-10 text-xs focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition placeholder-slate-500"
                />
            </div>

            <!-- Status Tabs & Button -->
            <div class="flex items-center gap-3">
                <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800">
                    <button
                        @click="selectedStatus = 'all'; applyFilter()"
                        :class="[
                            'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition',
                            selectedStatus === 'all' ? 'bg-amber-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        All ({{ stats.total }})
                    </button>
                    <button
                        @click="selectedStatus = 'active'; applyFilter()"
                        :class="[
                            'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition',
                            selectedStatus === 'active' ? 'bg-emerald-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Active ({{ stats.active }})
                    </button>
                    <button
                        @click="selectedStatus = 'banned'; applyFilter()"
                        :class="[
                            'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition',
                            selectedStatus === 'banned' ? 'bg-rose-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Banned ({{ stats.banned }})
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

        <!-- Users Table -->
        <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950/70 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-4">User</th>
                            <th class="py-3.5 px-4">Login Name</th>
                            <th class="py-3.5 px-4">Role / Type</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">Remark</th>
                            <th class="py-3.5 px-4">Created Date</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-200">
                        <tr
                            v-for="targetUser in users.data"
                            :key="targetUser.id"
                            class="hover:bg-slate-800/40 transition group"
                        >
                            <!-- User Avatar & Username -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center font-bold text-slate-950 text-xs shrink-0 shadow-sm">
                                        {{ (targetUser.username || targetUser.login_name || 'U').charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-white text-sm flex items-center gap-1.5">
                                            <span>{{ targetUser.username }}</span>
                                            <span
                                                v-if="targetUser.id === currentAuthUser.id"
                                                class="text-[9px] px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30"
                                            >
                                                You
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 font-mono">ID: #{{ targetUser.id }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Login Name -->
                            <td class="py-3.5 px-4 font-mono text-slate-300">
                                {{ targetUser.login_name }}
                            </td>

                            <!-- Role / Type Badge -->
                            <td class="py-3.5 px-4">
                                <span :class="['text-[10px] font-bold px-2.5 py-0.5 rounded-full inline-block', getRoleBadgeClass(targetUser.type)]">
                                    {{ getRoleLabel(targetUser.type) }}
                                </span>
                            </td>

                            <!-- Status Toggle (Active vs Banned) -->
                            <td class="py-3.5 px-4">
                                <button
                                    v-if="permissions.edit_user && targetUser.id !== currentAuthUser.id"
                                    @click="toggleStatus(targetUser)"
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-bold border transition flex items-center gap-1.5',
                                        targetUser.status
                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20'
                                            : 'bg-rose-500/10 text-rose-400 border-rose-500/30 hover:bg-rose-500/20'
                                    ]"
                                    :title="targetUser.status ? 'Click to Ban User' : 'Click to Activate User'"
                                >
                                    <span :class="['w-1.5 h-1.5 rounded-full', targetUser.status ? 'bg-emerald-400' : 'bg-rose-400']"></span>
                                    <span>{{ targetUser.status ? 'Active' : 'Banned' }}</span>
                                </button>
                                <span
                                    v-else
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-bold border inline-block',
                                        targetUser.status
                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                                            : 'bg-rose-500/10 text-rose-400 border-rose-500/30'
                                    ]"
                                >
                                    {{ targetUser.status ? 'Active' : 'Banned' }}
                                </span>
                            </td>

                            <!-- Remark -->
                            <td class="py-3.5 px-4 text-slate-400 max-w-xs truncate">
                                {{ targetUser.remark || '-' }}
                            </td>

                            <!-- Created Date -->
                            <td class="py-3.5 px-4 text-slate-400 text-[11px] font-mono whitespace-nowrap">
                                {{ new Date(targetUser.created_at).toLocaleDateString() }}
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right space-x-1.5">
                                <button
                                    v-if="permissions.edit_user"
                                    @click="openEditModal(targetUser)"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition font-medium text-[11px]"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="permissions.delete_user && targetUser.id !== currentAuthUser.id"
                                    @click="confirmDelete(targetUser)"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition font-medium text-[11px] border border-rose-500/20"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="users.data.length === 0">
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-3xl mb-2">👥</span>
                                    <span class="text-sm font-medium">No users match criteria</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="users.links && users.links.length > 3"
                class="p-4 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs"
            >
                <div class="text-slate-400">
                    Showing {{ users.from || 0 }} to {{ users.to || 0 }} of {{ users.total }} users
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-for="(link, idx) in users.links"
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
                        <span>{{ isEditing ? '✏️ Edit User' : '👥 Add System User' }}</span>
                    </h2>
                    <button @click="isModalOpen = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Username & Login Name Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Username -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                Username *
                            </label>
                            <input
                                v-model="form.username"
                                type="text"
                                required
                                placeholder="e.g. john_manager"
                                class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                                :class="{ 'border-rose-500': form.errors.username }"
                            />
                            <p v-if="form.errors.username" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.username }}</p>
                        </div>

                        <!-- Login Name -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                Login Name *
                            </label>
                            <input
                                v-model="form.login_name"
                                type="text"
                                required
                                placeholder="e.g. john"
                                class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                                :class="{ 'border-rose-500': form.errors.login_name }"
                            />
                            <p v-if="form.errors.login_name" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.login_name }}</p>
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            {{ isEditing ? 'New Password (Leave blank to keep unchanged)' : 'Password *' }}
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!isEditing"
                            placeholder="••••••••"
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                            :class="{ 'border-rose-500': form.errors.password }"
                        />
                        <p v-if="form.errors.password" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.password }}</p>
                    </div>

                    <!-- User Type / Role -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            User Type / Hierarchy Level *
                        </label>
                        <select
                            v-model="form.type"
                            required
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                            :class="{ 'border-rose-500': form.errors.type }"
                        >
                            <option value="junior">👁️ Junior Staff (View Only)</option>
                            <option value="senior">🛡️ Senior Staff (View & Edit)</option>
                            <option value="super_senior">⭐ Super Senior (Full CRUD)</option>
                            <option value="root">👑 Root Administrator (Unrestricted Access)</option>
                            <option value="member">🎮 Member (Storefront Diamond Topup)</option>
                        </select>
                        <p v-if="form.errors.type" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.type }}</p>
                    </div>

                    <!-- Remark -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Remark / Notes (Optional)
                        </label>
                        <input
                            v-model="form.remark"
                            type="text"
                            placeholder="e.g. Day shift cashier, support manager..."
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                        />
                    </div>

                    <!-- Status Switch -->
                    <label class="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center gap-2.5 cursor-pointer hover:border-slate-700">
                        <input
                            v-model="form.status"
                            type="checkbox"
                            class="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-amber-400"
                        />
                        <div>
                            <span class="text-xs font-bold text-slate-200 block">Account Active</span>
                            <span class="text-[10px] text-slate-500 block">If unchecked, user is banned and cannot log into the system</span>
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
                            {{ isEditing ? 'Save Changes' : 'Create User' }}
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
                    Delete User '{{ userToDelete?.username }}'?
                </h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    This user account will be permanently removed. The user will immediately lose access to the admin system.
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
