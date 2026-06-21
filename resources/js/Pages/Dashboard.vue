<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale
} from 'chart.js';

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale
);

const page = usePage();
const salla = computed(() => page.props.salla as { connected: boolean; merchant_id: string | null; products_count: number; orders_count: number });
const whatsapp = computed(() => page.props.whatsapp as { connected: boolean; instance_name: string | null; status: string; delay_hours: number; custom_questions: any });
const errors = computed(() => page.props.errors || {});
const flash = computed(() => (page.props.flash || {}) as { success: string | null; error: string | null });
const subscription = computed(() => page.props.subscription as {
  plan_name: string;
  price: number;
  status: string;
  current_period_start: string | null;
  current_period_end: string | null;
  monthly_limit: number;
  current_period_usage: number;
} | null);

let intervalId: any = null;
let whatsappIntervalId: any = null;
let reviewsIntervalId: any = null;

const startStatusPolling = () => {
    if (whatsappIntervalId) clearInterval(whatsappIntervalId);
    whatsappIntervalId = setInterval(async () => {
        try {
            const response = await fetch('/auth/whatsapp/status');
            const data = await response.json();
            if (data.status === 'connected') {
                clearInterval(whatsappIntervalId);
                router.reload({ only: ['whatsapp'] });
            }
        } catch (err) {
            console.error('Error polling whatsapp status:', err);
        }
    }, 4000);
};

onMounted(() => {
    // Poll Salla shared stats every 5 seconds
    intervalId = setInterval(() => {
        router.reload({ only: ['salla'] });
    }, 5000);

    // If not connected, start polling status
    if (!whatsapp.value.connected) {
        startStatusPolling();
    }

    // Fetch initial reviews data
    fetchReviewsData(true);

    // Poll reviews every 10 seconds silently in the background
    reviewsIntervalId = setInterval(() => {
        fetchReviewsData(false);
    }, 10000);
});

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
    if (whatsappIntervalId) {
        clearInterval(whatsappIntervalId);
    }
    if (reviewsIntervalId) {
        clearInterval(reviewsIntervalId);
    }
});

const disconnect = () => {
    if (confirm('هل أنت متأكد من فك الربط وحذف كافة البيانات؟ سيتم حذف جميع المنتجات والطلبات والعملاء والتوكنات التابعة لمتجرك بشكل نهائي ولا يمكن التراجع عن هذا الإجراء.')) {
        router.post('/auth/salla/disconnect');
    }
};

const sync = () => {
    router.post('/auth/salla/sync');
};

// WhatsApp Integration States
const qrCodeBase64 = ref<string | null>(null);
const isLoadingQr = ref(false);
const delayHours = ref(whatsapp.value.delay_hours ?? 24);
const isSavingSettings = ref(false);

const ensureTenOptions = (arr: any) => {
    const list = Array.isArray(arr) ? [...arr] : [];
    while (list.length < 10) {
        list.push('');
    }
    return list.slice(0, 10);
};

const customQuestions = ref({
    enable_questions: whatsapp.value.custom_questions?.enable_questions !== false,
    enable_salla_scanner: whatsapp.value.custom_questions?.enable_salla_scanner !== false,
    salla_scanner_interval_minutes: whatsapp.value.custom_questions?.salla_scanner_interval_minutes ?? 60,
    salla_scanner_lookback_hours: whatsapp.value.custom_questions?.salla_scanner_lookback_hours ?? 24,
    rating_message: whatsapp.value.custom_questions?.rating_message ?? 'مرحباً {name}، شكراً لتعاملك مع متجرنا! يسعدنا جداً تقييمك لطلبك رقم {order_number}. كيف تقيم تجربتك معنا؟',
    rating_button_label: whatsapp.value.custom_questions?.rating_button_label ?? 'اختر التقييم بالنجوم',
    rating_label_5: whatsapp.value.custom_questions?.rating_label_5 ?? '⭐⭐⭐⭐⭐ ممتاز',
    rating_label_4: whatsapp.value.custom_questions?.rating_label_4 ?? '⭐⭐⭐⭐ جيد جداً',
    rating_label_3: whatsapp.value.custom_questions?.rating_label_3 ?? '⭐⭐⭐ مقبول',
    rating_label_2: whatsapp.value.custom_questions?.rating_label_2 ?? '⭐⭐ سيء',
    rating_label_1: whatsapp.value.custom_questions?.rating_label_1 ?? '⭐ سيء جداً',
    rating_invalid_warning: whatsapp.value.custom_questions?.rating_invalid_warning ?? 'الرجاء اختيار تقييم صحيح من 1 إلى 5 نجوم باستخدام القائمة:',
    invalid_rating_message: whatsapp.value.custom_questions?.invalid_rating_message ?? '',
    success_message: whatsapp.value.custom_questions?.success_message ?? 'شكراً لتقييمك! تم حفظ تقييمك بنجاح وسيتم عرضه قريباً في المتجر.',
    questions: Array.isArray(whatsapp.value.custom_questions?.questions)
        ? whatsapp.value.custom_questions.questions.map((q: any) => ({
            id: q.id ?? 'q_' + Math.random().toString(36).substr(2, 9),
            type: q.type ?? 'buttons',
            text: q.text ?? '',
            options: q.type === 'buttons' ? ensureTenOptions(q.options) : []
          }))
        : [
            {
                id: 'q_1',
                type: 'buttons',
                text: 'هل المنتج مطابق للوصف والصور؟',
                options: ['نعم مطابق 👍', 'لا يختلف 👎', '', '', '', '', '', '', '', '']
            },
            {
                id: 'q_2',
                type: 'buttons',
                text: 'كيف كانت جودة المنتج؟',
                options: ['ممتازة ⭐', 'متوسطة 😐', 'ضعيفة 👎', '', '', '', '', '', '', '']
            },
            {
                id: 'q_3',
                type: 'buttons',
                text: 'هل المقاس مناسب؟',
                options: ['نعم مناسب', 'أصغر', 'أكبر', '', '', '', '', '', '', '']
            },
            {
                id: 'q_4',
                type: 'text',
                text: 'يسعدنا معرفة رأيك بالتفصيل. يرجى كتابة تعليقك هنا في رسالة واحدة.'
            },
            {
                id: 'q_5',
                type: 'media',
                text: 'أخيراً: هل ترغب في مشاركة صورة أو فيديو للمنتج لتأكيد مصداقية التقييم؟ (يرجى إرسال الصورة أو نقر \'تخطي\')'
            }
          ]
});

// Watch for successful connection to automatically dismiss QR code
watch(() => whatsapp.value.connected, (newVal) => {
    if (newVal) {
        qrCodeBase64.value = null;
        if (whatsappIntervalId) {
            clearInterval(whatsappIntervalId);
        }
    } else {
        startStatusPolling();
    }
});

// Watch for delay hours property update from backend (in case of reloads)
watch(() => whatsapp.value.delay_hours, (newVal) => {
    delayHours.value = newVal;
});

// Watch for custom questions updates from backend
watch(() => whatsapp.value.custom_questions, (newVal) => {
    if (newVal) {
        customQuestions.value = {
            enable_questions: newVal.enable_questions !== false,
            enable_salla_scanner: newVal.enable_salla_scanner !== false,
            salla_scanner_interval_minutes: newVal.salla_scanner_interval_minutes ?? 60,
            salla_scanner_lookback_hours: newVal.salla_scanner_lookback_hours ?? 24,
            rating_message: newVal.rating_message ?? 'مرحباً {name}، شكراً لتعاملك مع متجرنا! يسعدنا جداً تقييمك لطلبك رقم {order_number}. كيف تقيم تجربتك معنا؟',
            rating_button_label: newVal.rating_button_label ?? 'اختر التقييم بالنجوم',
            rating_label_5: newVal.rating_label_5 ?? '⭐⭐⭐⭐⭐ ممتاز',
            rating_label_4: newVal.rating_label_4 ?? '⭐⭐⭐⭐ جيد جداً',
            rating_label_3: newVal.rating_label_3 ?? '⭐⭐⭐ مقبول',
            rating_label_2: newVal.rating_label_2 ?? '⭐⭐ سيء',
            rating_label_1: newVal.rating_label_1 ?? '⭐ سيء جداً',
            rating_invalid_warning: newVal.rating_invalid_warning ?? 'الرجاء اختيار تقييم صحيح من 1 إلى 5 نجوم باستخدام القائمة:',
            invalid_rating_message: newVal.invalid_rating_message ?? '',
            success_message: newVal.success_message ?? 'شكراً لتقييمك! تم حفظ تقييمك بنجاح وسيتم عرضه قريباً في المتجر.',
            questions: Array.isArray(newVal.questions)
                ? newVal.questions.map((q: any) => ({
                    id: q.id ?? 'q_' + Math.random().toString(36).substr(2, 9),
                    type: q.type ?? 'buttons',
                    text: q.text ?? '',
                    options: q.type === 'buttons' ? ensureTenOptions(q.options) : []
                  }))
                : []
        };
    }
}, { deep: true });

const addQuestion = () => {
    customQuestions.value.questions.push({
        id: 'q_' + Date.now() + Math.random().toString(36).substr(2, 5),
        type: 'buttons',
        text: 'اكتب نص السؤال هنا...',
        options: Array(10).fill('')
    });
};

const removeQuestion = (index: number) => {
    customQuestions.value.questions.splice(index, 1);
};

const moveQuestionUp = (index: number) => {
    if (index > 0) {
        const q = customQuestions.value.questions.splice(index, 1)[0];
        customQuestions.value.questions.splice(index - 1, 0, q);
    }
};

const moveQuestionDown = (index: number) => {
    if (index < customQuestions.value.questions.length - 1) {
        const q = customQuestions.value.questions.splice(index, 1)[0];
        customQuestions.value.questions.splice(index + 1, 0, q);
    }
};

const changeQuestionType = (index: number, type: 'buttons' | 'text' | 'media') => {
    const q = customQuestions.value.questions[index];
    q.type = type;
    if (type === 'buttons') {
        q.options = Array(10).fill('');
    } else {
        q.options = [];
    }
};

const startWhatsappConnect = async () => {
    isLoadingQr.value = true;
    qrCodeBase64.value = null;
    try {
        const response = await fetch('/auth/whatsapp/connect');
        const data = await response.json();
        if (data.error) {
            alert('فشل بدء الربط: ' + data.error);
        } else if (data.qrcode) {
            qrCodeBase64.value = data.qrcode;
            // Explicitly trigger status polling once QR code is displayed
            startStatusPolling();
        } else {
            alert('فشل الحصول على رمز الاستجابة السريعة (QR Code).');
        }
    } catch (err: any) {
        alert('حدث خطأ أثناء الاتصال بالخادم: ' + err.message);
    } finally {
        isLoadingQr.value = false;
    }
};

