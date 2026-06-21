<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
    status?: string;
}>();

// Page authentication helper
const page = usePage();
const user = computed(() => page.props.auth ? (page.props.auth as any).user : null);
const flash = computed(() => (page.props.flash as any) || {});

// Navigation / Form view state
const isRegister = ref(false);
const showForgotPassword = ref(false);

// Register Form Config
const registerForm = useForm({
    name: '',
    email: '',
    code: '',
    password: '',
    password_confirmation: '',
    token: '',
});

const requestLinkForm = useForm({
    email: '',
});

const otpSentSuccessfully = ref(false);
const otpSuccessMessage = ref('');
const otpError = ref('');

const countdown = ref(0);
let countdownIntervalId: any = null;

const startCountdown = () => {
    countdown.value = 60;
    if (countdownIntervalId) clearInterval(countdownIntervalId);
    countdownIntervalId = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value--;
        } else {
            clearInterval(countdownIntervalId);
        }
    }, 1000);
};

onUnmounted(() => {
    if (countdownIntervalId) clearInterval(countdownIntervalId);
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'forgot-password' || params.has('forgot-password')) {
        showForgotPassword.value = true;
    }

    // Auto-fill register form if coming from the email link
    const action = params.get('action');
    const otpToken = params.get('otp_token') || params.get('token');
    const email = params.get('email');
    if (action === 'register' || otpToken) {
        isRegister.value = true;
        if (email) registerForm.email = email;
        if (otpToken) registerForm.token = otpToken;
        otpSentSuccessfully.value = true;
        otpSuccessMessage.value = 'تم ملء البريد الإلكتروني تلقائياً. يرجى إدخال رمز التأكيد وباقي البيانات.';
    }
});

// Login Form Config
const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const submitLogin = () => {
    loginForm.post(route('login'), {
        onFinish: () => {
            loginForm.reset('password');
        },
    });
};

const submitRequestLink = () => {
    otpError.value = '';
    otpSuccessMessage.value = '';
    requestLinkForm.email = registerForm.email;
    
    requestLinkForm.post(route('register.request-link'), {
        onSuccess: (page: any) => {
            const flash = page.props.flash as any;
            if (flash && flash.success) {
                otpSentSuccessfully.value = true;
                otpSuccessMessage.value = flash.success;
                registerForm.token = flash.otp_token || '';
                startCountdown();
            } else if (flash && flash.error) {
                otpError.value = flash.error;
            }
        },
        onError: (errors: any) => {
            otpError.value = errors.email || 'حدث خطأ أثناء إرسال الكود.';
        }
    });
};

const submitRegister = () => {
    // If the token is available in flash, assign it
    if (page.props.flash && (page.props.flash as any).otp_token) {
        registerForm.token = (page.props.flash as any).otp_token;
    }
    registerForm.post(route('register'), {
        onFinish: () => {
            registerForm.reset('password', 'password_confirmation');
        },
    });
};

// Forgot Password Form Config
const forgotPasswordForm = useForm({
    email: '',
    code: '',
    password: '',
    password_confirmation: '',
});
const forgotPasswordStage = ref(1);

const submitForgotPassword = () => {
    if (forgotPasswordStage.value === 1) {
        forgotPasswordForm.post(route('password.email'), {
            onSuccess: () => {
                forgotPasswordStage.value = 2;
            },
        });
    } else {
        forgotPasswordForm.post(route('password.reset.otp'), {
            onSuccess: () => {
                showForgotPassword.value = false;
                forgotPasswordStage.value = 1;
                forgotPasswordForm.reset();
            },
            onFinish: () => {
                forgotPasswordForm.reset('password', 'password_confirmation');
            }
        });
    }
};
</script>

