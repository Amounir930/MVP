<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Integration\Drivers\EvolutionAPIDriver;
use App\Models\Review;
use App\Models\WhatsappChatSession;
use App\Models\WhatsappConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handles the GET webhook verification request (kept for general compatibility).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info('WhatsApp webhook verified successfully.');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verification failed.', [
            'mode' => $mode,
            'token' => $token,
        ]);

        return response()->json(['error' => 'Forbidden.'], 403);
    }

    /**
     * Handles the incoming POST messages from Evolution API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('Incoming WhatsApp Webhook Payload:', $payload);
        $event = isset($payload['event']) ? strtolower($payload['event']) : null;
        $instanceName = $payload['instance'] ?? null;

        if (empty($instanceName)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Handle connection state update webhook events to immediately update configuration state in DB
        if ($event === 'connection.update') {
            $config = WhatsappConfig::withoutGlobalScopes()
                ->where('instance_name', $instanceName)
                ->first();

            if ($config) {
                $state = isset($payload['data']['state']) ? strtolower($payload['data']['state']) : null;
                $mappedStatus = ($state === 'open' || $state === 'connected') ? 'connected' : 'disconnected';
                $config->update(['status' => $mappedStatus]);
                Log::info("WhatsApp connection state updated via webhook", [
                    'instance' => $instanceName,
                    'state' => $state,
                    'status' => $mappedStatus
                ]);
            }
            return response()->json(['status' => 'success'], 200);
        }

        if ($event !== 'messages.upsert') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $data = $payload['data'] ?? [];
        $key = $data['key'] ?? [];
        $fromMe = $key['fromMe'] ?? false;

        // Skip messages sent from ourselves
        if ($fromMe) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $remoteJid = $key['remoteJidAlt'] ?? $key['remoteJid'] ?? '';
        // Extract phone number from JID (e.g. 966500000000@s.whatsapp.net)
        $senderPhone = preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
        if (str_starts_with($senderPhone, '00')) {
            $senderPhone = substr($senderPhone, 2);
        }

        if (empty($senderPhone)) {
            return response()->json(['error' => 'Missing sender phone number.'], 200);
        }

        try {
            // Lookup matching WhatsApp configuration to resolve Tenant ID
            $config = WhatsappConfig::withoutGlobalScopes()
                ->where('instance_name', $instanceName)
                ->first();

            if (empty($config)) {
                Log::warning('WhatsApp webhook received for unregistered Evolution Instance', [
                    'instance' => $instanceName,
                ]);
                return response()->json(['status' => 'ignored'], 200);
            }

            // Bind tenant ID to active application context
            App::bind('current_tenant_id', fn () => $config->tenant_id);

            // Retrieve active chat session
            $session = WhatsappChatSession::where('phone', $senderPhone)
                ->where('expires_at', '>', now())
                ->first();

            if (empty($session)) {
                Log::info('No active WhatsApp feedback session found for sender', [
                    'phone' => $senderPhone,
                ]);
                return response()->json(['status' => 'no_session'], 200);
            }

            $driver = new EvolutionAPIDriver();
            $tenant = $config->tenant;
            $message = $data['message'] ?? [];

            // Parse text content
            $incomingText = $message['conversation'] 
                ?? $message['extendedTextMessage']['text'] 
                ?? '';

            // State Machine processing
            switch ($session->step) {
                case 'awaiting_rating':
                    // Extract rating value from list reply, button reply or text input
                    $ratingVal = $message['listResponseMessage']['singleSelectReply']['selectedRowId']
                        ?? $message['buttonsResponseMessage']['selectedButtonId']
                        ?? trim($incomingText);

                    $ratingVal = (int) preg_replace('/[^0-9]/', '', $ratingVal);

                    if ($ratingVal >= 1 && $ratingVal <= 5) {
                        $session->rating = $ratingVal;

                        $customQuestions = $config->custom_questions ?? null;
                        $questions = [];

                        if ($customQuestions === null) {
                            $questions = [
                                [
                                    'id' => 'q_1',
                                    'type' => 'buttons',
                                    'text' => 'هل المنتج مطابق للوصف والصور؟',
                                    'options' => ['نعم مطابق 👍', 'لا يختلف 👎']
                                ],
                                [
                                    'id' => 'q_2',
                                    'type' => 'buttons',
                                    'text' => 'كيف كانت جودة المنتج؟',
                                    'options' => ['ممتازة ⭐', 'متوسطة 😐', 'ضعيفة 👎']
                                ],
                                [
                                    'id' => 'q_3',
                                    'type' => 'buttons',
                                    'text' => 'هل المقاس مناسب؟',
                                    'options' => ['نعم مناسب', 'أصغر', 'أكبر']
                                ],
                                [
                                    'id' => 'q_4',
                                    'type' => 'text',
                                    'text' => 'يسعدنا معرفة رأيك بالتفصيل. يرجى كتابة تعليقك هنا في رسالة واحدة.'
                                ],
                                [
                                    'id' => 'q_5',
                                    'type' => 'media',
                                    'text' => 'أخيراً: هل ترغب في مشاركة صورة أو فيديو للمنتج لتأكيد مصداقية التقييم؟ (يرجى إرسال الصورة أو نقر \'تخطي\')'
                                ]
                            ];
                        } else {
                            $enableQuestions = $customQuestions['enable_questions'] ?? true;
                            if ($enableQuestions) {
                                $questions = $customQuestions['questions'] ?? [];
                            }
                        }

                        if (empty($questions)) {
                            $session->answers = ['responses' => []];
                            $this->finalizeReview($driver, $session, $tenant, $senderPhone);
                        } else {
                            $session->step = 'awaiting_question';
                            $session->answers = [
                                'current_index' => 0,
                                'responses' => [],
                            ];
                            $session->save();

                            $this->sendQuestion($driver, $tenant, $senderPhone, $questions[0], 0, count($questions));
                        }
                    } else {
                        // Resend list or send custom response if invalid input
                        $customQuestions = $config->custom_questions ?? null;
                        $invalidRatingMsg = $customQuestions['invalid_rating_message'] ?? null;

                        if (!empty($invalidRatingMsg)) {
                            // If a custom invalid rating message is defined, send it directly and delete/terminate session immediately
                            $driver->sendTextMessage($tenant, $senderPhone, $invalidRatingMsg);
                            $session->delete();
                        } else {
                            // No custom message: check if input is numeric
                            $isNumericInput = is_numeric(trim($incomingText)) || !empty($message['listResponseMessage']) || !empty($message['buttonsResponseMessage']);

                            if ($isNumericInput) {
                                // Numeric typo: resend options
                                $body = $customQuestions['rating_invalid_warning'] ?? "الرجاء اختيار تقييم صحيح من 1 إلى 5 نجوم باستخدام القائمة:";
                                if (empty($body)) {
                                    $body = "الرجاء اختيار تقييم صحيح من 1 إلى 5 نجوم باستخدام القائمة:";
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
                                // Fallback for titles
                                foreach ($rows as $key => $row) {
                                    if (empty($row['title'])) {
                                        if ($row['id'] === '5') $rows[$key]['title'] = '⭐⭐⭐⭐⭐ ممتاز';
                                        if ($row['id'] === '4') $rows[$key]['title'] = '⭐⭐⭐⭐ جيد جداً';
                                        if ($row['id'] === '3') $rows[$key]['title'] = '⭐⭐⭐ مقبول';
                                        if ($row['id'] === '2') $rows[$key]['title'] = '⭐⭐ سيء';
                                        if ($row['id'] === '1') $rows[$key]['title'] = '⭐ سيء جداً';
                                    }
                                }
                                $driver->sendInteractiveList($tenant, $senderPhone, $body, $buttonLabel, $rows);
                            } else {
                                // Text comment/objection: terminate session
                                $session->delete();
                            }
                        }
                    }
                    break;

                case 'awaiting_question':
                    $customQuestions = $config->custom_questions ?? null;
                    $questions = [];

                    if ($customQuestions === null) {
                        $questions = [
                            [
                                'id' => 'q_1',
                                'type' => 'buttons',
                                'text' => 'هل المنتج مطابق للوصف والصور؟',
                                'options' => ['نعم مطابق 👍', 'لا يختلف 👎']
                            ],
                            [
                                'id' => 'q_2',
                                'type' => 'buttons',
                                'text' => 'كيف كانت جودة المنتج؟',
                                'options' => ['ممتازة ⭐', 'متوسطة 😐', 'ضعيفة 👎']
                            ],
                            [
                                'id' => 'q_3',
                                'type' => 'buttons',
                                'text' => 'هل المقاس مناسب؟',
                                'options' => ['نعم مناسب', 'أصغر', 'أكبر']
                            ],
                            [
                                'id' => 'q_4',
                                'type' => 'text',
                                'text' => 'يسعدنا معرفة رأيك بالتفصيل. يرجى كتابة تعليقك هنا في رسالة واحدة.'
                            ],
                            [
                                'id' => 'q_5',
                                'type' => 'media',
                                'text' => 'أخيراً: هل ترغب في مشاركة صورة أو فيديو للمنتج لتأكيد مصداقية التقييم؟ (يرجى إرسال الصورة أو نقر \'تخطي\')'
                            ]
                        ];
                    } else {
                        $enableQuestions = $customQuestions['enable_questions'] ?? true;
                        if ($enableQuestions) {
                            $questions = $customQuestions['questions'] ?? [];
                        }
                    }

                    $answers = $session->answers ?? [];
                    $currentIndex = $answers['current_index'] ?? 0;

                    if (!isset($questions[$currentIndex])) {
                        $this->finalizeReview($driver, $session, $tenant, $senderPhone);
                        break;
                    }

                    $currentQuestion = $questions[$currentIndex];
                    $responseVal = null;
                    $mediaType = null;

                    if ($currentQuestion['type'] === 'buttons') {
                        $buttonId = $message['buttonsResponseMessage']['selectedButtonId'] 
                            ?? trim($incomingText);

                        $options = $currentQuestion['options'] ?? [];

                        if (str_starts_with($buttonId, 'opt_')) {
                            $optIdx = (int) str_replace('opt_', '', $buttonId) - 1;
                            $responseVal = $options[$optIdx] ?? $buttonId;
                        } elseif (is_numeric($buttonId)) {
                            $optIdx = (int)$buttonId - 1;
                            $responseVal = $options[$optIdx] ?? $buttonId;
                        } else {
                            $found = false;
                            foreach ($options as $opt) {
                                if (mb_strtolower(trim($opt)) === mb_strtolower(trim($buttonId))) {
                                    $responseVal = $opt;
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                if ($buttonId === 'yes' || $buttonId === 'q1_opt_1' || $buttonId === 'q3_opt_1') {
                                    $responseVal = $options[0] ?? $buttonId;
                                } elseif ($buttonId === 'no' || $buttonId === 'q1_opt_2') {
                                    $responseVal = $options[1] ?? $buttonId;
                                } elseif ($buttonId === 'excellent' || $buttonId === 'q2_opt_1') {
                                    $responseVal = $options[0] ?? $buttonId;
                                } elseif ($buttonId === 'average' || $buttonId === 'q2_opt_2') {
                                    $responseVal = $options[1] ?? $buttonId;
                                } elseif ($buttonId === 'poor' || $buttonId === 'q2_opt_3') {
                                    $responseVal = $options[2] ?? $buttonId;
                                } elseif ($buttonId === 'smaller' || $buttonId === 'q3_opt_2') {
                                    $responseVal = $options[1] ?? $buttonId;
                                } elseif ($buttonId === 'larger' || $buttonId === 'q3_opt_3') {
                                    $responseVal = $options[2] ?? $buttonId;
                                } else {
                                    $responseVal = $buttonId;
                                }
                            }
                        }
                    } elseif ($currentQuestion['type'] === 'media') {
                        $buttonId = $message['buttonsResponseMessage']['selectedButtonId'] ?? trim($incomingText);
                        $mediaId = $key['id'] ?? null;

                        if ($buttonId === 'skip' || $buttonId === '1' || mb_strtolower($buttonId) === 'skip' || str_contains($buttonId, 'تخطي')) {
                            $responseVal = 'skip';
                        } elseif (!empty($message['imageMessage'])) {
                            $mediaType = 'image';
                        } elseif (!empty($message['videoMessage'])) {
                            $mediaType = 'video';
                        }

                        if ($buttonId !== 'skip' && $responseVal !== 'skip' && $mediaType && $mediaId) {
                            $responseVal = $driver->getMediaUrl($tenant, $mediaId);
                        }
                    } else {
                        $responseVal = trim($incomingText);
                    }

                    $responses = $answers['responses'] ?? [];
                    $responses[] = [
                        'type' => $currentQuestion['type'],
                        'text' => $currentQuestion['text'],
                        'response' => $responseVal,
                        'media_type' => $mediaType,
                    ];
                    $answers['responses'] = $responses;
                    $session->answers = $answers;

                    $nextIndex = $currentIndex + 1;
                    if ($nextIndex < count($questions)) {
                        $answers['current_index'] = $nextIndex;
                        $session->answers = $answers;
                        $session->save();

                        $this->sendQuestion($driver, $tenant, $senderPhone, $questions[$nextIndex], $nextIndex, count($questions));
                    } else {
                        $this->finalizeReview($driver, $session, $tenant, $senderPhone);
                    }
                    break;
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $exception) {
            Log::error('Exception triggered during WhatsApp webhook handling', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Internal server error.'], 500);
        }
    }

    /**
     * Send a questionnaire question to the user on WhatsApp.
     */
    protected function sendQuestion($driver, $tenant, $phone, $question, $index, $total)
    {
        $body = $question['text'];

        if ($question['type'] === 'buttons') {
            $buttons = [];
            $options = $question['options'] ?? [];
            foreach ($options as $idx => $opt) {
                if (trim($opt) !== '') {
                    $buttons[] = [
                        'id' => 'opt_' . ($idx + 1),
                        'title' => trim($opt)
                    ];
                }
            }
            if (empty($buttons)) {
                $buttons = [
                    ['id' => 'opt_1', 'title' => 'نعم 👍'],
                    ['id' => 'opt_2', 'title' => 'لا 👎']
                ];
            }
            $driver->sendInteractiveButtons($tenant, $phone, $body, $buttons);
        } elseif ($question['type'] === 'media') {
            $driver->sendTextMessage($tenant, $phone, $body);
        } else {
            // text type
            $driver->sendTextMessage($tenant, $phone, $body);
        }
    }

    /**
     * Finalize the review submission, clean up chat session and send a thank you.
     */
    protected function finalizeReview($driver, $session, $tenant, $senderPhone)
    {
        $responses = $session->answers['responses'] ?? [];
        $textComment = null;
        $mediaUrl = null;
        $mediaType = null;

        foreach ($responses as $resp) {
            if ($resp['type'] === 'text' && empty($textComment)) {
                $textComment = $resp['response'];
            } elseif ($resp['type'] === 'media' && $resp['response'] !== 'skip') {
                $mediaUrl = $resp['response'];
                $mediaType = $resp['media_type'];
            }
        }

        // Save finalized review record
        Review::create([
            'tenant_id' => $session->tenant_id,
            'order_id' => $session->order_id,
            'customer_id' => $session->order->customer_id,
            'rating' => $session->rating,
            'answers' => $responses,
            'comment' => $textComment,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'status' => 'pending',
            'source' => 'whatsapp',
        ]);

        // Terminate and delete the chat session
        $session->delete();

        // Send thank you response
        $config = WhatsappConfig::withoutGlobalScopes()
            ->where('tenant_id', $session->tenant_id)
            ->first();
        $customQuestions = $config->custom_questions ?? null;
        $thankYou = $customQuestions['success_message'] ?? null;
        if (empty($thankYou)) {
            $thankYou = "شكراً لتقييمك! تم حفظ تقييمك بنجاح وسيتم عرضه قريباً في المتجر.";
        } else {
            $customerName = ($session->order && $session->order->customer) ? $session->order->customer->name : 'عميلنا العزيز';
            $orderNumber = $session->order ? $session->order->invoice_number : '';
            $thankYou = str_replace(
                ['{name}', '{order_number}'],
                [$customerName, $orderNumber],
                $thankYou
            );
        }
        $driver->sendTextMessage($tenant, $senderPhone, $thankYou);
    }
}
