<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import AppShell from './AppShell.vue';

const products = ref([]);
const orders = ref([]);
const quantities = reactive({});

const loadingProducts = ref(false);
const loadingOrders = ref(false);
const placingOrder = ref(false);
const editingOrderId = ref(null);
const deletingOrderId = ref(null);
const productSearchQuery = ref('');
const orderSearchQuery = ref('');

const errorMessage = ref('');
const successMessage = ref('');

const formatMMK = (value) => {
    return `${Math.round(Number(value)).toLocaleString('en-US')} MMK`;
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

const loadOrders = async () => {
    loadingOrders.value = true;

    try {
        const response = await window.axios.get('/api/orders');
        orders.value = response.data.orders;
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? 'Failed to load orders.';
    } finally {
        loadingOrders.value = false;
    }
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

const filteredProducts = computed(() => {
    const query = productSearchQuery.value.trim().toLowerCase();

    if (!query) {
        return products.value;
    }

    return products.value.filter((product) => {
        return product.name.toLowerCase().includes(query);
    });
});

const filteredOrders = computed(() => {
    const query = orderSearchQuery.value.trim().toLowerCase();

    if (!query) {
        return orders.value;
    }

    return orders.value.filter((order) => {
        const byItems = order.items.some((item) => {
            return String(item.product?.name ?? '').toLowerCase().includes(query);
        });

        return byItems;
    });
});

const orderPreviewTotal = computed(() => {
    return orderItems.value.reduce(
        (sum, item) => sum + item.quantity * item.price,
        0,
    );
});

const orderTotal = (order) => formatMMK(order.total_price);

const itemSummary = (order) => {
    return order.items
        .map((item) => `${item.product?.name ?? 'Unknown'} x${item.quantity}`)
        .join(', ');
};

const resetOrderForm = () => {
    for (const key of Object.keys(quantities)) {
        quantities[key] = 0;
    }

    editingOrderId.value = null;
};

const startEditOrder = (order) => {
    errorMessage.value = '';
    successMessage.value = '';
    resetOrderForm();

    editingOrderId.value = order.id;

    for (const item of order.items) {
        quantities[item.product_id] = Number(item.quantity);
    }
};

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

const placeOrder = async () => {
    successMessage.value = '';
    errorMessage.value = '';

    if (orderItems.value.length === 0) {
        errorMessage.value = 'Please select at least one product quantity.';
        return;
    }

    placingOrder.value = true;

    try {
        const payload = {
            items: orderItems.value.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
            })),
        };

        const response = editingOrderId.value
            ? await window.axios.put(`/api/orders/${editingOrderId.value}`, payload)
            : await window.axios.post('/api/orders', payload);

        successMessage.value = editingOrderId.value
            ? `Order #${response.data.order.id} updated successfully.`
            : `Order #${response.data.order.id} placed successfully.`;

        resetOrderForm();

        await Promise.all([loadProducts(), loadOrders()]);
    } catch (error) {
        errorMessage.value = firstValidationMessage(error.response?.data?.errors)
            ?? error.response?.data?.message
            ?? 'Order failed. Please try again.';
    } finally {
        placingOrder.value = false;
    }
};

const deleteOrder = async (orderId) => {
    errorMessage.value = '';
    successMessage.value = '';
    deletingOrderId.value = orderId;

    try {
        await window.axios.delete(`/api/orders/${orderId}`);
        successMessage.value = 'Order deleted successfully.';

        if (editingOrderId.value === orderId) {
            resetOrderForm();
        }

        await Promise.all([loadProducts(), loadOrders()]);
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? 'Order deletion failed. Please try again.';
    } finally {
        deletingOrderId.value = null;
    }
};

onMounted(async () => {
    errorMessage.value = '';
    await Promise.all([loadProducts(), loadOrders()]);
});
</script>

<template>
    <Head title="Orders" />

    <AppShell title="Orders" subtitle="Create new orders and view order history.">
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

        <section class="mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
            <div class="border-b border-slate-200 px-4 py-3 text-sm font-medium text-slate-700">
                {{ editingOrderId ? 'Edit Order' : 'Create Order' }}
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

            <div class="flex items-center justify-between border-t border-slate-200 px-4 py-3">
                <p class="text-sm font-medium text-slate-700">
                    Order Preview Total: <span class="text-slate-900">{{ formatMMK(orderPreviewTotal) }}</span>
                </p>
                <div class="flex gap-2">
                    <button
                        v-if="editingOrderId"
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="resetOrderForm"
                    >
                        Cancel
                    </button>
                    <button
                        :disabled="placingOrder || loadingProducts"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="placeOrder"
                    >
                        {{ placingOrder ? (editingOrderId ? 'Updating Order...' : 'Placing Order...') : (editingOrderId ? 'Update Order' : 'Place Order') }}
                    </button>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
            <div class="border-b border-slate-200 px-4 py-3 text-sm font-medium text-slate-700">
                Order History
            </div>

            <div class="border-b border-slate-200 px-4 py-3">
                <input
                    v-model="orderSearchQuery"
                    type="text"
                    placeholder="Search by product"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
            </div>

            <div v-if="loadingOrders" class="px-4 py-6 text-sm text-slate-500">
                Loading orders...
            </div>

            <div v-else-if="orders.length === 0" class="px-4 py-6 text-sm text-slate-500">
                No orders yet.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="order in filteredOrders"
                            :key="order.id"
                            class="border-t border-slate-100"
                        >
                            <td class="px-4 py-3 text-slate-700">{{ itemSummary(order) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ orderTotal(order) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ new Date(order.created_at).toLocaleString() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button
                                        class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
                                        @click="startEditOrder(order)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        :disabled="deletingOrderId === order.id"
                                        class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-60"
                                        @click="deleteOrder(order.id)"
                                    >
                                        {{ deletingOrderId === order.id ? 'Deleting...' : 'Delete' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredOrders.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">
                                No orders match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppShell>
</template>
