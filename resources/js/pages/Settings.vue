<template>
    <div class="min-h-screen bg-gray-100 p-8">
        <div class="max-w-2xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Настройки</h1>
                <router-link to="/dashboard" class="text-blue-600 hover:underline">
                    ← На дашборд
                </router-link>
            </div>

            <div class="bg-white rounded shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Добавить организацию</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm mb-1">Ссылка на Яндекс.Карты</label>
                        <input v-model="url" type="url" required placeholder="https://yandex.ru/maps/org/..."
                            class="w-full border rounded px-3 py-2" />
                    </div>
                    <button type="submit" :disabled="loading"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
                        {{ loading ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </form>
                <p v-if="error" class="mt-3 text-red-600 text-sm">{{ error }}</p>
                <p v-if="success" class="mt-3 text-green-600 text-sm">{{ success }}</p>
            </div>

            <div v-if="organizations.length" class="bg-white rounded shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Мои организации</h2>
                <ul class="space-y-2">
                    <li v-for="org in organizations" :key="org.id" class="border-b pb-2">
                        <div class="font-medium">
                            <router-link :to="`/organizations/${org.id}`"
                                class="font-medium text-blue-600 hover:underline">
                                {{ org.title || 'Без названия' }}
                            </router-link>
                        </div>
                        <div class="text-sm text-gray-500">{{ org.url }}</div>
                        <div class="text-xs text-gray-400">Статус: {{ org.parse_status }}</div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const url = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');
const organizations = ref([]);

async function loadOrganizations() {
    try {
        const { data } = await axios.get('/api/organizations');
        organizations.value = data;
    } catch (e) {
        console.error(e);
    }
}

async function submit() {
    loading.value = true;
    error.value = '';
    success.value = '';
    try {
        await axios.post('/api/organizations', { url: url.value });
        success.value = 'Организация сохранена!';
        url.value = '';
        await loadOrganizations();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось сохранить';
    } finally {
        loading.value = false;
    }
}

onMounted(loadOrganizations);
</script>
