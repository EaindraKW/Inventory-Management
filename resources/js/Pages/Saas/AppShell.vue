<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
});

const token = localStorage.getItem('auth_token');

if (!token) {
    window.location.href = '/app/login';
}

window.axios.defaults.headers.common.Authorization = `Bearer ${token}`;

const path = computed(() => window.location.pathname);

const navItems = [
    { label: 'Dashboard', href: '/app/dashboard' },
    { label: 'Products', href: '/app/products' },
    { label: 'Orders', href: '/app/orders' },
];

const logout = async () => {
    try {
        await window.axios.post('/api/logout');
    } catch {
        // Ignore API logout failures and clear local client state.
    }

    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
    delete window.axios.defaults.headers.common.Authorization;
    window.location.href = '/app/login';
};
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <div class="mx-auto flex max-w-7xl px-4 py-6 lg:gap-6">
            <aside class="hidden w-64 shrink-0 lg:block">
                <div class="sticky top-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nordic Inventory</p>
                    <nav class="mt-4 space-y-2">
                        <a
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="path === item.href
                                ? 'bg-slate-900 text-white'
                                : 'text-slate-700 hover:bg-slate-100'"
                        >
                            {{ item.label }}
                        </a>
                    </nav>
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900">{{ title }}</h1>
                            <p v-if="subtitle" class="text-sm text-slate-600">{{ subtitle }}</p>
                        </div>
                        <button
                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                            @click="logout"
                        >
                            Logout
                        </button>
                    </div>
                    <div class="mt-3 flex gap-2 lg:hidden">
                        <a
                            v-for="item in navItems"
                            :key="item.href + '-mobile'"
                            :href="item.href"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="path === item.href
                                ? 'bg-slate-900 text-white'
                                : 'bg-slate-100 text-slate-700'"
                        >
                            {{ item.label }}
                        </a>
                    </div>
                </header>

                <slot />
            </main>
        </div>
    </div>
</template>
