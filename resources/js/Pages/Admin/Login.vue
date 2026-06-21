<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

defineProps<{
    status?: string;
}>();

// Super Admin login form configuration
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('admin.login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="بوابة إدارة النظام - سوبر أدمن" />

    <div class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B0F19] via-[#111827] to-[#030712] overflow-hidden font-sans text-gray-100">
        
        <!-- Ambient Decorative Glowing Circles (Dark Cyberpunk Theme) -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none animate-pulse"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-violet-500/10 rounded-full blur-[120px] pointer-events-none animate-pulse" style="animation-delay: 2s;"></div>
        
        <!-- Grid Backdrop Pattern -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff02_1px,transparent_1px),linear-gradient(to_bottom,#ffffff02_1px,transparent_1px)] bg-[size:32px_32px] pointer-events-none"></div>

        <div class="relative w-full max-w-md px-6 py-12 z-10 animate-fade-in">
            
            <!-- Brand Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-3xl font-extrabold mb-4 shadow-[0_0_20px_rgba(99,102,241,0.1)]">
                    CT
                </div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Conversion Trust</h1>
                <p class="mt-2 text-sm text-indigo-300/80 font-bold">بوابة إدارة النظام الأمنية (Super Admin)</p>
            </div>

            <!-- Validation Status -->
            <div v-if="status" class="mb-4 text-sm font-semibold text-emerald-400 text-center bg-emerald-500/10 border border-emerald-500/20 p-3 rounded-xl">
                {{ status }}
            </div>

            <!-- Glassmorphic Login Form Container -->
            <div class="bg-slate-900/60 backdrop-blur-2xl border border-white/5 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] overflow-hidden" dir="rtl">
                <div class="p-8">
                    <h2 class="text-lg font-extrabold text-white mb-6 text-right">تسجيل الدخول للمسؤولين</h2>

                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Email Input -->
                        <div>
                            <label for="email" class="block text-xs font-bold text-gray-400 mb-2">البريد الإلكتروني للادارة</label>
                            <div class="relative">
                                <input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    class="w-full px-4 py-3 bg-slate-950/40 border border-white/10 rounded-2xl text-sm text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/50 transition duration-300 placeholder-gray-600 text-right"
                                    placeholder="admin@example.com"
                                    required
                                    autofocus
                                    autocomplete="username"
                                />
                            </div>
                            <InputError class="mt-2 text-right" :message="form.errors.email" />
                        </div>

                        <!-- Password Input -->
                        <div>
                            <label for="password" class="block text-xs font-bold text-gray-400 mb-2">كلمة المرور الأمنية</label>
                            <input
                                id="password"
                                type="password"
                                v-model="form.password"
                                class="w-full px-4 py-3 bg-slate-950/40 border border-white/10 rounded-2xl text-sm text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/50 transition duration-300 placeholder-gray-700 text-right"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                            />
                            <InputError class="mt-2 text-right" :message="form.errors.password" />
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.remember"
                                    class="rounded bg-slate-950/50 border-white/10 text-indigo-600 focus:ring-indigo-500/50 focus:ring-offset-slate-900 h-4 w-4"
                                />
                                <span class="ms-2 text-xs font-bold text-gray-400">تذكرني على هذا الجهاز</span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-bold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-500 transition shadow-[0_4px_20px_0_rgba(99,102,241,0.25)] hover:shadow-[0_4px_24px_0_rgba(99,102,241,0.4)] disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                <svg v-if="form.processing" class="animate-spin -ms-1 me-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                الدخول الآمن للوحة التحكم
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Back to Merchant Login Link -->
            <div class="text-center mt-8">
                <Link href="/" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-400 hover:text-indigo-400 transition-colors duration-300">
                    <span class="rotate-180 inline-block">→</span>
                    بوابة دخول التجار (الرئيسية)
                </Link>
            </div>
            
        </div>
    </div>
</template>

<style scoped>
/* Smooth element fading animations */
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
