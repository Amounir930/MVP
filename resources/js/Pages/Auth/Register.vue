<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    name: '',
    email: props.email,
    password: '',
    password_confirmation: '',
    code: '',
    token: props.token,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="إكمال التسجيل" />

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400 text-right" dir="rtl">
            يرجى إكمال البيانات أدناه وإدخال رمز التأكيد المرسل إلى بريدك الإلكتروني لإتمام تفعيل الحساب.
        </div>

        <form @submit.prevent="submit" class="space-y-4 text-right" dir="rtl">
            <div>
                <InputLabel for="name" value="الاسم الكامل" class="text-right" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full text-right"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2 text-right" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="البريد الإلكتروني" class="text-right" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full bg-gray-100 dark:bg-gray-800 text-gray-500 cursor-not-allowed text-right"
                    v-model="form.email"
                    disabled
                />

                <InputError class="mt-2 text-right" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="code" value="رمز التأكيد (6 أرقام)" class="text-right" />

                <TextInput
                    id="code"
                    type="text"
                    maxlength="6"
                    class="mt-1 block w-full text-center font-bold tracking-widest text-lg"
                    v-model="form.code"
                    required
                    placeholder="000000"
                />

                <InputError class="mt-2 text-right" :message="form.errors.code" />
            </div>

            <div>
                <InputLabel for="password" value="كلمة المرور" class="text-right" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full text-right"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2 text-right" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="تأكيد كلمة المرور"
                    class="text-right"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full text-right"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2 text-right"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                >
                    هل لديك حساب بالفعل؟
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    تأكيد وإنشاء الحساب
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
