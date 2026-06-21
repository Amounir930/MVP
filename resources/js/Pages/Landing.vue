<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth ? (page.props.auth as any).user : null);

// Default to Light Mode (false) as requested by the user
const isDark = ref(false);
</script>

<template>
    <Head title="Conversion Trust - وثق تقييمات متجرك وضاعف مبيعاتك" />

    <div dir="rtl" :class="[
        'min-h-screen font-sans relative overflow-hidden transition-colors duration-500',
        isDark ? 'bg-slate-950 text-slate-100 selection:bg-indigo-500 selection:text-white' : 'bg-gradient-to-br from-slate-50 via-slate-100/70 to-slate-200/50 text-slate-800 selection:bg-indigo-600 selection:text-white'
    ]">
        <!-- Background decorative glows -->
        <div :class="[
            'absolute top-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full blur-[120px] pointer-events-none transition-all duration-500',
            isDark ? 'bg-indigo-900/20' : 'bg-indigo-500/10'
        ]"></div>
        <div :class="[
            'absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full blur-[120px] pointer-events-none transition-all duration-500',
            isDark ? 'bg-violet-950/30' : 'bg-violet-500/10'
        ]"></div>

        <!-- Subtle backdrop grid -->
        <div :class="[
            'absolute inset-0 bg-[size:32px_32px] pointer-events-none transition-all duration-500',
            isDark ? 'bg-[linear-gradient(to_right,#ffffff02_1px,transparent_1px),linear-gradient(to_bottom,#ffffff02_1px,transparent_1px)]' : 'bg-[linear-gradient(to_right,#0f172a04_1px,transparent_1px),linear-gradient(to_bottom,#0f172a04_1px,transparent_1px)]'
        ]"></div>

        <!-- Navbar -->
        <header :class="[
            'sticky top-0 z-50 backdrop-blur-md transition-all duration-500',
            isDark ? 'bg-slate-950/75 border-b border-slate-900/80' : 'bg-white/75 border-b border-slate-200/80 shadow-sm'
        ]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-between p-2 shadow-lg shadow-indigo-500/20 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 sm:w-6 sm:h-6 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                    </div>
                    <span :class="[
                        'text-[14px] sm:text-xl font-extrabold tracking-wide bg-gradient-to-r bg-clip-text text-transparent transition-all duration-500 whitespace-nowrap',
                        isDark ? 'from-white via-slate-100 to-indigo-400' : 'from-slate-900 via-slate-800 to-indigo-600'
                    ]">Conversion Trust</span>
                </div>

                <!-- Navigation links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-bold">
                    <a href="#features" :class="[
                        'transition-colors duration-200',
                        isDark ? 'text-slate-300 hover:text-white' : 'text-slate-600 hover:text-indigo-600'
                    ]">الميزات</a>
                    <a href="#pricing" :class="[
                        'transition-colors duration-200',
                        isDark ? 'text-slate-300 hover:text-white' : 'text-slate-600 hover:text-indigo-600'
                    ]">الأسعار</a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Light / Dark Mode Toggle Button -->
                    <button @click="isDark = !isDark" :class="[
                        'p-2 sm:p-2.5 rounded-xl border transition-all duration-300 flex items-center justify-center focus:outline-none shrink-0',
                        isDark ? 'border-slate-800 bg-slate-900/40 text-slate-300 hover:text-white' : 'border-slate-200 bg-white text-slate-600 hover:text-indigo-600 hover:border-indigo-200 shadow-sm'
                    ]">
                        <!-- Moon Icon for Light Mode -->
                        <svg v-if="!isDark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 sm:w-5 sm:h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                        <!-- Sun Icon for Dark Mode -->
                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 sm:w-5 sm:h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m0 13.5V21M5.25 12H3m16.035-6.035-1.591 1.591M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </button>

                    <template v-if="user">
                        <Link :href="route('dashboard')" class="px-3 sm:px-5 h-10 sm:h-11 flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs sm:text-sm transition-all duration-300 shadow-lg shadow-indigo-500/10 whitespace-nowrap">
                            لوحة التحكم
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" :class="[
                            'hidden sm:flex items-center text-sm font-bold transition-colors duration-200 whitespace-nowrap',
                            isDark ? 'text-slate-300 hover:text-white' : 'text-slate-600 hover:text-indigo-600'
                        ]">
                            تسجيل دخول التجار
                        </Link>
                        <Link :href="route('login', { action: 'register' })" class="px-3 sm:px-5 h-10 sm:h-11 flex items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold text-xs sm:text-sm transition-all duration-300 shadow-lg shadow-indigo-500/25 whitespace-nowrap">
                            ابدأ مجاناً
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative pt-12 pb-20 md:pt-32 md:pb-36 max-w-7xl mx-auto px-4 sm:px-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
                <!-- Left: Text content -->
                <div class="lg:col-span-7 text-right flex flex-col items-start lg:items-start order-1 lg:order-1">
                    <div :class="[
                        'inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold mb-6 self-start lg:self-auto transition-all duration-300',
                        isDark ? 'bg-indigo-500/10 border border-indigo-500/25 text-indigo-400' : 'bg-indigo-50 border border-indigo-100 text-indigo-600'
                    ]">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                        أتمتة مراجعات متجر سلة بالكامل
                    </div>
                    
                    <h1 :class="[
                        'text-3xl sm:text-5xl lg:text-6xl font-extrabold leading-[1.3] mb-6 tracking-tight text-right transition-colors duration-500',
                        isDark ? 'text-white' : 'text-slate-900'
                    ]">
                        ضاعف مبيعاتك وثقة عملائك عبر 
                        <span class="bg-gradient-to-r from-indigo-500 via-violet-500 to-purple-500 bg-clip-text text-transparent block mt-2">
                            توثيق التقييمات تلقائياً
                        </span>
                    </h1>

                    <p :class="[
                        'text-sm sm:text-lg leading-relaxed mb-8 sm:mb-10 max-w-2xl text-right transition-colors duration-500',
                        isDark ? 'text-slate-400' : 'text-slate-600'
                    ]">
                        تتيح لك منصة Conversion Trust إرسال رسائل تقييم تفاعلية ومؤتمتة لعملائك عبر واتساب بعد تسليم الطلب مباشرة. اجمع التقييمات، الصور، الفيديوهات، واعرضها في متجرك داخل نافذة Shadow DOM أنيقة ومحمية لتبهر زوار متجرك وتزيد مبيعاتك.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto justify-start">
                        <Link :href="route('login', { action: 'register' })" class="px-8 h-14 flex items-center justify-center rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-base transition-all duration-300 shadow-xl shadow-indigo-600/20 hover:scale-[1.02] whitespace-nowrap">
                            ابدأ تجربتك المجانية الآن
                        </Link>
                        <Link :href="route('login')" :class="[
                            'px-8 h-14 flex items-center justify-center rounded-2xl border font-bold text-base transition-all duration-300 hover:scale-[1.02] whitespace-nowrap',
                            isDark ? 'bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white border-slate-800' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200 shadow-sm'
                        ]">
                            تسجيل الدخول
                        </Link>
                    </div>

                    <!-- Trust factors -->
                    <div :class="[
                        'grid grid-cols-3 gap-4 sm:flex sm:flex-wrap lg:flex-nowrap items-center justify-between sm:justify-start lg:justify-start sm:gap-8 mt-12 border-t pt-8 w-full transition-colors duration-500',
                        isDark ? 'border-slate-900' : 'border-slate-200'
                    ]">
                        <div class="text-center sm:text-right flex flex-col justify-center">
                            <div :class="['text-lg sm:text-2xl font-extrabold transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">30,000+</div>
                            <div class="text-[9px] sm:text-xs text-slate-500 font-bold mt-1">تقييم موثق</div>
                        </div>
                        <div :class="['hidden sm:block w-px h-10 transition-colors duration-500', isDark ? 'bg-slate-800' : 'bg-slate-200']"></div>
                        <div class="text-center sm:text-right flex flex-col justify-center">
                            <div :class="['text-lg sm:text-2xl font-extrabold transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">1,500+</div>
                            <div class="text-[9px] sm:text-xs text-slate-500 font-bold mt-1">تاجر نشط</div>
                        </div>
                        <div :class="['hidden sm:block w-px h-10 transition-colors duration-500', isDark ? 'bg-slate-800' : 'bg-slate-200']"></div>
                        <div class="text-center sm:text-right flex flex-col justify-center">
                            <div :class="['text-lg sm:text-2xl font-extrabold transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">99.9%</div>
                            <div class="text-[9px] sm:text-xs text-slate-500 font-bold mt-1 leading-normal">نسبة جهوزية الإرسال</div>
                        </div>
                    </div>
                </div>

                <!-- Right: Visual mockup -->
                <div class="lg:col-span-5 relative w-full flex justify-center order-2 lg:order-2">
                    <div :class="[
                        'relative w-full max-w-[420px] aspect-auto lg:aspect-[4/5] rounded-[32px] border p-4 sm:p-6 backdrop-blur-sm overflow-hidden shadow-2xl transition-all duration-500 pb-6',
                        isDark ? 'border-slate-800 bg-slate-900/40 shadow-indigo-950/20' : 'border-slate-200/80 bg-white shadow-slate-200/50'
                    ]">
                        <!-- Top header of mockup -->
                        <div :class="[
                            'flex items-center justify-between pb-4 border-b mb-6 transition-colors duration-500',
                            isDark ? 'border-slate-800/80' : 'border-slate-200/80'
                        ]">
                            <div class="flex items-center gap-2">
                                <div :class="['w-3 h-3 rounded-full transition-colors duration-500', isDark ? 'bg-slate-700' : 'bg-slate-300']"></div>
                                <div :class="['w-3 h-3 rounded-full transition-colors duration-500', isDark ? 'bg-slate-700' : 'bg-slate-300']"></div>
                                <div :class="['w-3 h-3 rounded-full transition-colors duration-500', isDark ? 'bg-slate-700' : 'bg-slate-300']"></div>
                            </div>
                            <div class="text-xs font-semibold text-slate-500">لوحة التحكم التفاعلية</div>
                        </div>

                        <!-- Mockup dashboard stats -->
                        <div class="space-y-4">
                            <div :class="[
                                'h-16 rounded-2xl border p-4 flex items-center justify-between transition-all duration-500',
                                isDark ? 'bg-indigo-950/40 border-indigo-900/30' : 'bg-indigo-50/50 border-indigo-100/50 shadow-sm'
                            ]">
                                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-650">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z" />
                                    </svg>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-bold text-slate-500">حملات واتساب النشطة</div>
                                    <div :class="['text-sm font-extrabold mt-1 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-800']">2,854 رسالة مرسلة</div>
                                </div>
                            </div>

                            <!-- WhatsApp Mock chat -->
                            <div :class="[
                                'rounded-2xl p-4 border space-y-3 transition-all duration-500',
                                isDark ? 'bg-slate-950/50 border-slate-800' : 'bg-slate-50 border-slate-200'
                            ]">
                                <div class="flex gap-2.5 max-w-[85%]">
                                    <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors duration-500', isDark ? 'bg-slate-800 text-slate-400' : 'bg-slate-200 text-slate-600']">CT</div>
                                    <div :class="[
                                        'rounded-2xl rounded-tr-none p-3 text-xs leading-normal text-right transition-colors duration-500',
                                        isDark ? 'bg-slate-900 text-slate-300' : 'bg-white text-slate-700 border border-slate-200 shadow-sm'
                                    ]">
                                        مرحباً بك! كيف تقيم جودة المنتج الذي استلمته اليوم؟ (1 إلى 5 نجوم)
                                    </div>
                                </div>
                                <div class="flex gap-2.5 max-w-[80%] self-end justify-end ml-auto">
                                    <div class="bg-indigo-600 text-white rounded-2xl rounded-tl-none p-3 text-xs leading-normal text-right shadow-md shadow-indigo-600/10">
                                        ⭐⭐⭐⭐⭐ منتج فوق الممتاز وتوصيل سريع!
                                    </div>
                                </div>
                            </div>

                            <!-- Widget mockup representation -->
                            <div :class="[
                                'rounded-2xl p-4 border transition-all duration-500',
                                isDark ? 'bg-slate-950/30 border-slate-800/60' : 'bg-white border-slate-200 shadow-sm'
                            ]">
                                <div class="flex items-center gap-3 mb-2 justify-end">
                                    <span class="text-[10px] text-slate-500 font-semibold">قبل دقيقتين</span>
                                    <span :class="['text-xs font-bold transition-colors duration-500', isDark ? 'text-white' : 'text-slate-800']">خالد ع.</span>
                                    <div class="w-6 h-6 rounded-full bg-indigo-900/30 flex items-center justify-center text-[10px] font-bold text-indigo-500">خ</div>
                                </div>
                                <p :class="['text-[11px] text-right leading-relaxed transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-700']">
                                    جودة المنتجات والخدمة مذهلة جداً وسرعة الرد ممتازة. أنصح بالتعامل معهم.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" :class="[
            'py-24 border-t transition-all duration-500',
            isDark ? 'border-slate-900 bg-slate-950' : 'border-slate-200/80 bg-slate-50/50'
        ]">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 :class="['text-3xl sm:text-4xl font-extrabold mb-4 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">كل ما تحتاجه لبناء مصداقية متجرك</h2>
                    <p :class="['text-base leading-relaxed transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-600']">
                        نوفر لك حلولاً تقنية متكاملة تدمج متجرك في سلة مع نظام أتمتة الرسائل وخدمات الواتساب لعرض تقييمات موثقة بالكامل.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- Feature 1 -->
                    <div :class="[
                        'group p-8 rounded-3xl border transition-all duration-300 flex flex-col text-right',
                        isDark ? 'bg-slate-900/25 border-slate-800/80 hover:border-indigo-500/30' : 'bg-white border-slate-200/60 hover:border-indigo-300 hover:shadow-md shadow-sm'
                    ]">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 self-start">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 6 21c-.139.04-.277.05-.415.033a.75.75 0 0 1-.586-.641 4.482 4.482 0 0 0-.84-2.22C3.393 16.602 3 14.86 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                            </svg>
                        </div>
                        <h3 :class="['text-xl font-bold mb-3 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">شات بوت واتساب الذكي</h3>
                        <p :class="['text-sm leading-relaxed transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-600']">
                            يقوم النظام بطرح الأسئلة المخصصة التي تقوم بإعدادها بنفسك، ويطلب من المشتري التعليق النصي ورفع صورة أو فيديو للمنتج مباشرة في الواتساب.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div :class="[
                        'group p-8 rounded-3xl border transition-all duration-300 flex flex-col text-right',
                        isDark ? 'bg-slate-900/25 border-slate-800/80 hover:border-indigo-500/30' : 'bg-white border-slate-200/60 hover:border-indigo-300 hover:shadow-md shadow-sm'
                    ]">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 self-start">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9.75 16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                            </svg>
                        </div>
                        <h3 :class="['text-xl font-bold mb-3 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">عنصر الـ Widget المعزول</h3>
                        <p :class="['text-sm leading-relaxed transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-600']">
                            كود العرض يتم حقنه وعزله بالكامل داخل Shadow DOM لمنع أي تعارض في أكواد CSS مع القوالب الخاصة بسلة، ويعرض صور العملاء الدائرية بشكل أنيق.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div :class="[
                        'group p-8 rounded-3xl border transition-all duration-300 flex flex-col text-right',
                        isDark ? 'bg-slate-900/25 border-slate-800/80 hover:border-indigo-500/30' : 'bg-white border-slate-200/60 hover:border-indigo-300 hover:shadow-md shadow-sm'
                    ]">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 self-start">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h3 :class="['text-xl font-bold mb-3 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">تعدد المستأجرين وعزل تام</h3>
                        <p :class="['text-sm leading-relaxed transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-600']">
                            عزل تام لبيانات التجار بنسبة 100% عبر نطاق عالمي آمن يمنع أي تداخل أو وصول غير مصرح به للبيانات لتشعر بأقصى درجات الأمان.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div :class="[
                        'group p-8 rounded-3xl border transition-all duration-300 flex flex-col text-right',
                        isDark ? 'bg-slate-900/25 border-slate-800/80 hover:border-indigo-500/30' : 'bg-white border-slate-200/60 hover:border-indigo-300 hover:shadow-md shadow-sm'
                    ]">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 self-start">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                            </svg>
                        </div>
                        <h3 :class="['text-xl font-bold mb-3 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">تقارير وإحصاءات دقيقة</h3>
                        <p :class="['text-sm leading-relaxed transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-600']">
                            لوحة تحكم إحصائية تفاعلية تعرض معدلات الاستجابة، عدد الرسائل المرسلة، ومسار المراجعات بالتفصيل لتحسين أداء متجرك.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" :class="[
            'py-24 border-t transition-all duration-500',
            isDark ? 'border-slate-900 bg-slate-950/40' : 'border-slate-200/80 bg-white shadow-sm'
        ]">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 :class="['text-3xl sm:text-4xl font-extrabold mb-4 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">باقات مرنة تناسب حجم متجرك</h2>
                    <p :class="['text-slate-500 text-base leading-relaxed transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-600']">
                        ابدأ مجاناً وقم بالترقية متى رغبت في زيادة حجم أعمالك والوصول لميزات أتمتة غير محدودة.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch max-w-5xl mx-auto">
                    <!-- Pack 1: Free -->
                    <div :class="[
                        'rounded-3xl border p-8 flex flex-col justify-between text-right relative backdrop-blur-sm transition-all duration-300',
                        isDark ? 'border-slate-800 bg-slate-900/10' : 'border-slate-200/80 bg-slate-50/30 shadow-sm'
                    ]">
                        <div>
                            <span class="text-indigo-600 text-xs font-bold uppercase tracking-wider mb-2 block">البداية السريعة</span>
                            <h3 :class="['text-2xl font-extrabold mb-4 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">الباقة المجانية</h3>
                            <div class="flex items-baseline gap-1 justify-end mb-6">
                                <span :class="['text-4xl font-extrabold transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">0</span>
                                <span :class="['text-sm transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-500']">ريال / شهرياً</span>
                            </div>
                            <ul :class="[
                                'space-y-4 text-sm border-t pt-6 transition-colors duration-500',
                                isDark ? 'text-slate-400 border-slate-800/80' : 'text-slate-600 border-slate-200/80'
                            ]">
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>حد 50 رسالة تقييم شهرياً</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>شات بوت واتساب الأساسي</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>عنصر عرض المراجعات بالمتجر</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>ظهور علامة مائية للمنصة</span>
                                </li>
                            </ul>
                        </div>
                        <Link :href="route('login', { action: 'register' })" :class="[
                            'mt-8 px-6 h-12 flex items-center justify-center rounded-xl font-bold text-sm transition-all duration-300 border',
                            isDark ? 'bg-slate-900 hover:bg-slate-800 text-white border-slate-800' : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-200 shadow-sm'
                        ]">
                            سجل حسابك المجاني
                        </Link>
                    </div>

                    <!-- Pack 2: Startup (Featured) -->
                    <div :class="[
                        'rounded-3xl border-2 p-8 flex flex-col justify-between text-right relative shadow-xl transition-all duration-300',
                        isDark ? 'border-indigo-600 bg-slate-900/30 shadow-indigo-950/20' : 'border-indigo-600 bg-white shadow-lg shadow-indigo-100/50'
                    ]">
                        <div class="absolute top-0 right-1/2 translate-x-1/2 -translate-y-1/2 px-4 py-1 rounded-full bg-indigo-600 text-white text-xs font-bold">الأكثر طلباً</div>
                        <div>
                            <span class="text-indigo-550 text-xs font-bold uppercase tracking-wider mb-2 block">النمو السريع</span>
                            <h3 :class="['text-2xl font-extrabold mb-4 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">باقة انطلاق (Startup)</h3>
                            <div class="flex items-baseline gap-1 justify-end mb-6">
                                <span :class="['text-4xl font-extrabold transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">99</span>
                                <span :class="['text-sm transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-500']">ريال / شهرياً</span>
                            </div>
                            <ul :class="[
                                'space-y-4 text-sm border-t pt-6 transition-colors duration-500',
                                isDark ? 'text-slate-300 border-indigo-900/30' : 'text-slate-750 border-indigo-100/50'
                            ]">
                                <li class="flex items-center gap-2.5 justify-start font-bold" :class="isDark ? 'text-white' : 'text-slate-900'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>حد 1,000 رسالة تقييم شهرياً</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>إزالة العلامة المائية بالكامل</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>تخصيص كامل للأسئلة والشات بوت</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>مزامنة تلقائية مع متجر سلة</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>دعم تنزيل ميديا العملاء (الصور/الفيديو)</span>
                                </li>
                            </ul>
                        </div>
                        <Link :href="route('login', { action: 'register' })" class="mt-8 px-6 h-12 flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm transition-all duration-300 shadow-md shadow-indigo-600/10">
                            اشترك الآن
                        </Link>
                    </div>

                    <!-- Pack 3: Growth -->
                    <div :class="[
                        'rounded-3xl border p-8 flex flex-col justify-between text-right relative backdrop-blur-sm transition-all duration-300',
                        isDark ? 'border-slate-800 bg-slate-900/10' : 'border-slate-200/80 bg-slate-50/30 shadow-sm'
                    ]">
                        <div>
                            <span class="text-indigo-600 text-xs font-bold uppercase tracking-wider mb-2 block">المتاجر الكبيرة</span>
                            <h3 :class="['text-2xl font-extrabold mb-4 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">باقة النمو (Growth)</h3>
                            <div class="flex items-baseline gap-1 justify-end mb-6">
                                <span :class="['text-4xl font-extrabold transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">199</span>
                                <span :class="['text-sm transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-500']">ريال / شهرياً</span>
                            </div>
                            <ul :class="[
                                'space-y-4 text-sm border-t pt-6 transition-colors duration-500',
                                isDark ? 'text-slate-400 border-slate-800/80' : 'text-slate-600 border-slate-200/80'
                            ]">
                                <li class="flex items-center gap-2.5 justify-start font-bold" :class="isDark ? 'text-white' : 'text-slate-900'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>رسائل تقييم غير محدودة شهرياً</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>إزالة العلامة المائية بالكامل</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>تخصيص متقدم للشات بوت</span>
                                </li>
                                <li class="flex items-center gap-2.5 justify-start">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <span>دعم فني مخصص وأولوية مزامنة</span>
                                </li>
                            </ul>
                        </div>
                        <Link :href="route('login', { action: 'register' })" :class="[
                            'mt-8 px-6 h-12 flex items-center justify-center rounded-xl font-bold text-sm transition-all duration-300 border',
                            isDark ? 'bg-slate-900 hover:bg-slate-800 text-white border-slate-800' : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-200 shadow-sm'
                        ]">
                            اشترك الآن
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Final -->
        <section class="py-20 max-w-5xl mx-auto px-6 relative z-10">
            <div :class="[
                'rounded-3xl border p-12 text-center relative overflow-hidden transition-all duration-500',
                isDark ? 'bg-gradient-to-r from-indigo-900/40 via-purple-900/20 to-slate-900/50 border-indigo-900/20' : 'bg-gradient-to-r from-indigo-50/50 via-purple-50/30 to-slate-50/50 border-indigo-100/60 shadow-lg shadow-slate-100/40'
            ]">
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-indigo-800/5 via-transparent to-transparent pointer-events-none"></div>
                <h2 :class="['text-3xl sm:text-4xl font-extrabold mb-4 transition-colors duration-500', isDark ? 'text-white' : 'text-slate-900']">هل أنت جاهز لتكسب ثقة عملائك؟</h2>
                <p :class="['text-base max-w-2xl mx-auto mb-8 transition-colors duration-500', isDark ? 'text-slate-400' : 'text-slate-600']">
                    انضم لآلاف التجار في سلة الذين قاموا بزيادة نسب التحويل والمبيعات بفضل المراجعات والتقييمات الموثقة.
                </p>
                <Link :href="route('login', { action: 'register' })" class="inline-flex px-8 h-14 items-center justify-center rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-base transition-all duration-300 shadow-xl shadow-indigo-600/20 hover:scale-[1.02]">
                    ابدأ الآن مجاناً
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer :class="[
            'py-12 border-t text-center text-sm transition-all duration-500',
            isDark ? 'border-slate-900 bg-slate-950/85 text-slate-500' : 'border-slate-200 bg-slate-50 text-slate-500'
        ]">
            <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row justify-between items-center gap-6">
                <div>&copy; {{ new Date().getFullYear() }} Conversion Trust. جميع الحقوق محفوظة.</div>
                <div class="flex items-center gap-3">
                    <span>منصة موثوقة لأتمتة مراجعات المتاجر</span>
                    <div class="w-1.5 h-1.5 rounded-full bg-slate-700"></div>
                    <Link :href="route('admin.login')" class="hover:text-slate-400 transition-colors">السوبر أدمن</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