const isDisconnectingWhatsapp = ref(false);
const disconnectWhatsapp = () => {
    if (confirm('هل أنت متأكد من فك ربط حساب واتساب؟ سيتم تعطيل إرسال رسائل التقييم التلقائية وحذف إعدادات واتساب.')) {
        isDisconnectingWhatsapp.value = true;
        router.post('/auth/whatsapp/disconnect', {}, {
            onSuccess: () => {
                qrCodeBase64.value = null;
                if (whatsappIntervalId) {
                    clearInterval(whatsappIntervalId);
                }
            },
            onFinish: () => {
                isDisconnectingWhatsapp.value = false;
            }
        });
    }
};

const saveDelaySettings = () => {
    isSavingSettings.value = true;
    router.post('/auth/whatsapp/settings', {
        delay_hours: delayHours.value,
        custom_questions: customQuestions.value
    }, {
        onFinish: () => {
            isSavingSettings.value = false;
        }
    });
};

// Sandbox Simulation State
const sandboxForm = ref({
    customer_name: 'أحمد العتيبي',
    customer_phone: '',
    order_reference: 'SALLA-9988',
    order_total: 150.00,
    force_immediate: true
});
const isSimulating = ref(false);

const simulateOrder = () => {
    isSimulating.value = true;
    router.post('/sandbox/simulate-order', sandboxForm.value, {
        onFinish: () => {
            isSimulating.value = false;
        }
    });
};

// Billing & Subscription Upgrade States
const showUpgradeModal = ref(false);
const selectedUpgradePlan = ref('');
const upgradeCardDetails = ref({
    number: '4000 1234 5678 9010',
    expiry: '12/29',
    cvc: '123',
    name: page.props.auth?.user?.name || 'التاجر ش.م'
});
const isUpgrading = ref(false);

const openUpgradeModal = (plan: string) => {
    selectedUpgradePlan.value = plan;
    showUpgradeModal.value = true;
};

const closeUpgradeModal = () => {
    showUpgradeModal.value = false;
    selectedUpgradePlan.value = '';
};

const handleUpgradeSubmit = () => {
    isUpgrading.value = true;
    router.post('/billing/upgrade', {
        plan_name: selectedUpgradePlan.value
    }, {
        onSuccess: () => {
            closeUpgradeModal();
        },
        onFinish: () => {
            isUpgrading.value = false;
        }
    });
};

// Reviews Moderation & Analytics State
const activeMainTab = ref('analytics');
const reviewsTabMode = ref('order'); // 'order' or 'product'

// Accordion open/close state for settings tab
const accordionSandbox = ref(true);
const accordionWaConnection = ref(true);
const accordionWaSettings = ref(false);
const accordionWaQuestions = ref(false);

// Separate saving states
const isSavingDelayOnly = ref(false);
const isSavingQuestionsOnly = ref(false);

const saveDelaySettingsOnly = () => {
    isSavingDelayOnly.value = true;
    router.post('/auth/whatsapp/settings', {
        delay_hours: delayHours.value,
        custom_questions: {
            ...customQuestions.value,
            // only send the scanner/delay fields
            enable_salla_scanner: customQuestions.value.enable_salla_scanner,
            salla_scanner_interval_minutes: customQuestions.value.salla_scanner_interval_minutes,
            salla_scanner_lookback_hours: customQuestions.value.salla_scanner_lookback_hours,
        }
    }, {
        onFinish: () => { isSavingDelayOnly.value = false; },
        preserveScroll: true,
    });
};

const saveQuestionsOnly = () => {
    isSavingQuestionsOnly.value = true;
    router.post('/auth/whatsapp/settings', {
        delay_hours: delayHours.value,
        custom_questions: customQuestions.value
    }, {
        onFinish: () => { isSavingQuestionsOnly.value = false; },
        preserveScroll: true,
    });
};

const reviewsList = ref<any[]>([]);
const productsList = ref<any[]>([]);
const reviewsSearchQuery = ref('');
const reviewsStatusFilter = ref('all'); // 'all', 'pending', 'approved', 'rejected'
const isLoadingReviews = ref(false);
const reviewsError = ref('');
const reviewsSuccessMsg = ref('');

const dashboardStats = ref({
    avg_rating: 0,
    total_reviews: 0,
    messages_consumed: 0,
    delivery_rate: 100,
    chart_data: [] as any[]
});

const replyForms = ref<Record<number, string>>({});
const isSubmittingReply = ref<Record<number, boolean>>({});

const fetchReviewsData = async (showLoading = true) => {
    if (showLoading) {
        isLoadingReviews.value = true;
    }
    reviewsError.value = '';
    try {
        const response = await fetch('/reviews');
        if (!response.ok) throw new Error('فشل جلب البيانات من الخادم.');
        const data = await response.json();
        reviewsList.value = data.reviews || [];
        productsList.value = data.products || [];
        
        if (data.stats) {
            dashboardStats.value = data.stats;
        }

        // Initialize replies textareas
        reviewsList.value.forEach((r: any) => {
            if (r.reply && replyForms.value[r.id] === undefined) {
                replyForms.value[r.id] = r.reply;
            }
        });
    } catch (err: any) {
        reviewsError.value = err.message || 'حدث خطأ غير متوقع.';
    } finally {
        if (showLoading) {
            isLoadingReviews.value = false;
        }
    }
};

const updateReviewStatus = async (reviewId: number, status: string) => {
    try {
        const response = await fetch(`/reviews/${reviewId}/status`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({ status })
        });
        if (!response.ok) throw new Error('فشل تحديث حالة التقييم.');
        const data = await response.json();
        
        // Update in reviewsList
        const idx = reviewsList.value.findIndex((r: any) => r.id === reviewId);
        if (idx !== -1) {
            reviewsList.value[idx].status = status;
        }
        // Update in productsList reviews
        productsList.value.forEach((p: any) => {
            if (p.reviews) {
                const pIdx = p.reviews.findIndex((r: any) => r.id === reviewId);
                if (pIdx !== -1) {
                    p.reviews[pIdx].status = status;
                }
            }
        });
        
        reviewsSuccessMsg.value = 'تم تحديث حالة التقييم بنجاح.';
        setTimeout(() => reviewsSuccessMsg.value = '', 3000);
    } catch (err: any) {
        alert(err.message || 'حدث خطأ أثناء التحديث.');
    }
};

const submitReply = async (reviewId: number) => {
    isSubmittingReply.value[reviewId] = true;
    try {
        const response = await fetch(`/reviews/${reviewId}/reply`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({ reply: replyForms.value[reviewId] || '' })
        });
        if (!response.ok) throw new Error('فشل حفظ الرد.');
        const data = await response.json();
        
        // Update in reviewsList
        const idx = reviewsList.value.findIndex((r: any) => r.id === reviewId);
        if (idx !== -1) {
            reviewsList.value[idx] = data.review;
        }
        // Update in productsList reviews
        productsList.value.forEach((p: any) => {
            if (p.reviews) {
                const pIdx = p.reviews.findIndex((r: any) => r.id === reviewId);
                if (pIdx !== -1) {
                    p.reviews[pIdx] = data.review;
                }
            }
        });
        
        alert('تم حفظ الرد بنجاح.');
    } catch (err: any) {
        alert(err.message || 'حدث خطأ أثناء حفظ الرد.');
    } finally {
        isSubmittingReply.value[reviewId] = false;
    }
};

const deleteReply = async (reviewId: number) => {
    if (!confirm('هل أنت متأكد من حذف الرد الرسمي للمتجر؟')) return;
    isSubmittingReply.value[reviewId] = true;
    try {
        const response = await fetch(`/reviews/${reviewId}/reply`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({ reply: null })
        });
        if (!response.ok) throw new Error('فشل حذف الرد.');
        const data = await response.json();
        
        // Update in reviewsList
        const idx = reviewsList.value.findIndex((r: any) => r.id === reviewId);
        if (idx !== -1) {
            reviewsList.value[idx] = data.review;
        }
        // Update in productsList reviews
        productsList.value.forEach((p: any) => {
            if (p.reviews) {
                const pIdx = p.reviews.findIndex((r: any) => r.id === reviewId);
                if (pIdx !== -1) {
                    p.reviews[pIdx] = data.review;
                }
            }
        });
        
        replyForms.value[reviewId] = '';
        alert('تم حذف الرد بنجاح.');
    } catch (err: any) {
        alert(err.message || 'حدث خطأ أثناء الحذف.');
    } finally {
        isSubmittingReply.value[reviewId] = false;
    }
};

