<?php

namespace App\Jobs;

use App\Integration\Drivers\EvolutionAPIDriver;
use App\Models\Order;
use App\Models\WhatsappChatSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class SendWhatsAppRatingMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Order $order;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Order  $order
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Enforce the tenant scope to isolate database operations
        App::bind('current_tenant_id', fn () => $this->order->tenant_id);

        // Avoid duplicate sending
        $sessionExists = WhatsappChatSession::where('order_id', $this->order->id)->exists();
        $reviewExists = \App\Models\Review::where('order_id', $this->order->id)->exists();
        if ($sessionExists || $reviewExists) {
            Log::info('WhatsApp rating message job skipped: order already has a feedback session or review.', [
                'order_id' => $this->order->id,
            ]);
            return;
        }

        $order = $this->order->load(['customer', 'tenant']);
        $customer = $order->customer;

        if (empty($customer) || empty($customer->phone)) {
            Log::warning('Cannot send WhatsApp rating message: Customer phone number is missing.', [
                'order_id' => $order->id,
            ]);
            return;
        }

        $phone = preg_replace('/[^0-9]/', '', $customer->phone);
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }
        $tenant = $order->tenant;

        $whatsappConfig = \App\Models\WhatsappConfig::where('tenant_id', $tenant->id)->first();
        $customQuestions = $whatsappConfig ? $whatsappConfig->custom_questions : null;

        $bodyText = "مرحباً {$customer->name}، شكراً لتعاملك مع متجرنا! يسعدنا جداً تقييمك لطلبك رقم {$order->invoice_number}. كيف تقيم تجربتك معنا؟";
        if (!empty($customQuestions['rating_message'])) {
            $bodyText = str_replace(
                ['{name}', '{order_number}'],
                [$customer->name, $order->invoice_number],
                $customQuestions['rating_message']
            );
        }

        $buttonLabel = $customQuestions['rating_button_label'] ?? "اختر التقييم بالنجوم";
        if (empty($buttonLabel)) {
            $buttonLabel = "اختر التقييم بالنجوم";
        }

        $rows = [
            ['id' => '5', 'title' => $customQuestions['rating_label_5'] ?? '⭐⭐⭐⭐⭐ ممتاز'],
            ['id' => '4', 'title' => $customQuestions['rating_label_4'] ?? '⭐⭐⭐⭐ جيد جداً'],
            ['id' => '3', 'title' => $customQuestions['rating_label_3'] ?? '⭐⭐⭐ مقبول'],
            ['id' => '2', 'title' => $customQuestions['rating_label_2'] ?? '⭐⭐ سيء'],
            ['id' => '1', 'title' => $customQuestions['rating_label_1'] ?? '⭐ سيء جداً'],
        ];

        // Filter out empty rows just in case, but keep default IDs 1-5
        foreach ($rows as $key => $row) {
            if (empty($row['title'])) {
                if ($row['id'] === '5') $rows[$key]['title'] = '⭐⭐⭐⭐⭐ ممتاز';
                if ($row['id'] === '4') $rows[$key]['title'] = '⭐⭐⭐⭐ جيد جداً';
                if ($row['id'] === '3') $rows[$key]['title'] = '⭐⭐⭐ مقبول';
                if ($row['id'] === '2') $rows[$key]['title'] = '⭐⭐ سيء';
                if ($row['id'] === '1') $rows[$key]['title'] = '⭐ سيء جداً';
            }
        }

        // 1. Create/update the session first to act as a lock for concurrent requests
        $session = WhatsappChatSession::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'phone' => $phone,
            ],
            [
                'order_id' => $order->id,
                'step' => 'awaiting_rating',
                'rating' => null,
                'answers' => null,
                'text_comment' => null,
                'media_url' => null,
                'expires_at' => now()->addHours(24),
            ]
        );

        Log::info('Sending WhatsApp interactive rating list message.', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'phone' => $phone,
        ]);

        $driver = new EvolutionAPIDriver();
        $success = $driver->sendInteractiveList($tenant, $phone, $bodyText, $buttonLabel, $rows);

        if ($success) {
            \App\Models\WhatsappMessageLog::create([
                'tenant_id' => $tenant->id,
                'phone' => $phone,
                'order_id' => $order->id,
                'status' => 'sent',
            ]);

            Log::info('WhatsApp chat session created/updated successfully.', [
                'tenant_id' => $tenant->id,
                'phone' => $phone,
                'order_id' => $order->id,
            ]);
        } else {
            \App\Models\WhatsappMessageLog::create([
                'tenant_id' => $tenant->id,
                'phone' => $phone,
                'order_id' => $order->id,
                'status' => 'failed',
            ]);

            // Delete the session if sending failed
            $session->delete();
            Log::error('Failed to send WhatsApp interactive list rating message.', [
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
            ]);
        }
    }
}
