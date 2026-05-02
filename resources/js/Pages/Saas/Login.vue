<script setup>
import { Head } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const form = reactive({
    email: '',
    password: '',
});

const loading = ref(false);
const errorMessage = ref('');

const submit = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post('/api/login', {
            ...form,
            device_name: 'web-client',
        });

        localStorage.setItem('auth_token', response.data.token);
        localStorage.setItem('auth_user', JSON.stringify(response.data.user));

        window.location.href = '/app/dashboard';
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? 'Login failed. Please check your credentials.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Login" />

    <div class="min-h-screen bg-slate-100 px-4 py-16">
        <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-lg">
            <h1 class="text-2xl font-bold text-slate-900">Inventory Login</h1>
            <p class="mt-2 text-sm text-slate-600">
                Sign in to view products and place orders.
            </p>

            <div
                v-if="errorMessage"
                class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 outline-none transition focus:border-slate-500"
                        placeholder="you@example.com"
                    />
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 outline-none transition focus:border-slate-500"
                        placeholder="Your password"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ loading ? 'Signing in...' : 'Login' }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-600">
                Don't have an account?
                <a href="/app/register" class="font-medium text-slate-900 underline">Register</a>
            </p>
        </div>
    </div>
</template>