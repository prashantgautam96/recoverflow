<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'billing_plan')) {
                $table->string('billing_plan')->default('starter')->after('password');
            }

            if (! Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->unique()->after('billing_plan');
            }

            if (! Schema::hasColumn('users', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            }

            if (! Schema::hasColumn('users', 'subscription_status')) {
                $table->string('subscription_status')->default('inactive')->after('stripe_subscription_id');
            }

            if (! Schema::hasColumn('users', 'subscription_ends_at')) {
                $table->timestamp('subscription_ends_at')->nullable()->after('subscription_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'subscription_ends_at')) {
                $table->dropColumn('subscription_ends_at');
            }

            if (Schema::hasColumn('users', 'subscription_status')) {
                $table->dropColumn('subscription_status');
            }

            if (Schema::hasColumn('users', 'stripe_subscription_id')) {
                $table->dropColumn('stripe_subscription_id');
            }

            if (Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->dropUnique('users_stripe_customer_id_unique');
                $table->dropColumn('stripe_customer_id');
            }

            if (Schema::hasColumn('users', 'billing_plan')) {
                $table->dropColumn('billing_plan');
            }
        });
    }
};
