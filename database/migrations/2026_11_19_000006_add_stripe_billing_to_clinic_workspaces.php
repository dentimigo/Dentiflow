<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_workspaces', function (Blueprint $table) {
            $table->string('stripe_checkout_id')->nullable()->unique();
            $table->unsignedInteger('stripe_checkout_attempt')->default(0);
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_status', 20)->nullable();
            $table->dateTime('stripe_paid_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_workspaces', function (Blueprint $table) {
            $table->dropUnique(['stripe_checkout_id']);
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn([
                'stripe_checkout_id', 'stripe_checkout_attempt', 'stripe_customer_id',
                'stripe_subscription_id', 'stripe_status', 'stripe_paid_until',
            ]);
        });
    }
};
