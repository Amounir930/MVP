<script setup lang="ts">
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="relative min-h-screen bg-gradient-to-br from-[#F8FAFC] via-[#F1F5F9] to-[#E2E8F0] dark:from-[#0B0F19] dark:via-[#111827] dark:to-[#030712] text-gray-900 dark:text-gray-100 transition-colors duration-300 overflow-hidden">
            <!-- Ambient Decorative Glowing Circles (Light Theme) -->
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-[100px] pointer-events-none animate-pulse"></div>
            <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-blue-500/10 rounded-full blur-[100px] pointer-events-none animate-pulse" style="animation-delay: 2s;"></div>
            
            <!-- Subtle backdrop grid -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#0f172a05_1px,transparent_1px),linear-gradient(to_bottom,#0f172a05_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none dark:opacity-30"></div>

            <!-- Apple Style Floating Navigation Bar -->
            <div class="fixed top-4 left-4 right-4 z-50">
                <nav class="mx-auto max-w-7xl bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl border border-white/25 dark:border-white/5 rounded-2xl shadow-[0_8px_32px_0_rgba(0,0,0,0.06)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.4)] transition-all duration-300">
                    <div class="px-6">
                        <div class="flex h-16 items-center justify-between">
                            <!-- Left Section (Actions/Profile) -->
                            <div class="hidden sm:flex sm:items-center sm:gap-4">
                                <Dropdown align="left" width="48">
                                    <template #trigger>
                                        <button type="button" class="inline-flex items-center gap-2 px-4 py-2 border border-transparent text-sm font-semibold rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-[#2C2C2E] dark:hover:bg-[#3A3A3C] text-gray-700 dark:text-gray-200 transition-all duration-200">
                                            {{ $page.props.auth.user.name }}
                                            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')" class="text-right">الملف الشخصي</DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button" class="text-right text-rose-600 dark:text-rose-400 font-bold">تسجيل الخروج</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Right Section (Logo & Links) -->
                            <div class="flex items-center gap-8">
                                <!-- Logo -->
                                <Link :href="route('dashboard')" class="flex shrink-0 items-center">
                                    <ApplicationLogo class="block h-7 w-auto fill-current text-gray-800 dark:text-gray-200 hover:opacity-80 transition-opacity duration-200" />
                                </Link>

                                <!-- Navigation Links -->
                                <div class="hidden sm:flex sm:items-center sm:gap-6" dir="rtl">
                                    <Link 
                                        :href="route('dashboard')" 
                                        :class="route().current('dashboard') 
                                            ? 'text-indigo-650 dark:text-indigo-400 font-bold' 
                                            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                                        class="text-sm font-semibold transition-colors duration-200"
                                    >
                                        لوحة تحكم التاجر
                                    </Link>
                                    
                                    <Link 
                                        v-if="$page.props.auth.user.is_admin"
                                        :href="route('admin.overview')" 
                                        :class="route().current('admin.overview') 
                                            ? 'text-indigo-650 dark:text-indigo-400 font-bold' 
                                            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                                        class="text-sm font-semibold transition-colors duration-200"
                                    >
                                        لوحة الإشراف العام
                                    </Link>
                                </div>
                            </div>

                            <!-- Hamburger (Mobile) -->
                            <div class="flex items-center sm:hidden">
                                <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center justify-center p-2 rounded-xl text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-[#2C2C2E] focus:outline-none transition-colors duration-250">
                                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>

            <!-- Mobile menu -->
            <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden fixed inset-x-4 top-24 z-50 bg-white/95 dark:bg-[#1C1C1E]/95 backdrop-blur-xl border border-white/25 dark:border-white/5 rounded-2xl shadow-xl p-4 transition-all duration-300">
                <div class="space-y-3" dir="rtl">
                    <Link :href="route('dashboard')" class="block px-4 py-2.5 rounded-xl text-sm font-bold text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-[#2C2C2E]">لوحة تحكم التاجر</Link>
                    <Link v-if="$page.props.auth.user.is_admin" :href="route('admin.overview')" class="block px-4 py-2.5 rounded-xl text-sm font-bold text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-[#2C2C2E]">لوحة الإشراف العام</Link>
                    <div class="border-t border-gray-100 dark:border-gray-800 my-2"></div>
                    <Link :href="route('profile.edit')" class="block px-4 py-2.5 rounded-xl text-sm font-bold text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-[#2C2C2E]">الملف الشخصي</Link>
                    <Link :href="route('logout')" method="post" as="button" class="block w-full text-right px-4 py-2.5 rounded-xl text-sm font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20">تسجيل الخروج</Link>
                </div>
            </div>

            <!-- Spacer for fixed top bar -->
            <div class="h-24"></div>

            <!-- Page Heading -->
            <header class="bg-transparent" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-6 py-6">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="relative z-10">
                <slot />
            </main>
        </div>
    </div>
</template>
