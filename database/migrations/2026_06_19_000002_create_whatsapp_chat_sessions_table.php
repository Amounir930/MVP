<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Establishes the database schema for active customer WhatsApp feedback chat sessions.
     */
    public function up(): void
    {
        Schema::create('whatsapp_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('phone');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('step');
            $table->integer('rating')->nullable();
            $table->json('answers')->nullable();
            $table->text('text_comment')->nullable();
            $table->text('media_url')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_sessions');
    }
};
