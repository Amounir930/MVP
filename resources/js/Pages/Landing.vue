<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth ? (page.props.auth as any).user : null);
</script>

<template>
    <Head title="Conversion Trust - وثق تقييمات متجرك وضاعف مبيعاتك" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-indigo-500 selection:text-white relative overflow-hidden">
        <!-- Background decorative glows -->
        <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-indigo-900/20 blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-violet-950/30 blur-[120px] pointer-events-none"></div>

        <!-- Navbar -->
        <header class="sticky top-0 z-50 backdrop-blur-md bg-slate-950/75 border-b border-slate-800/80 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-between p-2 shadow-lg shadow-indigo-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold tracking-wide bg-gradient-to-r from-white via-slate-100 to-indigo-400 bg-clip-text text-transparent">Conversion Trust</span>
                </div>

                <!-- Navigation links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-300">
                    <a href="#features" class="hover:text-white transition-colors duration-200">الميزات</a>
                    <a href="#pricing" class="hover:text-white transition-colors duration-200">الأسعار</a>
                    <a href="#widget" class="hover:text-white transition-colors duration-200">الـ Widget</a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-4">
                    <template v-if="user">
                        <Link :href="route('dashboard')" class="px-5 h-11 flex items-center justify-center rounded-xl bg-indigo-650 hover:bg-indigo-600 text-white font-bold text-sm transition-all duration-300 shadow-lg shadow-indigo-500/10">
                            لوحة التحكم
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="hidden sm:flex items-center text-sm font-bold text-slate-300 hover:text-white transition-colors duration-200">
                            تسجيل دخول التجار
                        </Link>
                        <Link :href="route('login', { action: 'register' })" class="px-5 h-11 flex items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-650 hover:from-indigo-550 hover:to-violet-550 text-white font-bold text-sm transition-all duration-300 shadow-lg shadow-indigo-500/25">
                            ابدأ مجاناً
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative pt-20 pb-24 md:pt-32 md:pb-36 max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
                <!-- Left: Text content -->
                <div class="lg:col-span-7 text-right flex flex-col items-start lg:items-end">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/25 text-indigo-400 text-xs font-semibold tracking-wider mb-6 self-start lg:self-auto">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                        أتمتة مراجعات متجر سلة بالكامل
                    </div>
                    
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-[1.25] mb-6 tracking-tight text-right">
                        ضاعف مبيعاتك وثقة عملائك عبر 
                        <span class="bg-gradient-to-r from-indigo-400 via-violet-400 to-purple-400 bg-clip-text text-transparent block mt-2">
                            توثيق التقييمات تلقائياً
                        </span>
                    </h1>

                    <p class="text-lg text-slate-400 leading-relaxed mb-10 max-w-2xl text-right">
                        تتيح لك منصة Conversion Trust إرسال رسائل تقييم تفاعلية ومؤتمتة لعملائك عبر واتساب بعد تسليم الطلب مباشرة. اجمع التقييمات، الصور، الفيديوهات، واعرضها في متجرك داخل نافذة Shadow DOM أنيقة ومحمية لتبهر زوار متجرك وتزيد مبيعاتك.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto justify-end">
                        <Link :href="route('login', { action: 'register' })" class="px-8 h-14 flex items-center justify-center rounded-2xl bg-indigo-600 hover:bg-indigo-550 text-white font-bold text-base transition-all duration-300 shadow-xl shadow-indigo-600/30 hover:scale-[1.02]">
                            ابدأ تجربتك المجانية الآن
                        </Link>
                        <Link :href="route('login')" class="px-8 h-14 flex items-center justify-center rounded-2xl bg-slate-900 hover:bg-slate-850 text-slate-300 hover:text-white border border-slate-800 font-bold text-base transition-all duration-300 hover:scale-[1.02]">
                            تسجيل الدخول
                        </Link>
                    </div>

                    <!-- Trust factors -->
                    <div class="flex items-center gap-8 mt-12 border-t border-slate-900 pt-8 w-full justify-end">
                        <div>
                            <div class="text-2xl font-extrabold text-white">30,000+</div>
                            <div class="text-xs text-slate-500 font-semibold mt-1">تقييم موثق</div>
                        </div>
                        <div class="w-px h-10 bg-slate-800"></div>
                        <div>
                            <div class="text-2xl font-extrabold text-white">1,500+</div>
                            <div class="text-xs text-slate-500 font-semibold mt-1">تاجر نشط</div>
                        </div>
                        <div class="w-px h-10 bg-slate-800"></div>
                        <div>
                            <div class="text-2xl font-extrabold text-white">99.9%</div>
                            <div class="text-xs text-slate-500 font-semibold mt-1">نسبة جهوزية خوادم الإرسال</div>
                        </div>
                    </div>
                </div>

                <!-- Right: Visual mockup -->
                <div class="lg:col-span-5 relative w-full flex justify-center">
                    <div class="relative w-full max-w-[420px] aspect-[4/5] rounded-[32px] border border-slate-800 bg-slate-900/40 p-6 backdrop-blur-sm overflow-hidden shadow-2xl shadow-indigo-950/20">
                        <!-- Top header of mockup -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-800/80 mb-6">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-slate-700"></div>
                                <div class="w-3 h-3 rounded-full bg-slate-700"></div>
                                <div class="w-3 h-3 rounded-full bg-slate-700"></div>
                            </div>
                            <div class="text-xs font-semibold text-slate-500">لوحة التحكم التفاعلية</div>
                        </div>

                        <!-- Mockup dashboard stats -->
                        <div class="space-y-4">
                            <div class="h-16 rounded-2xl bg-indigo-950/40 border border-indigo-900/30 p-4 flex items-center justify-between">
                                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z" />
                                    </svg>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-bold text-slate-500">حملات واتساب النشطة</div>
                                    <div class="text-sm font-extrabold text-white mt-1">2,854 رسالة مرسلة</div>
                                </div>
                            </div>

                            <!-- WhatsApp Mock chat -->
                            <div class="rounded-2xl bg-slate-950/50 p-4 border border-slate-800 space-y-3">
                                <div class="flex gap-2.5 max-w-[85%]">
                                    <div class="w-7 h-7 rounded-full bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-400">CT</div>
                                    <div class="bg-slate-900 rounded-2xl rounded-tr-none p-3 text-xs text-slate-300 leading-normal text-right">
                                        مرحباً بك! كيف تقيم جودة المنتج الذي استلمته اليوم؟ (1 إلى 5 نجوم)
                                    </div>
                                </div>
                                <div class="flex gap-2.5 max-w-[80%] self-end justify-end ml-auto">
                                    <div class="bg-indigo-650 text-white rounded-2xl rounded-tl-none p-3 text-xs leading-normal text-right shadow-md shadow-indigo-600/10">
                                        ⭐⭐⭐⭐⭐ منتج فوق الممتاز وتوصيل سريع!
                                    </div>
                                </div>
                            </div>

                            <!-- Widget mockup representation -->
                            <div class="rounded-2xl bg-slate-950/30 p-4 border border-slate-800/60">
                                <div class="flex items-center gap-3 mb-2 justify-end">
                                    <span class="text-[10px] text-slate-500 font-semibold">قبل دقيقتين</span>
                                    <span class="text-xs font-bold text-white">خالد ع.</span>
                                    <div class="w-6 h-6 rounded-full bg-indigo-900/30 flex items-center justify-center text-[10px] font-bold text-indigo-400">خ</div>
                                </div>
                                <p class="text-[11px] text-slate-400 text-right leading-relaxed">
                                    جودة المنتجات والخدمة مذهلة جداً وسرعة الرد ممتازة. أنصح بالتعامل معهم.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-24 border-t border-slate-900 bg-slate-950 relative">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">كل ما تحتاجه لبناء مصداقية متجرك</h2>
                    <p class="text-slate-400 text-base leading-relaxed">
                        نوفر لك حلولاً تقنية متكاملة تدمج متجرك في سلة مع نظام أتمتة الرسائل وخدمات الواتساب لعرض تقييمات موثقة بالكامل.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- Feature 1 -->
                    <div class="group p-8 rounded-3xl bg-slate-900/25 border border-slate-800/80 hover:border-indigo-500/30 transition-all duration-300 flex flex-col text-right">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 self-end">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 6 21c-.139.04-.277.05-.415.033a.75.75 0 0 1-.586-.641 4.482 4.482 0 0 0-.84-2.22C3.393 16.602 3 14.86 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">شات بوت واتساب الذكي</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            يقوم النظام بطرح الأسئلة المخصصة التي تقوم بإعدادها بنفسك، ويطلب من المشتري التعليق النصي ورفع صورة أو فيديو للمنتج مباشرة في الواتساب.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="group p-8 rounded-3xl bg-slate-900/25 border border-slate-800/80 hover:border-indigo-500/30 transition-all duration-300 flex flex-col text-right">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 self-end">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9.75 16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">عنصر الـ Widget المعزول</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            كود العرض يتم حقنه وعزله بالكامل داخل Shadow DOM لمنع أي تعارض في أكواد CSS مع القوالب الخاصة بسلة، ويعرض صور العملاء الدائرية بشكل أنيق.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="group p-8 rounded-3xl bg-slate-900/25 border border-slate-800/80 hover:border-indigo-500/30 transition-all duration-300 flex flex-col text-right">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 self-end">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">تعدد المستأجرين وعزل تام</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            عزل تام لبيانات التجار بنسبة 100% عبر نطاق عالمي آمن يمنع أي تداخل أو وصول غير مصرح به للبيانات لتشعر بأقصى درجات الأمان.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="group p-8 rounded-3xl bg-slate-900/25 border border-slate-800/80 hover:border-indigo-500/30 transition-all duration-300 flex flex-col text-right">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 self-end">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">تقارير وإحصاءات دقيقة</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            لوحة تحكم إحصائية تفاعلية تعرض معدلات الاستجابة، عدد الرسائل المرسلة، ومسار المراجعات بالتفصيل لتحسين أداء متجرك.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-24 border-t border-slate-900 bg-slate-950/40 relative">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">باقات مرنة تناسب حجم متجرك</h2>
                    <p class="text-slate-400 text-base leading-relaxed">
                        ابدأ مجاناً وقم بالترقية متى رغبت في زيادة حجم أعمالك والوصول لميزات أتمتة غير محدودة.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch max-w-5xl mx-auto">
                    <!-- Pack 1: Free -->
                    <div class="rounded-3xl border border-slate-800 bg-slate-900/10 p-8 flex flex-col justify-between text-right relative backdrop-blur-sm">
                        <div>
                            <span class="text-indigo-400 text-xs font-bold uppercase tracking-wider mb-2 block">البداية السريعة</span>
                            <h3 class="text-2xl font-extrabold text-white mb-4">الباقة المجانية</h3>
                            <div class="flex items-baseline gap-1 justify-end mb-6">
                                <span class="text-white text-4xl font-extrabold">0</span>
                                <span class="text-slate-400 text-sm">ريال / شهرياً</span>
                            </div>
                            <ul class="space-y-4 text-slate-400 text-sm border-t border-slate-800/80 pt-6">
                                <li class="flex items-center gap-2.5 justify-end">حد 50 رسالة تقييم شهرياً <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">شات بوت واتساب الأساسي <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">عنصر عرض المراجعات بالمتجر <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">ظهور علامة مائية للمنصة <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span></li>
                            </ul>
                        </div>
                        <Link :href="route('login', { action: 'register' })" class="mt-8 px-6 h-12 flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm transition-all duration-300 border border-slate-800">
                            سجل حسابك المجاني
                        </Link>
                    </div>

                    <!-- Pack 2: Startup (Featured) -->
                    <div class="rounded-3xl border-2 border-indigo-600 bg-slate-900/30 p-8 flex flex-col justify-between text-right relative shadow-xl shadow-indigo-950/20">
                        <div class="absolute top-0 right-1/2 translate-x-1/2 -translate-y-1/2 px-4 py-1 rounded-full bg-indigo-600 text-white text-xs font-bold">الأكثر طلباً</div>
                        <div>
                            <span class="text-indigo-400 text-xs font-bold uppercase tracking-wider mb-2 block">النمو السريع</span>
                            <h3 class="text-2xl font-extrabold text-white mb-4">باقة انطلاق (Startup)</h3>
                            <div class="flex items-baseline gap-1 justify-end mb-6">
                                <span class="text-white text-4xl font-extrabold">99</span>
                                <span class="text-slate-400 text-sm">ريال / شهرياً</span>
                            </div>
                            <ul class="space-y-4 text-slate-300 text-sm border-t border-indigo-900/30 pt-6">
                                <li class="flex items-center gap-2.5 justify-end font-medium text-white">حد 1,000 رسالة تقييم شهرياً <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">إزالة العلامة المائية بالكامل <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">تخصيص كامل للأسئلة والشات بوت <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">مزامنة تلقائية مع متجر سلة <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">دعم تنزيل ميديا العملاء (الصور/الفيديو) <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span></li>
                            </ul>
                        </div>
                        <Link :href="route('login', { action: 'register' })" class="mt-8 px-6 h-12 flex items-center justify-center rounded-xl bg-indigo-650 hover:bg-indigo-600 text-white font-bold text-sm transition-all duration-300 shadow-lg shadow-indigo-600/20">
                            اشترك الآن
                        </Link>
                    </div>

                    <!-- Pack 3: Growth -->
                    <div class="rounded-3xl border border-slate-800 bg-slate-900/10 p-8 flex flex-col justify-between text-right relative backdrop-blur-sm">
                        <div>
                            <span class="text-indigo-400 text-xs font-bold uppercase tracking-wider mb-2 block">المتاجر الكبيرة</span>
                            <h3 class="text-2xl font-extrabold text-white mb-4">باقة النمو (Growth)</h3>
                            <div class="flex items-baseline gap-1 justify-end mb-6">
                                <span class="text-white text-4xl font-extrabold">199</span>
                                <span class="text-slate-400 text-sm">ريال / شهرياً</span>
                            </div>
                            <ul class="space-y-4 text-slate-400 text-sm border-t border-slate-800/80 pt-6">
                                <li class="flex items-center gap-2.5 justify-end font-medium text-white">رسائل تقييم غير محدودة شهرياً <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">إزالة العلامة المائية بالكامل <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">تخصيص متقدم للشات بوت <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span></li>
                                <li class="flex items-center gap-2.5 justify-end">دعم فني مخصص وأولوية مزامنة <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span></li>
                            </ul>
                        </div>
                        <Link :href="route('login', { action: 'register' })" class="mt-8 px-6 h-12 flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm transition-all duration-300 border border-slate-800">
                            اشترك الآن
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Final -->
        <section class="py-20 max-w-5xl mx-auto px-6 relative z-10">
            <div class="rounded-3xl bg-gradient-to-r from-indigo-900/40 via-purple-900/20 to-slate-900/50 border border-indigo-900/20 p-12 text-center relative overflow-hidden">
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-indigo-800/5 via-transparent to-transparent pointer-events-none"></div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">هل أنت جاهز لتكسب ثقة عملائك؟</h2>
                <p class="text-slate-400 text-base max-w-2xl mx-auto mb-8">
                    انضم لآلاف التجار في سلة الذين قاموا بزيادة نسب التحويل والمبيعات بفضل المراجعات والتقييمات الموثقة.
                </p>
                <Link :href="route('login', { action: 'register' })" class="inline-flex px-8 h-14 items-center justify-center rounded-2xl bg-indigo-600 hover:bg-indigo-550 text-white font-bold text-base transition-all duration-300 shadow-xl shadow-indigo-600/30 hover:scale-[1.02]">
                    ابدأ الآن مجاناً
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 border-t border-slate-900 bg-slate-950/80 text-center text-sm text-slate-500">
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
