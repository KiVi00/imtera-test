<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <form @submit.prevent="submit" class="bg-white p-8 rounded shadow w-96">
            <h1 class="text-2xl font-bold mb-4">Вход</h1>

            <div v-if="error" class="mb-3 text-red-600 text-sm">{{ error }}</div>

            <label class="block mb-3">
                <span class="text-sm">Email</span>
                <input v-model="email" type="email" required
                       class="mt-1 w-full border rounded px-3 py-2" />
            </label>

            <label class="block mb-4">
                <span class="text-sm">Пароль</span>
                <input v-model="password" type="password" required
                       class="mt-1 w-full border rounded px-3 py-2" />
            </label>

            <button type="submit" :disabled="loading"
                    class="w-full bg-blue-600 text-white rounded py-2 hover:bg-blue-700 disabled:opacity-50">
                {{ loading ? 'Вход...' : 'Войти' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const email = ref('kirill@gmail.com');
const password = ref('password123');
const loading = ref(false);
const error = ref('');

const auth = useAuthStore();
const router = useRouter();

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        await auth.login(email.value, password.value);
        router.push({ name: 'dashboard' });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось войти';
    } finally {
        loading.value = false;
    }
}
</script>