const deleteReview = async (reviewId: number) => {
    if (!confirm('هل أنت متأكد من حذف هذا التقييم بشكل نهائي؟')) return;
    try {
        const response = await fetch(`/reviews/${reviewId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            }
        });
        if (!response.ok) throw new Error('فشل حذف التقييم.');
        
        // Remove from reviewsList
        reviewsList.value = reviewsList.value.filter((r: any) => r.id !== reviewId);
        // Remove from productsList reviews
        productsList.value.forEach((p: any) => {
            if (p.reviews) {
                p.reviews = p.reviews.filter((r: any) => r.id !== reviewId);
            }
        });
        
        reviewsSuccessMsg.value = 'تم حذف التقييم بنجاح.';
        setTimeout(() => reviewsSuccessMsg.value = '', 3000);
    } catch (err: any) {
        alert(err.message || 'حدث خطأ أثناء الحذف.');
    }
};

const filteredReviews = computed(() => {
    return reviewsList.value.filter((review: any) => {
        if (reviewsStatusFilter.value !== 'all' && review.status !== reviewsStatusFilter.value) {
            return false;
        }
        if (reviewsSearchQuery.value) {
            const query = reviewsSearchQuery.value.toLowerCase();
            const customerName = review.customer?.name?.toLowerCase() || '';
            const comment = review.comment?.toLowerCase() || '';
            const orderRef = review.order?.invoice_number?.toLowerCase() || '';
            const products = review.order?.products?.map((p: any) => p.name.toLowerCase()).join(' ') || '';
            
            return customerName.includes(query) || 
                   comment.includes(query) || 
                   orderRef.includes(query) || 
                   products.includes(query);
        }
        return true;
    });
});

const filteredProducts = computed(() => {
    return productsList.value.map((product: any) => {
        const filteredProReviews = (product.reviews || []).filter((review: any) => {
            if (reviewsStatusFilter.value !== 'all' && review.status !== reviewsStatusFilter.value) {
                return false;
            }
            if (reviewsSearchQuery.value) {
                const query = reviewsSearchQuery.value.toLowerCase();
                const customerName = review.customer?.name?.toLowerCase() || '';
                const comment = review.comment?.toLowerCase() || '';
                const orderRef = review.order?.invoice_number?.toLowerCase() || '';
                
                return customerName.includes(query) || 
                       comment.includes(query) || 
                       orderRef.includes(query);
            }
            return true;
        });
        
        return {
            ...product,
            filtered_reviews: filteredProReviews
        };
    }).filter((product: any) => {
        if (reviewsSearchQuery.value) {
            const query = reviewsSearchQuery.value.toLowerCase();
            const productNameMatches = product.name?.toLowerCase().includes(query);
            const hasMatchingReviews = product.filtered_reviews.length > 0;
            return productNameMatches || hasMatchingReviews;
        }
        return (product.reviews || []).length > 0 || (product.filtered_reviews || []).length > 0;
    });
});

const formatInvoiceNumber = (invoiceNumber: any) => {
    if (!invoiceNumber) return 'N/A';
    const numStr = String(invoiceNumber);
    return numStr.startsWith('#') ? numStr : `#${numStr}`;
};

// Chart configurations
const chartDataConfig = computed(() => {
    const labels = (dashboardStats.value.chart_data || []).map((d: any) => d.date);
    const datasetData = (dashboardStats.value.chart_data || []).map((d: any) => d.count);

    return {
        labels: labels,
        datasets: [
            {
                label: 'عدد التقييمات اليومية',
                backgroundColor: 'rgba(79, 70, 229, 0.05)',
                borderColor: '#4f46e5',
                borderWidth: 2,
                pointBackgroundColor: '#4f46e5',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#4f46e5',
                data: datasetData,
                fill: true,
                tension: 0.3
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(200, 200, 200, 0.08)'
            },
            ticks: {
                color: '#9ca3af',
                font: {
                    family: 'Cairo',
                    size: 10
                }
            }
        },
        x: {
            grid: {
                display: false
            },
            ticks: {
                color: '#9ca3af',
                font: {
                    family: 'Cairo',
                    size: 9
                }
            }
        }
    }
};
</script>

<template>
    <Head title="لوحة التحكم" />

    <AuthenticatedLayout>
        <!-- Background Radial Glows for Apple Aesthetic -->
        <div class="fixed top-16 right-1/3 w-[500px] h-[500px] bg-indigo-400/8 dark:bg-indigo-600/5 rounded-full blur-[120px] pointer-events-none z-0"></div>
        <div class="fixed bottom-1/4 left-1/4 w-[400px] h-[400px] bg-emerald-400/6 dark:bg-teal-600/4 rounded-full blur-[100px] pointer-events-none z-0"></div>
        <div class="fixed top-1/2 left-1/2 w-[300px] h-[300px] bg-purple-400/5 dark:bg-purple-600/3 rounded-full blur-[80px] pointer-events-none z-0"></div>

        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4" dir="rtl">
                <div class="text-right">
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-gray-100">
                        لوحة تحكم التاجر
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        مراقبة الأداء وإدارة التقييمات وإعدادات الأتمتة.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8 relative z-10" dir="rtl">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Premium Apple-style Pill Tab Navigation -->
                <div class="flex items-center gap-2 p-1.5 bg-white/70 dark:bg-[#1C1C1E]/70 backdrop-blur-xl border border-white/25 dark:border-white/5 rounded-2xl shadow-sm w-fit">
                    <button
                        @click="activeMainTab = 'analytics'"
                        :class="activeMainTab === 'analytics'
                            ? 'bg-indigo-600 text-white shadow-[0_4px_12px_0_rgba(79,70,229,0.25)]'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100/60 dark:hover:bg-white/5'"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200"
                    >
                        المؤشرات والتحليلات
                    </button>
                    <button
                        @click="activeMainTab = 'settings'"
                        :class="activeMainTab === 'settings'
                            ? 'bg-indigo-600 text-white shadow-[0_4px_12px_0_rgba(79,70,229,0.25)]'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100/60 dark:hover:bg-white/5'"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200"
                    >
                        إعدادات الربط والأتمتة
                    </button>
                    <button
                        @click="activeMainTab = 'reviews'"
                        :class="activeMainTab === 'reviews'
                            ? 'bg-indigo-600 text-white shadow-[0_4px_12px_0_rgba(79,70,229,0.25)]'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100/60 dark:hover:bg-white/5'"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2"
                    >
                        إدارة التقييمات
                        <span v-if="reviewsList.length > 0" class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-extrabold bg-white/25 text-white">
                            {{ reviewsList.length }}
                        </span>
                    </button>
                    <button
                        @click="activeMainTab = 'billing'"
                        :class="activeMainTab === 'billing'
                            ? 'bg-indigo-600 text-white shadow-[0_4px_12px_0_rgba(79,70,229,0.25)]'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100/60 dark:hover:bg-white/5'"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200"
                    >
                        الباقات والاشتراكات
                    </button>
                    <button
                        @click="activeMainTab = 'sandbox'"
                        :class="activeMainTab === 'sandbox'
                            ? 'bg-indigo-600 text-white shadow-[0_4px_12px_0_rgba(79,70,229,0.25)]'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100/60 dark:hover:bg-white/5'"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200"
                    >
                        محاكي متجر سلة
                    </button>
                </div>

                <div v-if="activeMainTab === 'analytics'" class="space-y-6">
                    <!-- Statistical Cards Grid (Apple Glassmorphism Cards) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <!-- Card 1: Average Rating -->
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.07)]">
                            <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">متوسط التقييم العام</span>
                            <div class="flex items-baseline gap-2 mt-4">
                                <h4 class="text-4xl font-extrabold text-gray-900 dark:text-gray-100 font-mono">
                                    {{ dashboardStats.avg_rating }}
                                </h4>
                                <span class="text-indigo-500 dark:text-indigo-400 font-black text-xl">/ 5</span>
                            </div>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">متوسط تقييمات العملاء لمتجرك</p>
                        </div>

                        <!-- Card 2: Total Reviews -->
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.07)]">
                            <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">إجمالي التقييمات</span>
                            <h4 class="text-4xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-4 font-mono">
                                {{ dashboardStats.total_reviews }}
                            </h4>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">تقييمات مكتملة وموثقة</p>
                        </div>

                        <!-- Card 3: Consumed Messages -->
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.07)]">
                            <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">الرسائل المستهلكة</span>
                            <div class="flex items-baseline gap-2 mt-4">
                                <h4 class="text-4xl font-extrabold text-gray-900 dark:text-gray-100 font-mono">
                                    {{ subscription ? subscription.current_period_usage : dashboardStats.messages_consumed }}
                                </h4>
                                <span class="text-sm text-gray-400 dark:text-gray-500 font-mono">/ {{ subscription ? subscription.monthly_limit : 50 }}</span>
                            </div>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">من رصيد الباقة النشطة هذا الشهر</p>
                        </div>

                        <!-- Card 4: WhatsApp Delivery Rate -->
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-[0_12px_40px_0_rgba(0,0,0,0.07)]">
                            <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">توصيل واتساب</span>
                            <div class="flex items-baseline gap-2 mt-4">
                                <h4 class="text-4xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">
                                    {{ dashboardStats.delivery_rate }}%
                                </h4>
                            </div>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">نسبة نجاح إرسال الرسائل</p>
                        </div>
                    </div>

                    <!-- Line Chart (Apple Glassmorphism Container) -->
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] transition-all duration-300">
                        <h3 class="text-base font-extrabold text-gray-800 dark:text-gray-100 mb-6">مخطط تطور التقييمات (آخر 30 يوم)</h3>
                        <div class="h-72 w-full">
                            <Line :data="chartDataConfig" :options="chartOptions" />
                        </div>
                    </div>
                </div>

                <div v-if="activeMainTab === 'settings'" class="space-y-5">
                    <!-- Salla Integration Card -->
                <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] overflow-hidden transition-all duration-300">
                    <div class="p-8">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                            
                            <!-- Right Column: Details -->
                            <div class="flex items-center gap-5 text-right w-full md:w-auto">
                                <!-- Icon Container -->
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.616m-7.5 0h7.5m2.25-3.562V3.75m0 0a1.5 1.5 0 0 1-1.5-1.5m1.5 1.5a1.5 1.5 0 0 0 1.5-1.5M18.75 21h-7.5V13.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21m-2.25 0h7.5m0-3h1.5m-1.5-3h1.5M2.25 12h1.5m16.5 0h1.5M1.5 9.75h21M1.5 5.25h21" />
                                    </svg>
                                </div>
                                
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        تكامل متجر سلة (Salla Integration)
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        مزامنة المنتجات والطلبات والعملاء بشكل تلقائي وآمن.
                                    </p>
                                </div>
                            </div>

                            <!-- Left Column: Status/Action -->
                            <div class="flex items-center w-full md:w-auto justify-end">
                                <!-- If Salla is NOT connected -->
                                <div v-if="!salla.connected" class="w-full md:w-auto">
                                    <a 
                                        href="/auth/salla/redirect"
                                        class="inline-flex items-center justify-center px-6 py-3.5 border border-transparent text-base font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all duration-200 transform hover:-translate-y-0.5 active:translate-y-0 w-full md:w-auto shadow-md shadow-emerald-500/10"
                                    >
                                        ربط متجر سلة الآن
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 mr-2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                        </svg>
                                    </a>
                                </div>

                                <!-- If Salla IS connected -->
                                <div v-else class="flex flex-col items-end gap-2 text-right">
                                    <div class="flex flex-wrap items-center gap-3 justify-end w-full md:w-auto">
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold text-sm">
                                            <span class="relative flex h-2 w-2">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                            </span>
                                            متصل ونشط
                                        </div>
                                        <button 
                                            @click="sync"
                                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all duration-200 shadow-md shadow-emerald-500/10"
                                        >
                                            مزامنة البيانات الآن
                                        </button>
                                        <button 
                                            @click="disconnect"
                                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-xl text-white bg-rose-600 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500 transition-all duration-200 shadow-md shadow-rose-500/10"
                                        >
                                            فك الربط وحذف البيانات
                                        </button>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        معرف التاجر: <code class="bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded font-mono text-gray-800 dark:text-gray-200">{{ salla.merchant_id }}</code>
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats Grid (Apple Glassmorphism) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                        <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">إجمالي الطلبات المزامنة</span>
                        <h4 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-3 font-mono">
                            {{ salla.connected ? salla.orders_count : '--' }}
                        </h4>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">طلبات مستوردة من سلة</p>
                    </div>
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                        <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">المنتجات المستوردة</span>
                        <h4 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-3 font-mono">
                            {{ salla.connected ? salla.products_count : '--' }}
                        </h4>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">منتجات مزامنة من سلة</p>
                    </div>
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                        <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">حالة المزامنة</span>
                        <div class="flex items-center gap-2 mt-3">
                            <span v-if="salla.connected" class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            <h4 class="text-lg font-bold mt-0" :class="salla.connected ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'">
                                {{ salla.connected ? 'مستعدة للعمل' : 'غير نشطة (يرجى الربط)' }}
                            </h4>
                        </div>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">حالة مزامنة الخلفية مع سلة</p>
                    </div>
                </div>



                <!-- ===== ACCORDION 2: WhatsApp Connection ===== -->
                <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] overflow-hidden">
                    <button @click="accordionWaConnection = !accordionWaConnection" class="w-full flex items-center justify-between p-6 text-right group" type="button">
                        <div class="flex items-center gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="whatsapp.connected ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-gray-100 dark:bg-white/5 text-gray-500'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-2.875A8.035 8.035 0 0 1 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                </svg>
                            </div>
                            <div class="text-right">
                                <h3 class="text-base font-extrabold text-gray-900 dark:text-gray-100">ربط حساب واتساب</h3>
                                <p class="text-xs mt-0.5" :class="whatsapp.connected ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-400 dark:text-gray-500'">
                                    {{ whatsapp.connected ? 'متصل ومفعل — ' + whatsapp.instance_name : 'غير متصل — اضغط لإعداد الربط' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span v-if="whatsapp.connected" class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            <svg :class="accordionWaConnection ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition-transform duration-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>

                    <div v-show="accordionWaConnection" class="border-t border-gray-100 dark:border-white/5 p-6">
                        <!-- Disconnected State -->
                        <div v-if="!whatsapp.connected" class="space-y-6">
                            <div class="bg-gray-50 dark:bg-gray-900/30 p-8 rounded-2xl border border-gray-200 dark:border-gray-700/50 text-center flex flex-col items-center justify-center min-h-[280px]">
                                <div v-if="!qrCodeBase64 && !isLoadingQr" class="space-y-4">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-500/10 text-amber-500 mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900 dark:text-gray-100">واتساب غير متصل</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">اضغط على الزر أدناه لتوليد رمز QR ومسحه بهاتفك لتفعيل روبوت التقييم التلقائي.</p>
                                    <button @click="startWhatsappConnect" class="inline-flex items-center gap-2 px-6 py-3 border border-transparent text-sm font-bold rounded-2xl text-white bg-green-600 hover:bg-green-700 transition shadow-[0_4px_16px_0_rgba(34,197,94,0.2)]">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /></svg>
                                        توليد رمز QR للربط
                                    </button>
                                </div>

                                <div v-else-if="isLoadingQr" class="flex flex-col items-center gap-4">
                                    <div class="w-12 h-12 border-4 border-green-500 border-t-transparent rounded-full animate-spin"></div>
                                    <span class="text-sm font-semibold text-gray-500">جاري توليد رمز QR...</span>
                                </div>

                                <div v-else-if="qrCodeBase64" class="space-y-5 flex flex-col items-center">
                                    <h4 class="text-base font-bold text-gray-900 dark:text-gray-100">امسح رمز QR من تطبيق واتساب</h4>
                                    <div class="p-3 bg-white rounded-2xl shadow border border-gray-100">
                                        <img :src="qrCodeBase64" alt="WhatsApp QR Code" class="w-56 h-56 mx-auto" />
                                    </div>
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 font-semibold text-sm animate-pulse">
                                        <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span></span>
                                        بانتظار المسح من تطبيق واتساب...
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm leading-relaxed text-center">افتح واتساب على هاتفك، اذهب الى الاجهزة المرتبطة ثم اضغط ربط جهاز ووجه الكاميرا للشاشة.</p>
                                    <button @click="startWhatsappConnect" class="text-sm text-green-600 dark:text-green-400 hover:underline font-semibold">إعادة توليد رمز QR</button>
                                </div>
                            </div>
                        </div>

                        <!-- Connected State -->
                        <div v-else class="bg-emerald-500/5 border border-emerald-400/20 p-5 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-5">
                            <div class="flex items-center gap-4 text-right w-full md:w-auto">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                </div>
                                <div>
                                    <h4 class="text-base font-bold text-emerald-800 dark:text-emerald-400">حساب واتساب متصل ومفعل</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-mono">المثيل: {{ whatsapp.instance_name }} | الحالة: {{ whatsapp.status }}</p>
                                </div>
                            </div>
                            <button @click="disconnectWhatsapp" :disabled="isDisconnectingWhatsapp" class="inline-flex items-center gap-2 px-4 py-2.5 border border-transparent text-sm font-bold rounded-2xl text-white bg-rose-600 hover:bg-rose-700 transition shadow-[0_4px_12px_0_rgba(244,63,94,0.15)] disabled:opacity-50 shrink-0 w-full md:w-auto justify-center">
                                <svg v-if="isDisconnectingWhatsapp" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                {{ isDisconnectingWhatsapp ? 'جاري الفصل...' : 'قطع اتصال واتساب' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ===== ACCORDION 3: WhatsApp Automation Settings ===== -->
                <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] overflow-hidden">
                    <button @click="accordionWaSettings = !accordionWaSettings" class="w-full flex items-center justify-between p-6 text-right group" type="button">
                        <div class="flex items-center gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            </div>
                            <div class="text-right">
                                <h3 class="text-base font-extrabold text-gray-900 dark:text-gray-100">إعدادات الأتمتة</h3>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">تأخير الإرسال — الفحص الدوري — إعدادات Scanner</p>
                            </div>
                        </div>
                        <svg :class="accordionWaSettings ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition-transform duration-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div v-show="accordionWaSettings" class="border-t border-gray-100 dark:border-white/5 p-6 space-y-5">
                        <!-- Flash messages -->
                        <div v-if="flash.success" class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-400/20 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            {{ flash.success }}
                        </div>

                        <!-- Delay Hours Field -->
                        <div class="bg-gray-50/80 dark:bg-gray-900/20 p-5 rounded-2xl border border-gray-200 dark:border-gray-700/50 space-y-3">
                            <label class="block text-sm font-extrabold text-gray-800 dark:text-gray-200">ساعات تأخير إرسال رسالة التقييم</label>
                            <input type="number" v-model="delayHours" min="0" max="720" class="w-full max-w-xs rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-right font-mono text-sm outline-none transition" />
                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">المدة بالانتظار بعد تسليم الطلب قبل إرسال رسالة التقييم (ضع 0 للإرسال الفوري).</p>
                        </div>

                        <!-- Salla Scanner -->
                        <div class="bg-gray-50/80 dark:bg-gray-900/20 p-5 rounded-2xl border border-gray-200 dark:border-gray-700/50 space-y-5">
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                <div class="text-right">
                                    <label class="block text-sm font-extrabold text-gray-800 dark:text-gray-200">نظام الفحص الذكي للطلبات الفائتة (Salla Scanner)</label>
                                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 leading-relaxed max-w-lg">عند التفعيل، يفحص النظام طلباتك دورياً ويجدول رسائل التقييم لأي طلب لم تصله إشارة Webhook.</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                    <input type="checkbox" v-model="customQuestions.enable_salla_scanner" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    <span class="mr-2 text-xs font-bold text-gray-600 dark:text-gray-400">{{ customQuestions.enable_salla_scanner ? 'مفعل' : 'معطل' }}</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pt-4 border-t border-gray-200 dark:border-gray-700/50">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2">تكرار الفحص (بالدقائق)</label>
                                    <input type="number" v-model="customQuestions.salla_scanner_interval_minutes" min="10" max="1440" class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-right font-mono text-sm outline-none transition" />
                                    <p class="mt-1.5 text-[11px] text-gray-400">الحد الأدنى 10 دقائق</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2">المدى الزمني للفحص (بالساعات)</label>
                                    <input type="number" v-model="customQuestions.salla_scanner_lookback_hours" min="1" max="720" class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-right font-mono text-sm outline-none transition" />
                                    <p class="mt-1.5 text-[11px] text-gray-400">مثال: آخر 24 ساعة</p>
                                </div>
                                <div class="flex flex-col justify-end">
                                    <button type="button" @click="sync" class="inline-flex items-center justify-center gap-2 px-4 py-3 border border-gray-200 dark:border-gray-700 text-sm font-bold rounded-2xl text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition w-full">
                                        تشغيل الفحص الفوري
                                    </button>
                                    <p class="mt-1.5 text-[11px] text-gray-400 text-right">فحص ومزامنة الطلبات الآن</p>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button for Settings -->
                        <div class="flex justify-end pt-2">
                            <button type="button" @click="saveDelaySettingsOnly" :disabled="isSavingDelayOnly" class="inline-flex items-center gap-2 px-6 py-3 border border-transparent text-sm font-bold rounded-2xl text-white bg-blue-600 hover:bg-blue-700 transition shadow-[0_4px_16px_0_rgba(37,99,235,0.2)] disabled:opacity-50">
                                <svg v-if="isSavingDelayOnly" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                {{ isSavingDelayOnly ? 'جاري الحفظ...' : 'حفظ إعدادات الأتمتة' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ===== ACCORDION 4: WhatsApp Messages & Questions ===== -->
                <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] overflow-hidden">
                    <button @click="accordionWaQuestions = !accordionWaQuestions" class="w-full flex items-center justify-between p-6 text-right group" type="button">
                        <div class="flex items-center gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>
                            </div>
                            <div class="text-right">
                                <h3 class="text-base font-extrabold text-gray-900 dark:text-gray-100">رسائل وأسئلة روبوت واتساب</h3>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">تخصيص رسائل الترحيب والأسئلة التفاعلية</p>
                            </div>
                        </div>
                        <svg :class="accordionWaQuestions ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition-transform duration-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div v-show="accordionWaQuestions" class="border-t border-gray-100 dark:border-white/5 p-6 space-y-5">
                        <!-- Rating message -->
                        <div class="bg-gray-50/80 dark:bg-gray-900/20 p-5 rounded-2xl border border-gray-200 dark:border-gray-700/50 space-y-4">
                            <h5 class="text-sm font-extrabold text-gray-800 dark:text-gray-100">إعدادات رسالة التقييم بالنجوم</h5>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">الرسالة الترحيبية الاولى (طلب التقييم)</label>
                                <textarea v-model="customQuestions.rating_message" rows="3" class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition"></textarea>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 select-all cursor-pointer">{name} : اسم العميل</span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 select-all cursor-pointer">{order_number} : رقم الفاتورة</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">عنوان زر قائمة التقييم</label>
                                <input type="text" v-model="customQuestions.rating_button_label" class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition" placeholder="اختر التقييم بالنجوم..." />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2">مسميات خيارات التقييم</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div v-for="(star, i) in [['5', 'rating_label_5'], ['4', 'rating_label_4'], ['3', 'rating_label_3'], ['2', 'rating_label_2'], ['1', 'rating_label_1']]" :key="i">
                                        <span class="text-[10px] text-gray-400 block mb-1">{{ star[0] }} نجوم</span>
                                        <input type="text" v-model="(customQuestions as any)[star[1]]" class="w-full rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Warnings & Success messages -->
                        <div class="bg-gray-50/80 dark:bg-gray-900/20 p-5 rounded-2xl border border-gray-200 dark:border-gray-700/50 space-y-4">
                            <h5 class="text-sm font-extrabold text-gray-800 dark:text-gray-100">رسائل التنبيه والنجاح</h5>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">رسالة التنبيه عند إدخال رقم خاطئ</label>
                                <textarea v-model="customQuestions.rating_invalid_warning" rows="2" class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">رسالة التحويل للدعم عند الرد غير المتوقع</label>
                                <textarea v-model="customQuestions.invalid_rating_message" rows="2" placeholder="مثال: سيتم التواصل معكم حالا من خدمة العملاء..." class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">رسالة النجاح عند اكمال التقييم</label>
                                <textarea v-model="customQuestions.success_message" rows="2" class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition"></textarea>
                            </div>
                        </div>

                        <!-- Questions section -->
                        <div class="bg-gray-50/80 dark:bg-gray-900/20 p-5 rounded-2xl border border-gray-200 dark:border-gray-700/50 space-y-4">
                            <div class="flex items-center justify-between">
                                <h5 class="text-sm font-extrabold text-gray-800 dark:text-gray-100">الاسئلة التفاعلية بعد التقييم</h5>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="customQuestions.enable_questions" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-violet-600"></div>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">في حال التعطيل، تنتهي المحادثة بعد التقييم بالنجوم مباشرة لتقليل تكلفة الرسائل.</p>

                            <div v-if="customQuestions.enable_questions" class="space-y-4 pt-3 border-t border-gray-200 dark:border-gray-700/50">
                                <div v-if="customQuestions.questions.length === 0" class="text-center py-6 text-gray-500 dark:text-gray-400 text-sm">
                                    لا يوجد اسئلة. اضغط على اضافة سؤال للبدء.
                                </div>

                                <div v-else class="space-y-3">
                                    <div v-for="(q, index) in customQuestions.questions" :key="q.id" class="bg-white dark:bg-gray-800/60 p-4 rounded-2xl border border-gray-200 dark:border-gray-700 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-extrabold text-violet-600 dark:text-violet-400">السؤال {{ index + 1 }}</span>
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" @click="moveQuestionUp(index)" :disabled="index === 0" class="p-1.5 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-500 hover:bg-gray-100 disabled:opacity-30 transition text-xs">▲</button>
                                                <button type="button" @click="moveQuestionDown(index)" :disabled="index === customQuestions.questions.length - 1" class="p-1.5 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-500 hover:bg-gray-100 disabled:opacity-30 transition text-xs">▼</button>
                                                <button type="button" @click="removeQuestion(index)" class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/20 text-rose-500 hover:bg-rose-100 transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">نوع السؤال</label>
                                                <select :value="q.type" @change="changeQuestionType(index, ($event.target as HTMLSelectElement).value as any)" class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition">
                                                    <option value="buttons">خيارات الرد (أزرار)</option>
                                                    <option value="text">تعليق نصي (مفتوح)</option>
                                                    <option value="media">مرفق صورة أو فيديو</option>
                                                </select>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">نص السؤال</label>
                                                <input type="text" v-model="q.text" required class="w-full rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition" placeholder="مثال: هل أعجبك المنتج؟" />
                                            </div>
                                        </div>

                                        <!-- Options List (if type is buttons) -->
                                        <div v-if="q.type === 'buttons'" class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl border border-gray-150 dark:border-gray-800 space-y-3">
                                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">خيارات الرد (أزرار تفاعلية - بحد أقصى 3 خيارات)</label>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <div v-for="optIdx in [0, 1, 2]" :key="optIdx" class="flex gap-2">
                                                    <span class="text-xs text-gray-400 dark:text-gray-500 self-center font-mono w-4">{{ optIdx + 1 }}</span>
                                                    <input type="text" v-model="q.options[optIdx]" class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 text-right text-xs outline-none transition" placeholder="خيار فارغ..." />
                                                </div>
                                            </div>
                                            <p class="text-[10px] text-gray-400 dark:text-gray-500">* سيتم تجاهل الخيارات الفارغة عند إرسال الأزرار للعميل.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Question button -->
                                <div class="flex justify-center pt-2">
                                    <button type="button" @click="addQuestion" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/20 border border-violet-200 dark:border-violet-900/50 rounded-xl hover:bg-violet-100 dark:hover:bg-violet-950/40 transition">
                                        إضافة سؤال جديد
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Save button for Questions only -->
                        <div class="flex justify-end pt-2">
                            <button type="button" @click="saveQuestionsOnly" :disabled="isSavingQuestionsOnly" class="inline-flex items-center gap-2 px-6 py-3 border border-transparent text-sm font-bold rounded-2xl text-white bg-violet-600 hover:bg-violet-700 transition shadow-[0_4px_16px_0_rgba(124,58,237,0.2)] disabled:opacity-50">
                                <svg v-if="isSavingQuestionsOnly" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                {{ isSavingQuestionsOnly ? 'جاري الحفظ...' : 'حفظ رسائل وأسئلة روبوت واتساب' }}
                            </button>
                        </div>
                    </div>
                </div>
                </div> <!-- End Main Settings Tab -->

                <!-- Reviews moderation container -->
                <div v-else-if="activeMainTab === 'reviews'" class="space-y-8 animate-fade-in text-right" dir="rtl">
                    <!-- Notification banners for review actions success -->
                    <div v-if="reviewsSuccessMsg" class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-250 dark:border-emerald-900/50 p-4 rounded-xl text-emerald-600 dark:text-emerald-400 text-sm flex items-center gap-2 shadow-sm justify-start">
                        <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>{{ reviewsSuccessMsg }}</span>
                    </div>

                    <!-- Reviews Stats Summary (Apple Glassmorphism) -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                            <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">إجمالي التقييمات</span>
                            <div class="flex items-baseline justify-between mt-3">
                                <h4 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 font-mono">{{ reviewsList.length }}</h4>
                                <span class="text-[10px] bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 px-2.5 py-1 rounded-lg font-bold">كافة الحالات</span>
                            </div>
                        </div>
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                            <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">المقبولة والنشطة</span>
                            <div class="flex items-baseline justify-between mt-3">
                                <h4 class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">{{ reviewsList.filter(r => r.status === 'approved').length }}</h4>
                                <span class="text-[10px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-2.5 py-1 rounded-lg font-bold">تظهر بالـ Widget</span>
                            </div>
                        </div>
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                            <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">بانتظار المراجعة</span>
                            <div class="flex items-baseline justify-between mt-3">
                                <h4 class="text-3xl font-extrabold text-amber-500 dark:text-amber-400 font-mono">{{ reviewsList.filter(r => r.status === 'pending').length }}</h4>
                                <span class="text-[10px] bg-amber-500/10 text-amber-600 dark:text-amber-400 px-2.5 py-1 rounded-lg font-bold">تحتاج إجراء</span>
                            </div>
                        </div>
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-6 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                            <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">متوسط التقييم</span>
                            <div class="flex items-baseline justify-between mt-3">
                                <h4 class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">
                                    {{ reviewsList.length > 0 ? (reviewsList.reduce((acc, curr) => acc + curr.rating, 0) / reviewsList.length).toFixed(1) : '0.0' }}
                                </h4>
                                <span class="text-[10px] bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 px-2.5 py-1 rounded-lg font-bold">من 5</span>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Control Card (Apple Glassmorphism) -->
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-5 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                            <!-- Search & Status Filter -->
                            <div class="flex flex-wrap items-center gap-4 w-full md:w-auto justify-start">
                                <button 
                                    @click="fetchReviewsData(true)" 
                                    :disabled="isLoadingReviews"
                                    class="p-2.5 bg-gray-50 dark:bg-gray-900 text-gray-500 hover:text-emerald-600 dark:hover:text-emerald-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                                    title="تحديث التقييمات"
                                >
                                    <svg 
                                        :class="{ 'animate-spin': isLoadingReviews }" 
                                        class="w-5 h-5" 
                                        fill="none" 
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89H18"></path>
                                    </svg>
                                </button>
                                <a 
                                    href="/reviews/export"
                                    class="p-2.5 bg-gray-50 dark:bg-gray-900 text-gray-500 hover:text-emerald-600 dark:hover:text-emerald-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 flex items-center justify-center gap-1.5"
                                    title="تصدير التقييمات بصيغة CSV"
                                    download
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span class="text-xs font-bold hidden sm:inline">تصدير CSV</span>
                                </a>
                                <div class="relative w-full md:w-80">
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400 dark:text-gray-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </span>
                                    <input 
                                        type="text" 
                                        v-model="reviewsSearchQuery"
                                        placeholder="ابحث باسم العميل، محتوى التقييم، رقم الطلب..." 
                                        class="w-full pr-10 pl-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 transition-all duration-200 text-right"
                                    />
                                </div>
                                
                                <div class="flex gap-2">
                                    <button 
                                        @click="reviewsStatusFilter = 'all'"
                                        :class="reviewsStatusFilter === 'all' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/10' : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'"
                                        class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                                    >
                                        الكل
                                    </button>
                                    <button 
                                        @click="reviewsStatusFilter = 'pending'"
                                        :class="reviewsStatusFilter === 'pending' ? 'bg-amber-500 text-white shadow-md shadow-amber-500/10' : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'"
                                        class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center gap-1.5"
                                    >
                                        <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
                                        قيد الانتظار
                                    </button>
                                    <button 
                                        @click="reviewsStatusFilter = 'approved'"
                                        :class="reviewsStatusFilter === 'approved' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/10' : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'"
                                        class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                                    >
                                        مقبول
                                    </button>
                                    <button 
                                        @click="reviewsStatusFilter = 'rejected'"
                                        :class="reviewsStatusFilter === 'rejected' ? 'bg-rose-500 text-white shadow-md shadow-rose-500/10' : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-100'"
                                        class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                                    >
                                        مرفوض
                                    </button>
                                </div>
                            </div>

                            <!-- Display Mode Toggle -->
                            <div class="flex items-center bg-gray-100 dark:bg-gray-900 p-1 rounded-2xl w-full md:w-auto">
                                <button 
                                    @click="reviewsTabMode = 'order'"
                                    :class="reviewsTabMode === 'order' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-sm font-bold' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                                    class="flex-1 md:flex-none px-6 py-2 rounded-xl text-sm font-medium transition-all duration-200 focus:outline-none"
                                >
                                    حسب الطلبات
                                </button>
                                <button 
                                    @click="reviewsTabMode = 'product'"
                                    :class="reviewsTabMode === 'product' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-sm font-bold' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                                    class="flex-1 md:flex-none px-6 py-2 rounded-xl text-sm font-medium transition-all duration-200 focus:outline-none"
                                >
                                    حسب المنتجات
                                </button>
                            </div>

                        </div>
                    </div>

                    <!-- Reviews List / Products Grid -->
                    <div v-if="isLoadingReviews" class="flex flex-col items-center justify-center py-16 space-y-4">
                        <div class="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">جاري تحميل وإعداد التقييمات...</span>
                    </div>

                    <div v-else-if="reviewsError" class="bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/50 p-4 rounded-xl text-rose-600 dark:text-rose-400 text-sm">
                        {{ reviewsError }}
                    </div>

                    <!-- Display By Order (حسب الطلبات) -->
                    <div v-else-if="reviewsTabMode === 'order'" class="space-y-6">
                        <div v-if="filteredReviews.length === 0" class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl border border-white/20 dark:border-white/5 p-12 rounded-3xl text-center">
                            <p class="text-gray-500 dark:text-gray-400">لا توجد تقييمات مطابقة للفلاتر الحالية.</p>
                        </div>
                        
                        <div 
                            v-for="review in filteredReviews" 
                            :key="review.id" 
                            class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl border border-white/20 dark:border-white/5 rounded-3xl p-6 shadow-[0_4px_20px_0_rgba(0,0,0,0.04)] dark:shadow-[0_4px_20px_0_rgba(0,0,0,0.15)] transition-all duration-300 hover:shadow-[0_8px_32px_0_rgba(0,0,0,0.06)] hover:scale-[1.005] flex flex-col md:flex-row md:items-start gap-6 text-right"
                        >
                            <!-- User Avatar & Rating Details -->
                            <div class="flex md:flex-col items-center md:items-start gap-4 shrink-0 md:w-48 text-right">
                                <div class="h-12 w-12 rounded-full bg-gradient-to-tr from-emerald-400 to-teal-500 flex items-center justify-center text-white font-bold text-lg shadow-inner">
                                    {{ review.customer?.name?.charAt(0) || 'ع' }}
                                </div>
                                <div class="text-right">
                                    <h5 class="font-bold text-gray-900 dark:text-gray-100">{{ review.customer?.name || 'عميل مجهول' }}</h5>
                                    <p class="text-xs text-gray-450 mt-0.5">رقم الفاتورة: {{ formatInvoiceNumber(review.order?.invoice_number) }}</p>
                                    <p class="text-xs text-gray-450 mt-0.5">{{ new Date(review.created_at).toLocaleDateString('ar-SA') }}</p>
                                </div>
                            </div>

                            <!-- Review Contents -->
                            <div class="flex-1 text-right space-y-4">
                                <!-- Star Rating & Status Badge -->
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-0.5 text-amber-400">
                                        <span v-for="star in 5" :key="star" class="text-lg">
                                            {{ star <= review.rating ? '★' : '☆' }}
                                        </span>
                                    </div>
                                    
                                    <!-- Status Badge -->
                                    <span 
                                        :class="{
                                            'bg-amber-50 text-amber-700 border-amber-205 dark:bg-amber-955/30 dark:text-amber-400 dark:border-amber-900/50': review.status === 'pending',
                                            'bg-emerald-50 text-emerald-700 border-emerald-205 dark:bg-emerald-955/30 dark:text-emerald-400 dark:border-emerald-900/50': review.status === 'approved',
                                            'bg-rose-50 text-rose-700 border-rose-205 dark:bg-rose-955/30 dark:text-rose-400 dark:border-rose-900/50': review.status === 'rejected',
                                        }"
                                        class="px-2.5 py-1 rounded-lg border text-xs font-bold"
                                    >
                                        {{ review.status === 'pending' ? 'بانتظار المراجعة' : review.status === 'approved' ? 'مقبول ونشط' : 'مرفوض' }}
                                    </span>
                                </div>

                                <!-- Review Text -->
                                <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed whitespace-pre-line bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl">
                                    {{ review.comment || '(لا يوجد تعليق مكتوب)' }}
                                </p>

                                <!-- Custom Q&A Details -->
                                <div v-if="review.answers && review.answers.length > 0" class="border-t border-gray-100 dark:border-gray-700/50 pt-3 mt-3">
                                    <details class="group">
                                        <summary class="list-none flex items-center justify-between text-xs font-bold text-gray-500 hover:text-emerald-600 dark:text-gray-400 dark:hover:text-emerald-400 cursor-pointer select-none">
                                            <span>تفاصيل الأسئلة المخصصة والردود ({{ review.answers.length }})</span>
                                            <svg class="w-4 h-4 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </summary>
                                        <div class="mt-3 space-y-3 pl-2 pr-2 border-r-2 border-emerald-500/30">
                                            <div v-for="(ans, aIdx) in review.answers" :key="aIdx" class="text-xs">
                                                <p class="font-bold text-gray-600 dark:text-gray-400">{{ ans.text }}</p>
                                                <p class="text-gray-850 dark:text-gray-200 mt-1 bg-gray-50 dark:bg-gray-900 p-2 rounded-lg">{{ ans.response }}</p>
                                            </div>
                                        </div>
                                    </details>
                                </div>

                                <!-- Attached Media -->
                                <div v-if="review.media_url" class="border-t border-gray-100 dark:border-gray-700/50 pt-3 mt-3">
                                    <p class="text-xs font-bold text-gray-500 mb-2">المرفقات المرسلة:</p>
                                    <div class="flex">
                                        <template v-if="review.media_type === 'image' || review.media_url.match(/\.(jpeg|jpg|gif|png)$/i)">
                                            <a :href="review.media_url" target="_blank" class="block group relative overflow-hidden rounded-xl border border-gray-205 dark:border-gray-700 max-w-[200px]">
                                                <img :src="review.media_url" class="h-28 w-auto object-cover transition-transform duration-300 group-hover:scale-105" alt="Review Media" />
                                                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-200">
                                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                </div>
                                            </a>
                                        </template>
                                        <template v-else-if="review.media_type === 'video' || review.media_url.match(/\.(mp4|webm|ogg|mov)$/i)">
                                            <video controls class="max-h-28 rounded-xl border border-gray-205 dark:border-gray-700">
                                                <source :src="review.media_url" />
                                                تصفحك لا يدعم تشغيل الفيديو.
                                            </video>
                                        </template>
                                        <template v-else>
                                            <a :href="review.media_url" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-205 rounded-xl text-xs text-emerald-600 hover:bg-emerald-50 dark:border-gray-700">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                تنزيل الملف المرفق
                                            </a>
                                        </template>
                                    </div>
                                </div>

                                <!-- Bought Products inside order -->
                                <div class="border-t border-gray-100 dark:border-gray-700/50 pt-3 mt-3">
                                    <p class="text-xs font-bold text-gray-500 mb-2">المنتجات التي تم شراؤها:</p>
                                    <div class="flex flex-wrap gap-3">
                                        <div 
                                            v-for="prod in review.order?.products" 
                                            :key="prod.id" 
                                            class="inline-flex items-center gap-2 bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700 px-3 py-1.5 rounded-xl"
                                        >
                                            <img v-if="prod.image_url" :src="prod.image_url" class="h-6 w-6 object-cover rounded-md" />
                                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ prod.name }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Official Merchant Reply Section -->
                                <div class="border-t border-gray-100 dark:border-gray-700/50 pt-4 mt-4 space-y-3">
                                    <h6 class="text-xs font-bold text-gray-500 dark:text-gray-400">الرد الرسمي للمتجر</h6>
                                    
                                    <!-- Saved Reply -->
                                    <div v-if="review.reply" class="bg-gray-50 dark:bg-gray-900/40 p-4 rounded-xl border border-gray-100 dark:border-gray-700/50 flex justify-between items-start gap-4">
                                        <div class="space-y-1 flex-1">
                                            <p class="text-sm text-gray-800 dark:text-gray-200">{{ review.reply }}</p>
                                            <p v-if="review.replied_at" class="text-[10px] text-gray-450 font-mono">
                                                تاريخ الرد: {{ new Date(review.replied_at).toLocaleDateString('ar-SA') }}
                                            </p>
                                        </div>
                                        <button 
                                            @click="deleteReply(review.id)" 
                                            :disabled="isSubmittingReply[review.id]"
                                            class="text-xs text-rose-600 hover:text-rose-700 font-bold shrink-0 focus:outline-none transition-colors duration-150"
                                        >
                                            حذف الرد
                                        </button>
                                    </div>
                                    
                                    <!-- Reply Form -->
                                    <div v-else class="space-y-2">
                                        <textarea 
                                            v-model="replyForms[review.id]" 
                                            rows="2" 
                                            placeholder="اكتب رد المتجر الرسمي هنا ليظهر بالمتجر..." 
                                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 text-right text-xs"
                                        ></textarea>
                                        <div class="flex justify-end">
                                            <button 
                                                @click="submitReply(review.id)" 
                                                :disabled="isSubmittingReply[review.id] || !replyForms[review.id]?.trim()"
                                                class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-xs font-bold rounded-xl text-white bg-indigo-650 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150 disabled:opacity-50"
                                            >
                                                <span v-if="isSubmittingReply[review.id]">جاري الحفظ...</span>
                                                <span v-else>حفظ وإرسال الرد</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions buttons -->
                            <div class="flex md:flex-col items-center justify-end gap-2.5 shrink-0 border-t md:border-t-0 md:border-r border-gray-100 dark:border-gray-700/50 pt-4 md:pt-0 md:pr-4 w-full md:w-auto">
                                <button 
                                    v-if="review.status !== 'approved'"
                                    @click="updateReviewStatus(review.id, 'approved')"
                                    class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2 border border-transparent text-xs font-bold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 transition-colors duration-150 w-full"
                                >
                                    قبول ونشر
                                </button>
                                <button 
                                    v-if="review.status !== 'rejected'"
                                    @click="updateReviewStatus(review.id, 'rejected')"
                                    class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2 border border-transparent text-xs font-bold rounded-xl text-white bg-amber-500 hover:bg-amber-600 transition-colors duration-150 w-full"
                                >
                                    رفض وحظر
                                </button>
                                <button 
                                    @click="deleteReview(review.id)"
                                    class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2 border border-transparent text-xs font-bold rounded-xl text-white bg-rose-600 hover:bg-rose-700 transition-colors duration-150 w-full"
                                >
                                    حذف نهائي
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Display By Product (حسب المنتجات) -->
                    <div v-else-if="reviewsTabMode === 'product'" class="space-y-8">
                        <div v-if="filteredProducts.length === 0" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-12 rounded-2xl text-center">
                            <p class="text-gray-500 dark:text-gray-400">لا توجد منتجات تحتوي على تقييمات مطابقة للفلاتر.</p>
                        </div>

                        <div 
                            v-for="product in filteredProducts" 
                            :key="product.id"
                            class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl overflow-hidden shadow-md transition-all duration-300 hover:shadow-lg text-right"
                        >
                            <!-- Product Details Header Banner -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col md:flex-row items-center justify-between gap-4">
                                <div class="flex items-center gap-4 text-right">
                                    <img v-if="product.image_url" :src="product.image_url" class="h-16 w-16 object-cover rounded-xl shadow-sm border border-gray-200 dark:border-gray-700" />
                                    <div>
                                        <h4 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ product.name }}</h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 px-2 py-0.5 rounded font-mono">ID: {{ product.salla_product_id }}</span>
                                            <a v-if="product.product_url" :href="product.product_url" target="_blank" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">عرض في المتجر 🔗</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stats info -->
                                <div class="flex gap-6">
                                    <div class="text-center">
                                        <p class="text-xs text-gray-450 font-medium">إجمالي التقييمات</p>
                                        <p class="text-lg font-bold text-gray-800 dark:text-gray-100 mt-0.5">{{ product.reviews_count }}</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xs text-gray-450 font-medium">متوسط التقييم</p>
                                        <p class="text-lg font-bold text-amber-500 mt-0.5">{{ product.reviews_avg_rating }} ⭐</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Reviews Nested List -->
                            <div class="p-6 divide-y divide-gray-100 dark:divide-gray-700/50 space-y-6">
                                <div v-if="product.filtered_reviews.length === 0" class="text-center py-6 text-gray-450 text-sm">
                                    لا توجد تقييمات مطابقة للفلاتر الحالية لهذا المنتج.
                                </div>
                                <div 
                                    v-for="(review, rIdx) in product.filtered_reviews" 
                                    :key="review.id" 
                                    :class="{ 'pt-6': rIdx > 0 }"
                                    class="flex flex-col md:flex-row md:items-start justify-between gap-6 text-right"
                                >
                                    <div class="flex-1 space-y-3">
                                        <!-- Customer, Rating and Status -->
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <div class="h-8 w-8 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-sm">
                                                    {{ review.customer?.name?.charAt(0) || 'ع' }}
                                                </div>
                                                <div>
                                                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ review.customer?.name || 'عميل مجهول' }}</span>
                                                    <span class="text-xs text-gray-450 pr-2">{{ formatInvoiceNumber(review.order?.invoice_number) }} • {{ new Date(review.created_at).toLocaleDateString('ar-SA') }}</span>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <div class="flex gap-0.5 text-amber-400">
                                                    <span v-for="star in 5" :key="star" class="text-sm">
                                                        {{ star <= review.rating ? '★' : '☆' }}
                                                    </span>
                                                </div>
                                                <span 
                                                    :class="{
                                                        'bg-amber-50 text-amber-700 border-amber-205 dark:bg-amber-955/30 dark:text-amber-400 dark:border-amber-900/50': review.status === 'pending',
                                                        'bg-emerald-50 text-emerald-700 border-emerald-205 dark:bg-emerald-955/30 dark:text-emerald-400 dark:border-emerald-900/50': review.status === 'approved',
                                                        'bg-rose-50 text-rose-700 border-rose-205 dark:bg-rose-955/30 dark:text-rose-400 dark:border-rose-900/50': review.status === 'rejected',
                                                    }"
                                                    class="px-2 py-0.5 rounded border text-[10px] font-bold"
                                                >
                                                    {{ review.status === 'pending' ? 'قيد الانتظار' : review.status === 'approved' ? 'مقبول' : 'مرفوض' }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Comment -->
                                        <p class="text-gray-700 dark:text-gray-300 text-sm bg-gray-50 dark:bg-gray-900/30 p-3 rounded-lg">
                                            {{ review.comment || '(لا يوجد تعليق مكتوب)' }}
                                        </p>

                                        <!-- Media Attachments -->
                                        <div v-if="review.media_url" class="flex mt-2">
                                            <template v-if="review.media_type === 'image' || review.media_url.match(/\.(jpeg|jpg|gif|png)$/i)">
                                                <a :href="review.media_url" target="_blank" class="block rounded-lg overflow-hidden border border-gray-250 max-w-[150px]">
                                                    <img :src="review.media_url" class="h-20 w-auto object-cover" />
                                                </a>
                                            </template>
                                            <template v-else-if="review.media_type === 'video' || review.media_url.match(/\.(mp4|webm|ogg|mov)$/i)">
                                                <video controls class="max-h-20 rounded-lg border">
                                                    <source :src="review.media_url" />
                                                </video>
                                            </template>
                                        </div>

                                        <!-- Official Merchant Reply Section -->
                                        <div class="border-t border-gray-100 dark:border-gray-700/50 pt-4 mt-4 space-y-3">
                                            <h6 class="text-xs font-bold text-gray-500 dark:text-gray-400">الرد الرسمي للمتجر</h6>
                                            
                                            <!-- Saved Reply -->
                                            <div v-if="review.reply" class="bg-gray-50 dark:bg-gray-900/40 p-4 rounded-xl border border-gray-100 dark:border-gray-700/50 flex justify-between items-start gap-4">
                                                <div class="space-y-1 flex-1">
                                                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ review.reply }}</p>
                                                    <p v-if="review.replied_at" class="text-[10px] text-gray-450 font-mono">
                                                        تاريخ الرد: {{ new Date(review.replied_at).toLocaleDateString('ar-SA') }}
                                                    </p>
                                                </div>
                                                <button 
                                                    @click="deleteReply(review.id)" 
                                                    :disabled="isSubmittingReply[review.id]"
                                                    class="text-xs text-rose-600 hover:text-rose-700 font-bold shrink-0 focus:outline-none transition-colors duration-150"
                                                >
                                                    حذف الرد
                                                </button>
                                            </div>
                                            
                                            <!-- Reply Form -->
                                            <div v-else class="space-y-2">
                                                <textarea 
                                                    v-model="replyForms[review.id]" 
                                                    rows="2" 
                                                    placeholder="اكتب رد المتجر الرسمي هنا ليظهر بالمتجر..." 
                                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-3 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 text-right text-xs"
                                                ></textarea>
                                                <div class="flex justify-end">
                                                    <button 
                                                        @click="submitReply(review.id)" 
                                                        :disabled="isSubmittingReply[review.id] || !replyForms[review.id]?.trim()"
                                                        class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-xs font-bold rounded-xl text-white bg-indigo-650 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150 disabled:opacity-50"
                                                    >
                                                        <span v-if="isSubmittingReply[review.id]">جاري الحفظ...</span>
                                                        <span v-else>حفظ وإرسال الرد</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quick actions -->
                                    <div class="flex md:flex-col items-center justify-end gap-2 shrink-0 self-center w-full md:w-auto">
                                        <button 
                                            v-if="review.status !== 'approved'"
                                            @click="updateReviewStatus(review.id, 'approved')"
                                            class="inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-bold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 w-full md:w-auto"
                                        >
                                            قبول
                                        </button>
                                        <button 
                                            v-if="review.status !== 'rejected'"
                                            @click="updateReviewStatus(review.id, 'rejected')"
                                            class="inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-bold rounded-lg text-white bg-amber-500 hover:bg-amber-600 w-full md:w-auto"
                                        >
                                            رفض
                                        </button>
                                        <button 
                                            @click="deleteReview(review.id)"
                                            class="inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-bold rounded-lg text-white bg-rose-600 hover:bg-rose-700 w-full md:w-auto"
                                        >
                                            حذف
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- End Reviews moderation container -->

                <!-- ===== TAB: Sandbox Simulator ===== -->
                <div v-else-if="activeMainTab === 'sandbox'" class="space-y-6 animate-fade-in text-right" dir="rtl">
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-8 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] text-right">
                        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100 dark:border-white/5">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9.75 16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">محاكي سلة للتجربة السريعة</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Salla Sandbox Simulator — اختبر روبوت واتساب والتقييمات التلقائية فوراً وبشكل آمن</p>
                            </div>
                        </div>

                        <div v-if="!whatsapp.connected" class="bg-amber-500/10 border border-amber-400/20 p-4 rounded-2xl text-amber-700 dark:text-amber-400 text-sm font-semibold mb-6">
                            تنبيه: يجب ربط حساب واتساب أولاً لاستلام الرسائل التجريبية.
                        </div>

                        <form @submit.prevent="simulateOrder" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">اسم العميل التجريبي</label>
                                    <input type="text" v-model="sandboxForm.customer_name" required class="w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-4 py-3.5 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-right text-sm outline-none transition" placeholder="أحمد العتيبي" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">رقم الجوال (واتساب للاختبار)</label>
                                    <input type="text" v-model="sandboxForm.customer_phone" required class="w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-4 py-3.5 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-right text-sm font-mono outline-none transition" placeholder="966500000000" />
                                    <p class="mt-1.5 text-[11px] text-gray-400">الرقم بالصيغة الدولية دون مفتاح + (مثال: 966XXXXXXXXX)</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">رقم الطلب المرجعي</label>
                                    <input type="text" v-model="sandboxForm.order_reference" required class="w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-4 py-3.5 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-right text-sm font-mono outline-none transition" placeholder="SALLA-9988" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">إجمالي الطلب (ريال)</label>
                                    <input type="number" step="0.01" v-model="sandboxForm.order_total" required class="w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-4 py-3.5 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-right text-sm font-mono outline-none transition" />
                                </div>
                            </div>

                            <label class="flex items-center gap-3 cursor-pointer select-none w-fit">
                                <input type="checkbox" id="force_immediate" v-model="sandboxForm.force_immediate" class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer" />
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">تجاوز ساعات التأخير وإرسال رسالة التقييم فورا</span>
                            </label>

                            <div class="flex justify-end">
                                <button type="submit" :disabled="isSimulating || !whatsapp.connected" class="inline-flex items-center gap-2 px-6 py-3.5 border border-transparent text-sm font-bold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none transition-all shadow-[0_4px_16px_0_rgba(79,70,229,0.2)] disabled:opacity-50">
                                    <svg v-if="isSimulating" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    {{ isSimulating ? 'جاري إرسال الطلب...' : 'إرسال طلب تجريبي للمحاكاة' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ===== TAB: Billing & Subscriptions ===== -->
                <div v-else-if="activeMainTab === 'billing'" class="space-y-6 animate-fade-in text-right" dir="rtl">
                    <!-- Current Plan status card -->
                    <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-8 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)]">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 border-b border-gray-100 dark:border-white/5 pb-6">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-19.5 5.25h19.5m-19.5 0h19.5M2.25 9.75h19.5m-19.5 3h19.5m-19.5 0h19.5M2.25 12h19.5m-19.5 0h19.5M12 12.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">باقة الاشتراك الحالية</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">تفاصيل الباقة النشطة والحدود المستهلكة لرسائل التقييم</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-4 py-1.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold text-sm">
                                    الباقة النشطة: {{ subscription ? subscription.plan_name.toUpperCase() : 'FREE' }}
                                </span>
                            </div>
                        </div>

                        <!-- Usage progress -->
                        <div class="mt-6 space-y-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-semibold text-gray-600 dark:text-gray-450">استهلاك الباقة للرسائل</span>
                                <span class="font-mono font-bold text-gray-800 dark:text-gray-250">
                                    {{ subscription ? subscription.current_period_usage : 0 }} / {{ subscription ? subscription.monthly_limit : 50 }} رسالة
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 h-3 rounded-full overflow-hidden">
                                <div 
                                    class="bg-indigo-650 h-full rounded-full transition-all duration-500"
                                    :style="{ width: Math.min(((subscription ? subscription.current_period_usage : 0) / (subscription ? subscription.monthly_limit : 50)) * 100, 100) + '%' }"
                                ></div>
                            </div>
                            <div class="flex flex-col sm:flex-row justify-between text-xs text-gray-400 dark:text-gray-500 gap-2 pt-2">
                                <span>تاريخ بدء الفترة: {{ subscription?.current_period_start ? new Date(subscription.current_period_start).toLocaleDateString('ar-SA') : '--' }}</span>
                                <span>تاريخ التجديد/الانتهاء: {{ subscription?.current_period_end ? new Date(subscription.current_period_end).toLocaleDateString('ar-SA') : '--' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Free Plan Card -->
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-8 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between" :class="{ 'ring-2 ring-indigo-650': subscription?.plan_name === 'free' }">
                            <div>
                                <h4 class="text-lg font-bold text-gray-800 dark:text-gray-200">الباقة المجانية</h4>
                                <p class="text-xs text-gray-450 dark:text-gray-500 mt-2">مثالية لتجربة المنصة والبدء الفوري.</p>
                                <div class="mt-6 flex items-baseline gap-1">
                                    <span class="text-4xl font-extrabold text-gray-900 dark:text-white">0</span>
                                    <span class="text-sm font-semibold text-gray-400 dark:text-gray-500">ريال / شهرياً</span>
                                </div>
                                <ul class="mt-6 space-y-4 text-sm text-gray-600 dark:text-gray-400">
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-650 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>50 رسالة تقييم تفاعلية شهرياً</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-650 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>ربط تلقائي مع سلة وواتساب</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-amber-500">
                                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                        <span>ظهور العلامة المائية للـ Widget</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="mt-8">
                                <button 
                                    disabled
                                    class="w-full py-3 px-4 rounded-xl text-sm font-bold text-center border border-gray-250 dark:border-gray-700 text-gray-400 dark:text-gray-500 cursor-default bg-gray-50 dark:bg-gray-900/50"
                                >
                                    {{ subscription?.plan_name === 'free' ? 'باقتك الحالية' : 'الباقة المجانية' }}
                                </button>
                            </div>
                        </div>

                        <!-- Startup Plan Card -->
                        <div class="bg-white/85 dark:bg-[#1C1C1E]/85 backdrop-blur-xl p-8 rounded-3xl border border-white/25 dark:border-white/10 shadow-[0_12px_40px_0_rgba(0,0,0,0.06)] dark:shadow-[0_12px_40px_0_rgba(0,0,0,0.35)] flex flex-col justify-between relative" :class="{ 'ring-2 ring-indigo-600': subscription?.plan_name === 'startup' }">
                            <div class="absolute -top-3 left-6 px-3 py-1 bg-indigo-600 text-white text-[10px] font-extrabold rounded-full tracking-wider uppercase">الأكثر شعبية</div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-800 dark:text-gray-200">باقة الانطلاق (Startup)</h4>
                                <p class="text-xs text-gray-450 dark:text-gray-500 mt-2">للمتاجر النامية التي تحتاج إلى هوية مخصصة بالكامل.</p>
                                <div class="mt-6 flex items-baseline gap-1">
                                    <span class="text-4xl font-extrabold text-gray-900 dark:text-white font-mono">99</span>
                                    <span class="text-sm font-semibold text-gray-400 dark:text-gray-500">ريال / شهرياً</span>
                                </div>
                                <ul class="mt-6 space-y-4 text-sm text-gray-600 dark:text-gray-400">
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-650 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>400 رسالة تقييم تفاعلية شهرياً</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-650 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span class="font-bold text-gray-800 dark:text-gray-200">إزالة العلامة المائية للـ Widget بالكامل</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-650 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>خيارات وأسئلة غير محدودة ومخصصة</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="mt-8">
                                <button 
                                    @click="openUpgradeModal('startup')"
                                    :disabled="subscription?.plan_name === 'startup'"
                                    class="w-full py-3 px-4 rounded-xl text-sm font-bold text-center transition duration-200"
                                    :class="subscription?.plan_name === 'startup'
                                        ? 'bg-gray-50 dark:bg-gray-900/50 text-gray-400 dark:text-gray-500 border border-gray-250 dark:border-gray-700 cursor-default'
                                        : 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-600/20'"
                                >
                                    {{ subscription?.plan_name === 'startup' ? 'باقتك الحالية' : 'ترقية الباقة الآن' }}
                                </button>
                            </div>
                        </div>

                        <!-- Growth Plan Card -->
                        <div class="bg-white/80 dark:bg-[#1C1C1E]/80 backdrop-blur-xl p-8 rounded-3xl border border-white/20 dark:border-white/5 shadow-[0_8px_32px_0_rgba(0,0,0,0.04)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.2)] flex flex-col justify-between" :class="{ 'ring-2 ring-indigo-600': subscription?.plan_name === 'growth' }">
                            <div>
                                <h4 class="text-lg font-bold text-gray-800 dark:text-gray-200">باقة النمو (Growth)</h4>
                                <p class="text-xs text-gray-450 dark:text-gray-500 mt-2">للمتاجر الكبيرة ذات المبيعات الضخمة والنشطة.</p>
                                <div class="mt-6 flex items-baseline gap-1">
                                    <span class="text-4xl font-extrabold text-gray-900 dark:text-white font-mono">199</span>
                                    <span class="text-sm font-semibold text-gray-400 dark:text-gray-500">ريال / شهرياً</span>
                                </div>
                                <ul class="mt-6 space-y-4 text-sm text-gray-600 dark:text-gray-400">
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-650 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>1000 رسالة تقييم تفاعلية شهرياً</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-650 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>بدون علامة مائية للـ Widget</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-650 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>دعم فني مخصص وأولوية VIP</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="mt-8">
                                <button 
                                    @click="openUpgradeModal('growth')"
                                    :disabled="subscription?.plan_name === 'growth'"
                                    class="w-full py-3 px-4 rounded-xl text-sm font-bold text-center transition duration-200"
                                    :class="subscription?.plan_name === 'growth'
                                        ? 'bg-gray-50 dark:bg-gray-900/50 text-gray-400 dark:text-gray-500 border border-gray-250 dark:border-gray-700 cursor-default'
                                        : 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-600/20'"
                                >
                                    {{ subscription?.plan_name === 'growth' ? 'باقتك الحالية' : 'ترقية الباقة الآن' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Premium Glassmorphic Payment Modal -->
        <div v-if="showUpgradeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" dir="rtl">
            <div class="bg-white/95 dark:bg-[#1C1C1E]/95 backdrop-blur-2xl border border-white/20 dark:border-white/10 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden transition-all duration-300 transform scale-100">
                <div class="p-6 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">إكمال عملية الترقية (بوابة الدفع التجريبية)</h3>
                    <button @click="closeUpgradeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="handleUpgradeSubmit" class="p-6 space-y-6">
                    <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-400/20 text-indigo-700 dark:text-indigo-400 text-xs flex flex-col gap-1">
                        <span class="font-bold">الباقة المحددة: {{ selectedUpgradePlan.toUpperCase() }}</span>
                        <span>السعر: {{ selectedUpgradePlan === 'startup' ? '99.00' : '199.00' }} ريال سعودي شهرياً</span>
                    </div>

                    <!-- Simulated Card Info -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-450 mb-1.5">اسم صاحب البطاقة</label>
                            <input type="text" v-model="upgradeCardDetails.name" required class="w-full rounded-xl border border-gray-250 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3.5 py-2.5 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-right text-xs outline-none transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-450 mb-1.5">رقم البطاقة المصرفية</label>
                            <input type="text" v-model="upgradeCardDetails.number" required class="w-full rounded-xl border border-gray-250 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3.5 py-2.5 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-left font-mono text-xs outline-none transition" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-450 mb-1.5">تاريخ الانتهاء</label>
                                <input type="text" v-model="upgradeCardDetails.expiry" placeholder="MM/YY" required class="w-full rounded-xl border border-gray-250 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3.5 py-2.5 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-center font-mono text-xs outline-none transition" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-450 mb-1.5">الرمز السري (CVC)</label>
                                <input type="password" v-model="upgradeCardDetails.cvc" placeholder="***" required class="w-full rounded-xl border border-gray-250 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3.5 py-2.5 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-center font-mono text-xs outline-none transition" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button 
                            type="submit" 
                            :disabled="isUpgrading" 
                            class="flex-1 py-3 px-4 rounded-xl text-xs font-bold text-center text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none transition shadow-lg shadow-indigo-600/20 disabled:opacity-50"
                        >
                            <span v-if="isUpgrading">جاري معالجة الدفع...</span>
                            <span v-else>ادفع ورَقِّ الباقة الآن</span>
                        </button>
                        <button 
                            type="button" 
                            @click="closeUpgradeModal" 
                            class="py-3 px-4 rounded-xl text-xs font-bold text-center border border-gray-250 dark:border-gray-750 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                        >
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
