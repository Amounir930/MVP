<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Subscription {
    plan_name: 'free' | 'startup' | 'growth';
    price: string;
    status: 'active' | 'expired' | 'suspended';
    monthly_limit: number;
    current_period_usage: number;
    current_period_end: string;
}

interface Store {
    id: string;
    name: string;
    status: 'active' | 'suspended';
    created_at: string;
    owner_name: string;
    owner_email: string;
    salla_connected: boolean;
    whatsapp_status: string;
    reviews_count: number;
    orders_count: number;
    products_count: number;
    subscription: Subscription | null;
}

const props = defineProps<{
    stats: {
        total_stores: number;
        active_stores: number;
        total_messages_consumed: number;
    };
    stores: Store[];
    gateway_keys: {
        tap_api_key: string;
        tap_webhook_secret: string;
    };
}>();

const localStores = ref<Store[]>([...props.stores]);
const search = ref('');
const statusFilter = ref<'all' | 'active' | 'suspended' | 'salla_connected' | 'whatsapp_connected'>('all');
const togglingStoreId = ref<string | null>(null);
const successMessage = ref('');
const errorMessage = ref('');

// Simulator State
const showSimModal = ref(false);
const selectedStoreForSim = ref<Store | null>(null);
const simPlanName = ref<'free' | 'startup' | 'growth'>('startup');
const simStatus = ref<'active' | 'expired' | 'suspended'>('active');
const simUsage = ref(0);
const simLoading = ref(false);
const simWebhookLoading = ref(false);

const openSimulator = (store: Store) => {
    selectedStoreForSim.value = store;
    if (store.subscription) {
        simPlanName.value = store.subscription.plan_name;
        simStatus.value = store.subscription.status;
        simUsage.value = store.subscription.current_period_usage;
    } else {
        simPlanName.value = 'free';
        simStatus.value = 'active';
        simUsage.value = 0;
    }
    showSimModal.value = true;
};

const submitManualSubscription = async () => {
    if (!selectedStoreForSim.value) return;
    simLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await fetch('/superadmin/simulator/update-subscription', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                tenant_id: selectedStoreForSim.value.id,
                plan_name: simPlanName.value,
                status: simStatus.value,
                current_period_usage: simUsage.value,
            }),
        });

        if (!response.ok) throw new Error('فشل تحديث الاشتراك.');

        const data = await response.json();
        if (data.success) {
            const index = localStores.value.findIndex(s => s.id === selectedStoreForSim.value!.id);
            if (index !== -1) {
                localStores.value[index].subscription = data.subscription;
            }
            successMessage.value = `تم تحديث اشتراك متجر "${selectedStoreForSim.value.name}" يدوياً بنجاح.`;
            showSimModal.value = false;
            setTimeout(() => successMessage.value = '', 4000);
        } else {
            throw new Error(data.message || 'حدث خطأ غير متوقع.');
        }
    } catch (err: any) {
        errorMessage.value = err.message || 'حدث خطأ أثناء معالجة الطلب.';
        setTimeout(() => errorMessage.value = '', 4000);
    } finally {
        simLoading.value = false;
    }
};

const triggerWebhookSimulation = async () => {
    if (!selectedStoreForSim.value) return;
    simWebhookLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await fetch('/superadmin/simulator/trigger-webhook', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                tenant_id: selectedStoreForSim.value.id,
                plan_name: simPlanName.value,
            }),
        });

        if (!response.ok) throw new Error('فشل محاكاة الدفع.');

        const data = await response.json();
        if (data.success) {
            const index = localStores.value.findIndex(s => s.id === selectedStoreForSim.value!.id);
            if (index !== -1) {
                localStores.value[index].subscription = data.subscription;
            }
            successMessage.value = `تمت محاكاة الدفع عبر بوابة Tap Payments بنجاح لمتجر "${selectedStoreForSim.value.name}".`;
            showSimModal.value = false;
            setTimeout(() => successMessage.value = '', 4000);
        } else {
            throw new Error(data.message || 'حدث خطأ غير متوقع.');
        }
    } catch (err: any) {
        errorMessage.value = err.message || 'حدث خطأ أثناء معالجة الطلب.';
        setTimeout(() => errorMessage.value = '', 4000);
    } finally {
        simWebhookLoading.value = false;
    }
};

