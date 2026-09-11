<template>
    <div class="min-h-screen bg-gray-100 p-8">
        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">{{ org?.title || 'Организация' }}</h1>
                <router-link to="/settings" class="text-blue-600 hover:underline">
                    ← Настройки
                </router-link>
            </div>

            <div v-if="loadingOrg" class="text-gray-500">Загрузка...</div>
            <div v-else-if="errorOrg" class="text-red-600">{{ errorOrg }}</div>
            <div v-else-if="org" class="bg-white rounded shadow p-6 mb-6">

                <!-- Статус парсинга -->
                <div v-if="isParsing"
                    class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-blue-700 text-sm flex items-center gap-2">
                    <span
                        class="inline-block w-3 h-3 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></span>
                    {{ statusLabel }}. Обновление автоматически.
                </div>
                <div v-else-if="isFailed" class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                    <div class="font-medium mb-1">Не удалось спарсить организацию</div>
                    <div class="text-xs mb-2">{{ org.parse_error }}</div>
                    <button @click="retryParse" :disabled="refreshing"
                        class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700 disabled:opacity-50">
                        {{ refreshing ? 'Запуск...' : 'Повторить парсинг' }}
                    </button>
                </div>

                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-3xl font-bold text-yellow-500">{{ org.rating ?? '—' }}</div>
                        <div class="text-sm text-gray-500 mt-1">Средний рейтинг</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold">{{ org.ratings_count }}</div>
                        <div class="text-sm text-gray-500 mt-1">Оценок</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold">{{ org.reviews_count }}</div>
                        <div class="text-sm text-gray-500 mt-1">Отзывов</div>
                    </div>
                </div>
                <div class="text-sm text-gray-400 mt-4 text-center">
                    Собрано в базе: {{ totalReviews }} из {{ org.reviews_count }}
                </div>
            </div>

            <div class="bg-white rounded shadow">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h2 class="text-lg font-semibold">Отзывы</h2>
                    <span class="text-sm text-gray-500">Стр. {{ page }} из {{ lastPage }}</span>
                </div>

                <div v-if="loadingReviews" class="p-6 text-gray-500">Загрузка отзывов...</div>
                <div v-else-if="errorReviews" class="p-6 text-red-600">{{ errorReviews }}</div>

                <ul v-else class="divide-y">
                    <li v-for="r in reviews" :key="r.id" class="p-6">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="font-medium">{{ r.author }}</div>
                                <div class="text-xs text-gray-400">{{ formatDate(r.date) }}</div>
                            </div>
                            <div class="text-yellow-500 font-bold">{{ r.rating }} ★</div>
                        </div>
                        <p class="text-gray-700 whitespace-pre-line">{{ r.text || '(без текста)' }}</p>
                    </li>
                </ul>

                <div v-if="lastPage > 1" class="px-6 py-4 border-t flex justify-center gap-2">
                    <button :disabled="page <= 1 || loadingReviews" @click="goTo(page - 1)"
                        class="px-3 py-1 border rounded disabled:opacity-50">
                        ← Назад
                    </button>
                    <button v-for="p in visiblePages" :key="p" @click="goTo(p)" :class="[
                        'px-3 py-1 border rounded',
                        p === page ? 'bg-blue-600 text-white border-blue-600' : ''
                    ]">
                        {{ p }}
                    </button>
                    <button :disabled="page >= lastPage || loadingReviews" @click="goTo(page + 1)"
                        class="px-3 py-1 border rounded disabled:opacity-50">
                        Вперёд →
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const router = useRouter();

const org = ref(null);
const loadingOrg = ref(false);
const errorOrg = ref('');

const reviews = ref([]);
const totalReviews = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loadingReviews = ref(false);
const errorReviews = ref('');
const refreshing = ref(false);

let pollTimer = null;

const statusLabel = computed(() => {
    if (!org.value) return '';
    return {
        pending: 'В очереди на парсинг',
        running: 'Парсинг идёт...',
        failed: 'Не удалось спарсить',
        ok: 'Готово',
    }[org.value.parse_status] || org.value.parse_status;
});

const isParsing = computed(() =>
    org.value && (org.value.parse_status === 'pending' || org.value.parse_status === 'running')
);

const isFailed = computed(() => org.value && org.value.parse_status === 'failed');

async function loadOrg() {
    loadingOrg.value = true;
    errorOrg.value = '';
    try {
        const { data } = await axios.get(`/api/organizations/${route.params.id}`);
        org.value = data;

        if (data.parse_status === 'pending' || data.parse_status === 'running') {
            schedulePoll();
        } else {
            stopPoll();
        }
    } catch (e) {
        errorOrg.value = e?.response?.data?.message || 'Не удалось загрузить организацию';
    } finally {
        loadingOrg.value = false;
    }
}

function schedulePoll() {
    stopPoll();
    pollTimer = setTimeout(async () => {
        await loadOrg();
        if (org.value && org.value.parse_status === 'ok') {
            await loadReviews(1);
        }
    }, 3000);
}

function stopPoll() {
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

async function retryParse() {
    refreshing.value = true;
    try {
        await axios.post(`/api/organizations/${route.params.id}/refresh`);
        await loadOrg();
    } catch (e) {
        errorOrg.value = e?.response?.data?.message || 'Не удалось перезапустить парсинг';
    } finally {
        refreshing.value = false;
    }
}

async function loadReviews(p = 1) {
    loadingReviews.value = true;
    errorReviews.value = '';
    try {
        const { data } = await axios.get(`/api/organizations/${route.params.id}/reviews`, {
            params: { page: p, per_page: 50 },
        });
        reviews.value = data.data;
        page.value = data.current_page;
        lastPage.value = data.last_page;
        totalReviews.value = data.total;
        router.replace({ query: { page: p } });
    } catch (e) {
        errorReviews.value = e?.response?.data?.message || 'Не удалось загрузить отзывы';
    } finally {
        loadingReviews.value = false;
    }
}

function goTo(p) {
    if (p < 1 || p > lastPage.value || p === page.value) return;
    loadReviews(p);
}

const visiblePages = computed(() => {
    const total = lastPage.value;
    const current = page.value;
    const range = 2;
    const pages = [];
    const start = Math.max(1, current - range);
    const end = Math.min(total, current + range);
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
});

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('ru-RU', { year: 'numeric', month: 'long', day: 'numeric' });
}

onMounted(() => {
    loadOrg();
    const initialPage = parseInt(route.query.page) || 1;
    loadReviews(initialPage);
});

onUnmounted(() => {
    stopPoll();
});
</script>
