<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import AppShell from './AppShell.vue';

const products = ref([]);
const loadingProducts = ref(false);
const savingProduct = ref(false);
const deletingProductId = ref(null);
const editingProductId = ref(null);
const searchQuery = ref('');

const errorMessage = ref('');
const successMessage = ref('');

const formatMMK = (value) => {
    return `${Math.round(Number(value)).toLocaleString('en-US')} MMK`;
};

const productForm = reactive({
    name: '',
    price: '',
    stock: '',
});

const filteredProducts = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return products.value;
    }

    return products.value.filter((product) => {
        return product.name.toLowerCase().includes(query);
    });
});

const loadProducts = async () => {
    loadingProducts.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.get('/api/products');
        products.value = response.data.products;
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? 'Failed to load products.';
    } finally {
        loadingProducts.value = false;
    }
};

const resetProductForm = () => {
    productForm.name = '';
    productForm.price = '';
    productForm.stock = '';
    editingProductId.value = null;
};

const startEditProduct = (product) => {
    errorMessage.value = '';
    successMessage.value = '';

    editingProductId.value = product.id;
    productForm.name = product.name;
    productForm.price = Number(product.price);
    productForm.stock = Number(product.stock);
};

const saveProduct = async () => {
    errorMessage.value = '';
    successMessage.value = '';
    savingProduct.value = true;

    try {
        const payload = {
            name: String(productForm.name).trim(),
            price: Number(productForm.price),
            stock: Number(productForm.stock),
        };

        if (editingProductId.value) {
            await window.axios.put(`/api/products/${editingProductId.value}`, payload);
            successMessage.value = 'Product updated successfully.';
        } else {
            await window.axios.post('/api/products', payload);
            successMessage.value = 'Product created successfully.';
        }

        resetProductForm();
        await loadProducts();
    } catch (error) {
        const firstValidation = Object.values(error.response?.data?.errors ?? {})?.[0]?.[0];
        errorMessage.value = firstValidation
            ?? error.response?.data?.message
            ?? 'Product save failed. Please try again.';
    } finally {
        savingProduct.value = false;
    }
};

const deleteProduct = async (productId) => {
    errorMessage.value = '';
    successMessage.value = '';
    deletingProductId.value = productId;

    try {
        await window.axios.delete(`/api/products/${productId}`);
        successMessage.value = 'Product deleted successfully.';

        if (editingProductId.value === productId) {
            resetProductForm();
        }

        await loadProducts();
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? 'Product deletion failed.';
    } finally {
        deletingProductId.value = null;
    }
};

onMounted(loadProducts);
</script>

<template>
    <Head title="Products" />

    <AppShell title="Products" subtitle="Create, update, and manage stock.">
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

        <section class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-800">
                {{ editingProductId ? 'Edit Product' : 'Add Product' }}
            </h2>

            <form class="grid gap-3 md:grid-cols-4" @submit.prevent="saveProduct">
                <input
                    v-model="productForm.name"
                    required
                    type="text"
                    placeholder="Product name"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <input
                    v-model.number="productForm.price"
                    required
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Price"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <input
                    v-model.number="productForm.stock"
                    required
                    type="number"
                    min="0"
                    step="1"
                    placeholder="Stock"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <div class="flex gap-2">
                    <button
                        type="submit"
                        :disabled="savingProduct"
                        class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-60"
                    >
                        {{ savingProduct ? 'Saving...' : (editingProductId ? 'Update' : 'Create') }}
                    </button>
                    <button
                        v-if="editingProductId"
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                        @click="resetProductForm"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
            <div class="border-b border-slate-200 px-4 py-3 text-sm font-medium text-slate-700">
                Product List
            </div>

            <div class="border-b border-slate-200 px-4 py-3">
                <input
                    v-model="searchQuery"
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
                            <th class="px-4 py-3">Actions</th>
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
                                <div class="flex gap-2">
                                    <button
                                        class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
                                        @click="startEditProduct(product)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        :disabled="deletingProductId === product.id"
                                        class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-60"
                                        @click="deleteProduct(product.id)"
                                    >
                                        {{ deletingProductId === product.id ? 'Deleting...' : 'Delete' }}
                                    </button>
                                </div>
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
    </AppShell>
</template>
