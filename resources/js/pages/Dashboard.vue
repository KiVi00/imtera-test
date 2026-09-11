<template>
    <div class="p-8">
        <div class="flex justify-between mb-6">
            <h1 class="text-2xl font-bold">Дашборд</h1>
            <div class="flex items-center gap-4">
                <router-link to="/settings" class="text-blue-600 hover:underline">
                    Настройки
                </router-link>
                <button @click="logout" class="text-blue-600 hover:underline">Выйти</button>
            </div>
        </div>
        <p>Привет, {{ auth.user?.name }}!</p>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>