const filteredStores = computed(() => {
    return localStores.value.filter((store) => {
        if (statusFilter.value === 'active' && store.status !== 'active') return false;
        if (statusFilter.value === 'suspended' && store.status !== 'suspended') return false;
        if (statusFilter.value === 'salla_connected' && !store.salla_connected) return false;
        if (statusFilter.value === 'whatsapp_connected' && store.whatsapp_status !== 'connected') return false;

        if (search.value.trim()) {
            const query = search.value.toLowerCase();
            return (
                store.name.toLowerCase().includes(query) ||
                store.owner_name.toLowerCase().includes(query) ||
                store.owner_email.toLowerCase().includes(query) ||
                store.id.toLowerCase().includes(query)
            );
        }
        return true;
    });
});

const toggleStoreStatus = async (store: Store) => {
    const actionText = store.status === 'active' ? 'تعطيل' : 'تفعيل';
    if (!confirm(`هل أنت متأكد من رغبتك في ${actionText} هذا المتجر؟`)) {
        return;
    }

    togglingStoreId.value = store.id;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        const response = await fetch(`/superadmin/stores/${store.id}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        });

        if (!response.ok) throw new Error('فشل تحديث حالة المتجر.');

        const data = await response.json();
        if (data.success) {
            const index = localStores.value.findIndex(s => s.id === store.id);
            if (index !== -1) {
                localStores.value[index].status = data.status;
            }
            successMessage.value = `تم ${actionText} المتجر "${store.name}" بنجاح.`;
            setTimeout(() => successMessage.value = '', 4000);
        } else {
            throw new Error(data.message || 'حدث خطأ غير متوقع.');
        }
    } catch (err: any) {
        errorMessage.value = err.message || 'حدث خطأ أثناء معالجة الطلب.';
        setTimeout(() => errorMessage.value = '', 4000);
    } finally {
        togglingStoreId.value = null;
    }
};
</script>

<template>
    <Head title="إدارة المنصة" />

    <AuthenticatedLayout>
        <!-- Background Radial Glows for Apple Aesthetic -->
        <div class="absolute top-0 right-1/4 w-[400px] h-[400px] bg-indigo-400/10 dark:bg-indigo-600/5 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="absolute bottom-1/4 left-1/4 w-[500px] h-[500px] bg-teal-400/5 dark:bg-teal-600/5 rounded-full blur-[120px] pointer-events-none"></div>

        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="text-right">
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-gray-100">
                        لوحة الإشراف العام للمنصة
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-450">
                        إدارة ومراقبة المتاجر المسجلة وتكاملات الربط.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12 relative z-10" dir="rtl">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Notifications Toast -->
                <transition name="fade">
                    <div v-if="successMessage" class="p-4 rounded-2xl bg-emerald-500/10 dark:bg-emerald-950/20 border border-emerald-500/20 dark:border-emerald-900/50 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2.5 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 shrink-0 text-emerald-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>{{ successMessage }}</span>
                    </div>
                </transition>

                <transition name="fade">
                    <div v-if="errorMessage" class="p-4 rounded-2xl bg-rose-500/10 dark:bg-rose-955/20 border border-rose-500/20 dark:border-rose-900/50 text-rose-700 dark:text-rose-400 text-sm font-semibold flex items-center gap-2.5 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 shrink-0 text-rose-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <span>{{ errorMessage }}</span>
                    </div>
                </transition>

                <!-- Stats Dashboard Grid (Apple Glassmorphism Cards) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Stores Card -->
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between transition-all duration-300 hover:scale-[1.01] hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.06)] dark:hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.3)]">
                        <div>
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">إجمالي المتاجر المسجلة</span>
                            <h4 class="text-4xl font-extrabold text-gray-900 dark:text-gray-100 mt-3 font-mono">
                                {{ stats.total_stores }}
                            </h4>
                        </div>
                        <p class="text-[11px] text-gray-450 dark:text-gray-500 mt-4 border-t border-gray-100 dark:border-gray-800 pt-3">كافة الاشتراكات المربوطة بالمنصة</p>
                    </div>

                    <!-- Active Stores Card -->
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between transition-all duration-300 hover:scale-[1.01] hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.06)] dark:hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.3)]">
                        <div>
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">المتاجر النشطة</span>
                            <h4 class="text-4xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3 font-mono">
                                {{ stats.active_stores }}
                            </h4>
                        </div>
                        <p class="text-[11px] text-gray-450 dark:text-gray-500 mt-4 border-t border-gray-100 dark:border-gray-800 pt-3">متاجر مفعلة ومكتملة الربط مع سلة</p>
                    </div>

                    <!-- Consumed Messages Card -->
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between transition-all duration-300 hover:scale-[1.01] hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.06)] dark:hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.3)]">
                        <div>
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">إجمالي رسائل واتساب المستهلكة</span>
                            <h4 class="text-4xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-3 font-mono">
                                {{ stats.total_messages_consumed }}
                            </h4>
                        </div>
                        <p class="text-[11px] text-gray-450 dark:text-gray-500 mt-4 border-t border-gray-100 dark:border-gray-800 pt-3">إجمالي الرسائل المرسلة على مستوى السيرفر</p>
                    </div>
                </div>

                <!-- API Gateway Keys Card -->
                <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                    <h3 class="text-lg font-extrabold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-indigo-500"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" /></svg>
                        مفاتيح اختبار بوابة الدفع (Tap Payments)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-sm font-mono">
                        <div class="bg-gray-50/50 dark:bg-[#2C2C2E]/50 p-4 rounded-2xl border border-gray-100 dark:border-gray-800">
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block mb-1">TAP_API_KEY (مفتاح الربط)</span>
                            <span class="text-gray-805 dark:text-gray-200 break-all select-all font-semibold">{{ gateway_keys.tap_api_key }}</span>
                        </div>
                        <div class="bg-gray-50/50 dark:bg-[#2C2C2E]/50 p-4 rounded-2xl border border-gray-100 dark:border-gray-800">
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block mb-1">TAP_WEBHOOK_SECRET (رمز التحقق)</span>
                            <span class="text-gray-805 dark:text-gray-200 break-all select-all font-semibold">{{ gateway_keys.tap_webhook_secret }}</span>
                        </div>
                    </div>
                </div>

                <!-- Stores Directory Card (Glassmorphism Container) -->
                <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl rounded-3xl border border-white/25 dark:border-white/5 shadow-[0_12px_40px_0_rgba(0,0,0,0.04)] dark:shadow-[0_12px_40px_0_rgba(0,0,0,0.3)] overflow-hidden">
                    
                    <!-- Search and filters -->
                    <div class="p-6 border-b border-gray-100/80 dark:border-gray-800/80 space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <h3 class="text-xl font-extrabold text-gray-900 dark:text-gray-100">دليل ومراقبة المتاجر</h3>
                            
                            <!-- Search Input -->
                            <div class="relative w-full md:w-80">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none text-gray-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </span>
                                <input 
                                    type="text" 
                                    v-model="search"
                                    placeholder="ابحث باسم المتجر، المالك، البريد..." 
                                    class="w-full pr-11 pl-4 py-2.5 bg-gray-50/50 border border-gray-200/60 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:bg-[#2C2C2E]/60 dark:border-gray-800 dark:text-gray-100 transition-all duration-200 text-right font-semibold"
                                />
                            </div>
                        </div>

                        <!-- Filter buttons list -->
                        <div class="flex flex-wrap gap-2 pt-2">
                            <button 
                                @click="statusFilter = 'all'"
                                :class="statusFilter === 'all' ? 'bg-indigo-600 text-white shadow-[0_4px_12px_0_rgba(79,70,229,0.2)]' : 'bg-gray-100/70 dark:bg-[#2C2C2E]/70 text-gray-600 dark:text-gray-400 border border-transparent hover:bg-gray-200 dark:hover:bg-[#3A3A3C]'"
                                class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all duration-200"
                            >
                                الكل
                            </button>
                            <button 
                                @click="statusFilter = 'active'"
                                :class="statusFilter === 'active' ? 'bg-emerald-600 text-white shadow-[0_4px_12px_0_rgba(16,185,129,0.2)]' : 'bg-gray-100/70 dark:bg-[#2C2C2E]/70 text-gray-600 dark:text-gray-400 border border-transparent hover:bg-gray-200 dark:hover:bg-[#3A3A3C]'"
                                class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all duration-200"
                            >
                                نشط
                            </button>
                            <button 
                                @click="statusFilter = 'suspended'"
                                :class="statusFilter === 'suspended' ? 'bg-rose-600 text-white shadow-[0_4px_12px_0_rgba(225,29,72,0.2)]' : 'bg-gray-100/70 dark:bg-[#2C2C2E]/70 text-gray-600 dark:text-gray-400 border border-transparent hover:bg-gray-200 dark:hover:bg-[#3A3A3C]'"
                                class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all duration-200"
                            >
                                معطل
                            </button>
                            <button 
                                @click="statusFilter = 'salla_connected'"
                                :class="statusFilter === 'salla_connected' ? 'bg-indigo-600 text-white shadow-[0_4px_12px_0_rgba(79,70,229,0.2)]' : 'bg-gray-100/70 dark:bg-[#2C2C2E]/70 text-gray-600 dark:text-gray-400 border border-transparent hover:bg-gray-200 dark:hover:bg-[#3A3A3C]'"
                                class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all duration-200"
                            >
                                سلة متصلة
                            </button>
                            <button 
                                @click="statusFilter = 'whatsapp_connected'"
                                :class="statusFilter === 'whatsapp_connected' ? 'bg-indigo-600 text-white shadow-[0_4px_12px_0_rgba(79,70,229,0.2)]' : 'bg-gray-100/70 dark:bg-[#2C2C2E]/70 text-gray-600 dark:text-gray-400 border border-transparent hover:bg-gray-200 dark:hover:bg-[#3A3A3C]'"
                                class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all duration-200"
                            >
                                واتساب متصل
                            </button>
                        </div>
                    </div>

                    <!-- Directory Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-gray-50/20 dark:bg-gray-900/10 border-b border-gray-100/60 dark:border-gray-800/60">
                                    <th class="px-6 py-4.5 text-xs font-bold text-gray-400 uppercase tracking-wider">اسم المتجر</th>
                                    <th class="px-6 py-4.5 text-xs font-bold text-gray-400 uppercase tracking-wider">المالك وبيانات الاتصال</th>
                                    <th class="px-6 py-4.5 text-xs font-bold text-gray-400 uppercase tracking-wider">الحالة والتراخيص</th>
                                    <th class="px-6 py-4.5 text-xs font-bold text-gray-400 uppercase tracking-wider">إحصائيات المزامنة</th>
                                    <th class="px-6 py-4.5 text-xs font-bold text-gray-400 uppercase tracking-wider">تاريخ الانضمام</th>
                                    <th class="px-6 py-4.5 text-xs font-bold text-gray-400 uppercase tracking-wider text-left">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100/40 dark:divide-gray-800/40">
                                <tr v-if="filteredStores.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-450 dark:text-gray-550 font-semibold">
                                        لا توجد متاجر مطابقة لخيارات البحث الحالية.
                                    </td>
                                </tr>
                                <tr 
                                    v-for="store in filteredStores" 
                                    :key="store.id"
                                    class="hover:bg-gray-50/40 dark:hover:bg-[#2C2C2E]/20 transition-colors duration-200"
                                >
                                    <!-- Store Name -->
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="font-extrabold text-sm text-gray-900 dark:text-gray-100">{{ store.name }}</span>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 font-mono mt-1 select-all">{{ store.id }}</span>
                                        </div>
                                    </td>

                                    <!-- Owner Details -->
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ store.owner_name }}</span>
                                            <span class="text-xs text-gray-450 mt-0.5 font-mono select-all">{{ store.owner_email }}</span>
                                        </div>
                                    </td>

                                    <!-- Integrations and Status -->
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <!-- Store Status Badge -->
                                            <span 
                                                :class="store.status === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20' : 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20'"
                                                class="px-2.5 py-0.5 rounded-full border text-[10px] font-bold"
                                            >
                                                {{ store.status === 'active' ? 'نشط' : 'معطل' }}
                                            </span>
                                            
                                            <!-- Salla Config Connected -->
                                            <span 
                                                :class="store.salla_connected ? 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-500/20' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 border-transparent'"
                                                class="px-2.5 py-0.5 rounded-full border text-[10px] font-bold"
                                            >
                                                سلة: {{ store.salla_connected ? 'متصلة' : 'غير متصلة' }}
                                            </span>

                                            <!-- WhatsApp Status Badge -->
                                            <span 
                                                :class="store.whatsapp_status === 'connected' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 border-transparent'"
                                                class="px-2.5 py-0.5 rounded-full border text-[10px] font-bold"
                                            >
                                                واتساب: {{ store.whatsapp_status === 'connected' ? 'متصل' : 'غير متصل' }}
                                            </span>

                                            <!-- Subscription Plan Badge -->
                                            <span 
                                                v-if="store.subscription"
                                                :class="store.subscription.plan_name === 'growth' ? 'bg-[#eeebff] text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400 border-indigo-500/20' : (store.subscription.plan_name === 'startup' ? 'bg-teal-500/10 text-teal-700 dark:text-teal-400 border-teal-500/20' : 'bg-gray-550/10 text-gray-600 dark:text-gray-400 border-transparent')"
                                                class="px-2.5 py-0.5 rounded-full border text-[10px] font-bold"
                                            >
                                                باقة: {{ store.subscription.plan_name === 'growth' ? 'النمو (Growth)' : (store.subscription.plan_name === 'startup' ? 'الناشئة (Startup)' : 'المجانية (Free)') }}
                                            </span>
                                            <span 
                                                v-else
                                                class="px-2.5 py-0.5 rounded-full border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-[10px] font-bold"
                                            >
                                                باقة: المجانية (Free)
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Synchronization Metrics -->
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <div class="flex flex-col gap-1 text-xs font-mono text-gray-500 dark:text-gray-400">
                                            <div class="flex items-center gap-4">
                                                <div>
                                                    <span class="text-gray-400">الطلبات:</span>
                                                    <span class="font-bold text-gray-800 dark:text-gray-100 mr-1">{{ store.orders_count }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-400">المنتجات:</span>
                                                    <span class="font-bold text-gray-800 dark:text-gray-100 mr-1">{{ store.products_count }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-400">التقييمات:</span>
                                                    <span class="font-bold text-gray-800 dark:text-gray-100 mr-1">{{ store.reviews_count }}</span>
                                                </div>
                                            </div>
                                            <!-- Message Usage stats -->
                                            <div class="flex items-center gap-1 mt-1 text-[11px] font-semibold text-gray-400">
                                                <span>رسائل البوت:</span>
                                                <span v-if="store.subscription" :class="store.subscription.current_period_usage >= store.subscription.monthly_limit ? 'text-rose-500 font-bold' : 'text-gray-700 dark:text-gray-300'">
                                                    {{ store.subscription.current_period_usage }}
                                                </span>
                                                <span v-else class="text-gray-700 dark:text-gray-300">0</span>
                                                <span>/</span>
                                                <span>{{ store.subscription ? store.subscription.monthly_limit : 50 }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Created At -->
                                    <td class="px-6 py-5 whitespace-nowrap text-xs font-mono text-gray-400 dark:text-gray-500">
                                        {{ store.created_at }}
                                    </td>

                                    <!-- Action Buttons -->
                                    <td class="px-6 py-5 whitespace-nowrap text-left">
                                        <div class="flex items-center justify-end gap-2">
                                            <button 
                                                @click="openSimulator(store)"
                                                class="inline-flex items-center justify-center px-4 py-2 border border-indigo-500/20 bg-indigo-500/10 text-indigo-650 hover:bg-indigo-500/20 dark:text-indigo-400 dark:hover:bg-indigo-500/30 rounded-2xl text-xs font-extrabold transition-all duration-200 transform hover:scale-[1.03] active:scale-[0.97]"
                                            >
                                                محاكاة الفوترة
                                            </button>
                                            <button 
                                                @click="toggleStoreStatus(store)"
                                                :disabled="togglingStoreId === store.id"
                                                :class="store.status === 'active' 
                                                    ? 'bg-rose-500/10 border-rose-500/20 text-rose-600 hover:bg-rose-500/20 hover:text-rose-700 dark:text-rose-450 dark:hover:bg-rose-500/30' 
                                                    : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 hover:bg-emerald-500/20 hover:text-emerald-700 dark:text-emerald-450 dark:hover:bg-emerald-500/30'"
                                                class="inline-flex items-center justify-center px-4 py-2 border rounded-2xl text-xs font-extrabold transition-all duration-200 transform hover:scale-[1.03] active:scale-[0.97] disabled:opacity-50"
                                            >
                                                <span v-if="togglingStoreId === store.id" class="flex items-center gap-1.5">
                                                    <svg class="animate-spin h-3.5 w-3.5 text-current" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    جاري...
                                                </span>
                                                <span v-else>
                                                    {{ store.status === 'active' ? 'تعطيل الحساب' : 'تفعيل الحساب' }}
                                                </span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Billing & Subscription Simulator Modal (Apple Glassmorphism Dialog) -->
                <transition name="fade">
                    <div v-if="showSimModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                        <div class="bg-white dark:bg-[#1C1C1E] border border-gray-100 dark:border-gray-800 rounded-3xl max-w-md w-full overflow-hidden shadow-2xl p-6 space-y-6 text-right" dir="rtl">
                            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-850 pb-4">
                                <h3 class="text-lg font-extrabold text-gray-900 dark:text-gray-100">
                                    محاكي الفوترة: {{ selectedStoreForSim?.name }}
                                </h3>
                                <button @click="showSimModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <!-- Plan Selection -->
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">باقة الاشتراك</label>
                                <select 
                                    v-model="simPlanName"
                                    class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200/60 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-[#2C2C2E]/60 dark:border-gray-800 dark:text-gray-100 font-semibold"
                                >
                                    <option value="free">المجانية (Free) - 50 رسالة/شهر - علامة مائية</option>
                                    <option value="startup">الناشئة (Startup) - 400 رسالة/شهر - بدون علامة مائية</option>
                                    <option value="growth">النمو (Growth) - 1000 رسالة/شهر - بدون علامة مائية</option>
                                </select>
                            </div>

                            <!-- Status Selection -->
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">حالة الاشتراك</label>
                                <select 
                                    v-model="simStatus"
                                    class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200/60 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-[#2C2C2E]/60 dark:border-gray-800 dark:text-gray-100 font-semibold"
                                >
                                    <option value="active">نشط (Active)</option>
                                    <option value="expired">منتهي (Expired)</option>
                                    <option value="suspended">موقوف مؤقتاً (Suspended)</option>
                                </select>
                            </div>

                            <!-- Usage count input -->
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">الرسائل المستهلكة بالفترة الحالية</label>
                                <input 
                                    type="number" 
                                    v-model.number="simUsage"
                                    min="0"
                                    class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200/60 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-[#2C2C2E]/60 dark:border-gray-800 dark:text-gray-100 font-mono font-semibold"
                                />
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <button 
                                    @click="submitManualSubscription"
                                    :disabled="simLoading || simWebhookLoading"
                                    class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-xs font-extrabold transition-all duration-200 disabled:opacity-50"
                                >
                                    <span v-if="simLoading" class="animate-spin h-3.5 w-3.5 border-2 border-white border-t-transparent rounded-full mr-2"></span>
                                    تحديث يدوي
                                </button>
                                <button 
                                    @click="triggerWebhookSimulation"
                                    :disabled="simLoading || simWebhookLoading"
                                    class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs font-extrabold transition-all duration-200 disabled:opacity-50"
                                >
                                    <span v-if="simWebhookLoading" class="animate-spin h-3.5 w-3.5 border-2 border-white border-t-transparent rounded-full mr-2"></span>
                                    محاكاة ويب هوك الدفع (Tap)
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