<template>
    <Head title="مرحباً بك في Conversion Trust" />

    <div class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-[#F8FAFC] via-[#F1F5F9] to-[#E2E8F0] overflow-hidden font-sans">
        
        <!-- Ambient Decorative Glowing Circles (Light Theme) -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-[100px] pointer-events-none animate-pulse"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-blue-500/10 rounded-full blur-[100px] pointer-events-none animate-pulse" style="animation-delay: 2s;"></div>
        
        <!-- Subtle backdrop grid -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#0f172a05_1px,transparent_1px),linear-gradient(to_bottom,#0f172a05_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>

        <div class="relative w-full max-w-lg px-6 py-12 z-10">
            
            <!-- Brand Logo / Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Conversion Trust</h1>
                <p class="mt-2 text-sm text-slate-500 font-semibold">منصة إدارة مراجعات المتاجر والتواصل الذكي</p>
            </div>

            <!-- Logged In User State -->
            <div v-if="user" class="bg-white/80 backdrop-blur-xl border border-slate-200/50 rounded-3xl p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] text-center text-right" dir="rtl">
                <div class="flex flex-col items-center mb-6">
                    <div class="h-16 w-16 rounded-full bg-indigo-600/10 text-indigo-600 flex items-center justify-center text-2xl font-bold mb-4 border border-indigo-600/20">
                        {{ user.name[0].toUpperCase() }}
                    </div>
                    <h3 class="text-xl font-bold text-slate-855">أهلاً بك مجدداً، {{ user.name }}!</h3>
                    <p class="text-sm text-slate-500 mt-1">{{ user.email }}</p>
                </div>
                <div class="space-y-4">
                    <Link :href="route('dashboard')" class="w-full flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-bold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-[0_4px_20px_0_rgba(99,102,241,0.2)]">
                        الانتقال إلى لوحة التحكم
                    </Link>
                </div>
            </div>

            <!-- Login & Register Unified Card (Light Theme) -->
            <div v-else class="bg-white/80 backdrop-blur-2xl border border-slate-200/60 rounded-3xl shadow-[0_12px_40px_rgba(0,0,0,0.06)] overflow-hidden transition-all duration-500" dir="rtl">
                
                <!-- Toggle Tabs -->
                <div v-if="!showForgotPassword" class="flex border-b border-slate-100 bg-slate-50/50 p-2">
                    <button 
                        @click="isRegister = false" 
                        class="flex-1 py-3 text-sm font-bold rounded-xl transition-all duration-300"
                        :class="!isRegister ? 'bg-indigo-600 text-white shadow-[0_4px_12px_rgba(99,102,241,0.25)]' : 'text-slate-500 hover:text-slate-900'"
                    >
                        تسجيل الدخول
                    </button>
                    <button 
                        @click="isRegister = true" 
                        class="flex-1 py-3 text-sm font-bold rounded-xl transition-all duration-300"
                        :class="isRegister ? 'bg-indigo-600 text-white shadow-[0_4px_12px_rgba(99,102,241,0.25)]' : 'text-slate-500 hover:text-slate-900'"
                    >
                        إنشاء حساب جديد
                    </button>
                </div>

                <div class="p-8">
                    <!-- ===== LOGIN FORM ===== -->
                    <div v-if="!isRegister && !showForgotPassword" class="space-y-5 animate-fade-in">
                        <div class="text-right mb-4">
                            <h2 class="text-xl font-bold text-slate-800">مرحباً بك مجدداً!</h2>
                            <p class="text-xs text-slate-500 mt-1">قم بتسجيل الدخول للوصول إلى متجرك والتحكم بالتقييمات</p>
                        </div>

                        <!-- Status Flash / Success Message -->
                        <div v-if="status" class="p-4 mb-4 text-sm text-green-800 rounded-2xl bg-green-50 font-semibold text-right" dir="rtl">
                            {{ status }}
                        </div>

                        <form @submit.prevent="submitLogin" class="space-y-4">
                            <div>
                                <InputLabel for="email" value="البريد الإلكتروني" class="text-slate-700 font-semibold mb-1" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="loginForm.email"
                                    required
                                    autofocus
                                    placeholder="name@example.com"
                                    autocomplete="username"
                                />
                                <InputError class="mt-2" :message="loginForm.errors.email" />
                            </div>

                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <InputLabel for="password" value="كلمة المرور" class="text-slate-700 font-semibold" />
                                    <button 
                                        type="button" 
                                        @click="showForgotPassword = true" 
                                        class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold focus:outline-none"
                                    >
                                        نسيت كلمة المرور؟
                                    </button>
                                </div>
                                <TextInput
                                    id="password"
                                    type="password"
                                    class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="loginForm.password"
                                    required
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                />
                                <InputError class="mt-2" :message="loginForm.errors.password" />
                            </div>

                            <div class="block">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <Checkbox name="remember" v-model:checked="loginForm.remember" class="rounded bg-slate-50 border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-xs text-slate-600 font-bold">تذكرني على هذا الجهاز</span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                class="w-full flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-bold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-[0_4px_16px_0_rgba(99,102,241,0.2)] disabled:opacity-50 mt-2"
                                :disabled="loginForm.processing"
                            >
                                <svg v-if="loginForm.processing" class="animate-spin -ms-1 me-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                تسجيل الدخول
                            </button>
                        </form>
                    </div>

                    <!-- ===== REGISTER FORM ===== -->
                    <div v-else-if="isRegister && !showForgotPassword" class="space-y-5 animate-fade-in">
                        <div class="text-right mb-4">
                            <h2 class="text-xl font-bold text-slate-800">ابدأ رحلتك معنا!</h2>
                            <p class="text-xs text-slate-500 mt-1">أنشئ حساباً جديداً لربط متجرك وبدء أتمتة مراجعات العملاء</p>
                        </div>

                        <!-- Flash Messages -->
                        <div v-if="flash.error" class="p-4 mb-4 text-sm text-red-800 rounded-2xl bg-red-50 font-semibold text-right animate-fade-in" dir="rtl">
                            {{ flash.error }}
                        </div>

                        <form @submit.prevent="submitRegister" class="space-y-4 text-right" dir="rtl">
                            <!-- Full Name -->
                            <div>
                                <InputLabel for="reg_name" value="الاسم الكامل" class="text-slate-700 font-semibold mb-1" />
                                <TextInput
                                    id="reg_name"
                                    type="text"
                                    class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="registerForm.name"
                                    required
                                    placeholder="عبد الله محمد"
                                    autocomplete="name"
                                />
                                <InputError class="mt-2" :message="registerForm.errors.name" />
                            </div>

                            <!-- Email -->
                            <div>
                                <InputLabel for="reg_email" value="البريد الإلكتروني" class="text-slate-700 font-semibold mb-1" />
                                <div class="flex gap-2">
                                    <TextInput
                                        id="reg_email"
                                        type="email"
                                        class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{'bg-slate-100/85 text-slate-550 cursor-not-allowed': otpSentSuccessfully}"
                                        v-model="registerForm.email"
                                        required
                                        placeholder="name@example.com"
                                        :disabled="otpSentSuccessfully"
                                    />
                                    <button
                                        type="button"
                                        @click="submitRequestLink"
                                        class="mt-1 px-4 py-2 border border-indigo-600 text-xs font-bold rounded-2xl text-indigo-600 hover:bg-indigo-50 transition focus:outline-none disabled:opacity-50 flex items-center justify-center shrink-0 min-w-[100px]"
                                        :disabled="requestLinkForm.processing || countdown > 0"
                                    >
                                        <svg v-if="requestLinkForm.processing" class="animate-spin -ms-1 me-2 h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        <span v-if="countdown > 0">
                                            الانتظار {{ countdown }}ث
                                        </span>
                                        <span v-else>
                                            {{ otpSentSuccessfully ? 'إعادة إرسال' : 'تحقيق' }}
                                        </span>
                                    </button>
                                </div>
                                <InputError class="mt-2" :message="registerForm.errors.email || requestLinkForm.errors.email" />
                                
                                <!-- OTP Code Status Messages -->
                                <p v-if="otpSuccessMessage" class="mt-2 text-xs text-emerald-600 font-semibold leading-relaxed">
                                    {{ otpSuccessMessage }}
                                </p>
                                <p v-if="otpError" class="mt-2 text-xs text-rose-600 font-semibold leading-relaxed">
                                    {{ otpError }}
                                </p>
                            </div>

                            <!-- Verification Code (OTP) -->
                            <div v-if="otpSentSuccessfully" class="animate-fade-in">
                                <InputLabel for="reg_code" value="رمز التأكيد (6 أرقام)" class="text-slate-700 font-semibold mb-1" />
                                <TextInput
                                    id="reg_code"
                                    type="text"
                                    maxlength="6"
                                    class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 text-center font-bold tracking-widest text-lg"
                                    v-model="registerForm.code"
                                    required
                                    placeholder="000000"
                                />
                                <InputError class="mt-2" :message="registerForm.errors.code" />
                            </div>

                            <!-- Password -->
                            <div>
                                <InputLabel for="reg_password" value="كلمة المرور" class="text-slate-700 font-semibold mb-1" />
                                <TextInput
                                    id="reg_password"
                                    type="password"
                                    class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="registerForm.password"
                                    required
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="registerForm.errors.password" />
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <InputLabel for="reg_password_confirmation" value="تأكيد كلمة المرور" class="text-slate-700 font-semibold mb-1" />
                                <TextInput
                                    id="reg_password_confirmation"
                                    type="password"
                                    class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="registerForm.password_confirmation"
                                    required
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="registerForm.errors.password_confirmation" />
                            </div>

                            <!-- Submit Register Button -->
                            <button
                                type="submit"
                                class="w-full flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-bold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-[0_4px_16px_0_rgba(99,102,241,0.2)] disabled:opacity-50 mt-4"
                                :disabled="registerForm.processing || (otpSentSuccessfully && !registerForm.code)"
                            >
                                <svg v-if="registerForm.processing" class="animate-spin -ms-1 me-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                إنشاء حساب جديد
                            </button>
                        </form>
                    </div>

                    <!-- ===== FORGOT PASSWORD FORM ===== -->
                    <div v-else-if="showForgotPassword" class="space-y-5 animate-fade-in">
                        <div class="text-right mb-4">
                            <h2 class="text-xl font-bold text-slate-800">استعادة كلمة المرور</h2>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ forgotPasswordStage === 1 ? 'أدخل بريدك الإلكتروني لإرسال رمز التحقق' : 'أدخل رمز التحقق المرسل وكلمة المرور الجديدة' }}
                            </p>
                        </div>

                        <!-- Flash Messages -->
                        <div v-if="flash.success" class="p-4 mb-4 text-sm text-green-800 rounded-2xl bg-green-50 font-semibold text-right animate-fade-in" dir="rtl">
                            {{ flash.success }}
                        </div>
                        <div v-if="flash.error" class="p-4 mb-4 text-sm text-red-800 rounded-2xl bg-red-50 font-semibold text-right animate-fade-in" dir="rtl">
                            {{ flash.error }}
                        </div>

                        <form @submit.prevent="submitForgotPassword" class="space-y-4">
                            <!-- Stage 1: Email Input -->
                            <div v-if="forgotPasswordStage === 1">
                                <InputLabel for="forgot_email" value="البريد الإلكتروني" class="text-slate-700 font-semibold mb-1" />
                                <TextInput
                                    id="forgot_email"
                                    type="email"
                                    class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                    v-model="forgotPasswordForm.email"
                                    required
                                    autofocus
                                    placeholder="name@example.com"
                                    autocomplete="username"
                                />
                                <InputError class="mt-2" :message="forgotPasswordForm.errors.email" />
                            </div>

                            <!-- Stage 2: Code and New Password -->
                            <div v-else class="space-y-4 animate-fade-in">
                                <div>
                                    <InputLabel for="forgot_email_disabled" value="البريد الإلكتروني" class="text-slate-400 font-semibold mb-1" />
                                    <TextInput
                                        id="forgot_email_disabled"
                                        type="email"
                                        class="mt-1 block w-full rounded-2xl bg-slate-100 border-slate-200 text-slate-500 cursor-not-allowed"
                                        v-model="forgotPasswordForm.email"
                                        disabled
                                    />
                                </div>

                                <div>
                                    <InputLabel for="forgot_code" value="رمز التأكيد المرسل (6 أرقام)" class="text-slate-700 font-semibold mb-1" />
                                    <TextInput
                                        id="forgot_code"
                                        type="text"
                                        maxlength="6"
                                        class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 text-center font-bold tracking-widest text-lg"
                                        v-model="forgotPasswordForm.code"
                                        required
                                        placeholder="000000"
                                    />
                                    <InputError class="mt-2" :message="forgotPasswordForm.errors.code" />
                                </div>

                                <div>
                                    <InputLabel for="forgot_password" value="كلمة المرور الجديدة" class="text-slate-700 font-semibold mb-1" />
                                    <TextInput
                                        id="forgot_password"
                                        type="password"
                                        class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                        v-model="forgotPasswordForm.password"
                                        required
                                        placeholder="••••••••"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-2" :message="forgotPasswordForm.errors.password" />
                                </div>

                                <div>
                                    <InputLabel for="forgot_password_confirmation" value="تأكيد كلمة المرور الجديدة" class="text-slate-700 font-semibold mb-1" />
                                    <TextInput
                                        id="forgot_password_confirmation"
                                        type="password"
                                        class="mt-1 block w-full rounded-2xl bg-slate-50/80 border-slate-200 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                        v-model="forgotPasswordForm.password_confirmation"
                                        required
                                        placeholder="••••••••"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-2" :message="forgotPasswordForm.errors.password_confirmation" />
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="w-full flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-bold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-[0_4px_16px_0_rgba(99,102,241,0.2)] disabled:opacity-50 mt-2"
                                :disabled="forgotPasswordForm.processing"
                            >
                                <svg v-if="forgotPasswordForm.processing" class="animate-spin -ms-1 me-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                {{ forgotPasswordStage === 1 ? 'إرسال رمز استعادة كلمة المرور' : 'تحديث كلمة المرور' }}
                            </button>

                            <div class="text-center mt-4">
                                <button
                                    type="button"
                                    @click="showForgotPassword = false; isRegister = false; forgotPasswordStage = 1; forgotPasswordForm.reset();"
                                    class="text-sm text-indigo-600 hover:text-indigo-700 font-bold focus:outline-none"
                                >
                                    العودة لتسجيل الدخول
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- Footer Details -->
            <div class="text-center mt-8 text-xs text-slate-400 font-semibold">
                Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})
            </div>

        </div>
    </div>
</template>

<style>
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
