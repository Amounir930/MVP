<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->string('plan_name'); // free, startup, growth
            $table->decimal('price', 10, 2);
            $table->string('status')->default('active'); // active, expired, suspended
            $table->timestamp('current_period_start')->useCurrent();
            $table->timestamp('current_period_end');
            $table->integer('monthly_limit'); // 50, 400, 1000
            $table->integer('current_period_usage')->default(0);
            $table->string('gateway_token')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
