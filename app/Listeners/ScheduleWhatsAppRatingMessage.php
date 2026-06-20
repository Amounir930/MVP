<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Jobs\SendWhatsAppRatingMessageJob;
use Illuminate\Support\Facades\Log;

class ScheduleWhatsAppRatingMessage
{
    /**
     * Handle the event.
     *
     * @param  \App\Events\OrderDelivered  $event
     * @return void
     */
    public function handle(OrderDelivered $event): void
    {
        $order = $event->order;
        $tenant = $order->tenant;

        if (empty($tenant)) {
            Log::warning('OrderDelivered event received with missing tenant context.');
            return;
        }

        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->instance_name) || empty($config->instance_apikey) || $config->status !== 'connected') {
            Log::info('WhatsApp rating message not scheduled: Tenant has no active WhatsApp configuration.', [
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
            ]);
            return;
        }

        // Enforce atomic scheduling to prevent parallel webhook concurrency race conditions
        $lockKey = 'scheduling_whatsapp_rating_message_' . $order->id;
        if (!\Illuminate\Support\Facades\Cache::add($lockKey, true, 600)) {
            Log::info('WhatsApp rating message scheduling skipped: duplicate request locked.', [
                'order_id' => $order->id,
            ]);
            return;
        }

        // Avoid scheduling multiple messages for the same order (e.g. if transitioned to delivered then completed)
        if ($order->rating_message_scheduled) {
            Log::info('WhatsApp rating message skipped: order rating message already scheduled.', [
                'order_id' => $order->id,
            ]);
            return;
        }

        $sessionExists = \App\Models\WhatsappChatSession::where('order_id', $order->id)->exists();
        $reviewExists = \App\Models\Review::where('order_id', $order->id)->exists();
        if ($sessionExists || $reviewExists) {
            Log::info('WhatsApp rating message skipped: order already has a feedback session or review.', [
                'order_id' => $order->id,
            ]);
            return;
        }

        $delayHours = (int) ($config->delay_hours ?? 24);

        $job = new SendWhatsAppRatingMessageJob($order);
        if ($delayHours > 0) {
            $job->delay(now()->addHours($delayHours));
            Log::info('Scheduled WhatsApp rating message with delay.', [
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'delay_hours' => $delayHours,
            ]);
        } else {
            Log::info('Dispatching WhatsApp rating message immediately (no delay configured).', [
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
            ]);
        }

        $order->rating_message_scheduled = true;
        $order->save();

        dispatch($job);
    }
}
