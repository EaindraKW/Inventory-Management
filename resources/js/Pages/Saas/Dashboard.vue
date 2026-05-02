<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import AppShell from './AppShell.vue';

const productsCount = ref(0);
const ordersCount = ref(0);
const totalSales = ref(0);
const products = ref([]);
const quantities = reactive({});
const loading = ref(false);
const loadingProducts = ref(false);
const placingOrder = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const productSearchQuery = ref('');

const formatMMK = (value) => {
    return `${Math.round(Number(value)).toLocaleString('en-US')} MMK`;
};

const orderItems = computed(() => {
    return products.value
        .filter((product) => Number(quantities[product.id]) > 0)
        .map((product) => ({
            product_id: product.id,
            quantity: Number(quantities[product.id]),
            price: Number(product.price),
        }));
});

const orderPreviewTotal = computed(() => {
    return orderItems.value.reduce(
        (sum, item) => sum + item.quantity * item.price,
        0,
    );
});

const filteredProducts = computed(() => {
    const query = productSearchQuery.value.trim().toLowerCase();

    if (!query) {
        return products.value;
    }

    return products.value.filter((product) => {
        return product.name.toLowerCase().includes(query);
    });
});

const firstValidationMessage = (errors) => {
    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const queue = [errors];

    while (queue.length > 0) {
        const current = queue.shift();

        if (Array.isArray(current)) {
            if (typeof current[0] === 'string') {
                return current[0];
            }

            for (const value of current) {
                queue.push(value);
            }

            continue;
        }

        if (current && typeof current === 'object') {
            for (const value of Object.values(current)) {
                queue.push(value);
            }
        }
    }

    return null;
};

const loadSummary = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const [productsResponse, ordersResponse] = await Promise.all([
            window.axios.get('/api/products'),
            window.axios.get('/api/orders'),
        ]);

        productsCount.value = productsResponse.data.products.length;
        ordersCount.value = ordersResponse.data.orders.length;
        totalSales.value = ordersResponse.data.orders.reduce(
            (sum, order) => sum + Number(order.total_price),
            0,
        );
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? 'Failed to load dashboard summary.';
    } finally {
        loading.value = false;
    }
};

const loadProducts = async () => {
    loadingProducts.value = true;

    try {
        const response = await window.axios.get('/api/products');
        products.value = response.data.products;

        for (const product of products.value) {
            if (!(product.id in quantities)) {
                quantities[product.id] = 0;
            }
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? 'Failed to load products.';
    } finally {
        loadingProducts.value = false;
    }
};

const placeOrder = async () => {
    successMessage.value = '';
    errorMessage.value = '';

    if (orderItems.value.length === 0) {
        errorMessage.value = 'Please select at least one product quantity.';
        return;
    }

    placingOrder.value = true;

    try {
        const response = await window.axios.post('/api/orders', {
            items: orderItems.value.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
            })),
        });

        successMessage.value = `Order #${response.data.order.id} placed successfully.`;

        for (const key of Object.keys(quantities)) {
            quantities[key] = 0;
        }

        await Promise.all([loadProducts(), loadSummary()]);
    } catch (error) {
        errorMessage.value = firstValidationMessage(error.response?.data?.errors)
            ?? error.response?.data?.message
            ?? 'Order failed. Please try again.';
    } finally {
        placingOrder.value = false;
    }
};

onMounted(async () => {
    await Promise.all([loadSummary(), loadProducts()]);
});
</script>

<template>
    <Head title="Dashboard" />

    <AppShell title="Dashboard" subtitle="Overview of your inventory and orders.">
        <div
            v-if="errorMessage"
            class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
        >
            {{ errorMessage }}
        </div>
        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
        >
            {{ successMessage }}
        </div>
 
        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500 shadow-sm">
            Loading summary...
        </div>

        <div v-else>
            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Products</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ productsCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Orders</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ ordersCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Sales</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatMMK(totalSales) }}</p>
                </div>
            </section>

            <section class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800">Quick Actions</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="/app/products" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        Manage Products
                    </a>
                    <a href="/app/orders" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Create / View Orders
                    </a>
                </div>
            </section>

            <section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-medium text-slate-700">
                    Product List
                </div>

                <div class="border-b border-slate-200 px-4 py-3">
                    <input
                        v-model="productSearchQuery"
                        type="text"
                        placeholder="Search product by name"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    />
                </div>

                <div v-if="loadingProducts" class="px-4 py-6 text-sm text-slate-500">
                    Loading products...
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Price</th>
                                <th class="px-4 py-3">Stock</th>
                                <th class="px-4 py-3">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="product in filteredProducts"
                                :key="product.id"
                                class="border-t border-slate-100"
                            >
                                <td class="px-4 py-3 text-slate-800">{{ product.name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ formatMMK(product.price) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ product.stock }}</td>
                                <td class="px-4 py-3">
                                    <input
                                        v-model.number="quantities[product.id]"
                                        type="number"
                                        min="0"
                                        :max="product.stock"
                                        class="w-24 rounded-md border border-slate-300 px-2 py-1"
                                    />
                                </td>
                            </tr>
                            <tr v-if="filteredProducts.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">
                                    No products match your search.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-4 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-sm font-medium text-slate-700">
                    Order Preview Total: <span class="text-slate-900">{{ formatMMK(orderPreviewTotal) }}</span>
                </p>
                <button
                    :disabled="placingOrder || loadingProducts"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="placeOrder"
                >
                    {{ placingOrder ? 'Placing Order...' : 'Place Order' }}
                </button>
            </section>
        </div>
    </AppShell>
</template>
