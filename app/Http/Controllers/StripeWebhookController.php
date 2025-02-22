<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Laravel\Cashier\Subscription;
use Carbon\Carbon;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle the customer.subscription.updated webhook.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        Log::info('Received customer.subscription.updated webhook', ['payload' => $payload]);

        $stripeSubscription = $payload['data']['object'];

        $user = $this->getUserByStripeId($stripeSubscription['customer']);

        if (!$user) {
            Log::error('User not found for Stripe customer ID.', ['stripe_id' => $stripeSubscription['customer']]);
            return $this->successMethod();
        }

        $subscription = $user->subscriptions()->where('stripe_id', $stripeSubscription['id'])->first();

        if (!$subscription) {
            Log::error('Subscription not found for user.', ['stripe_subscription_id' => $stripeSubscription['id']]);
            return $this->successMethod();
        }

        // Update subscription status
        $subscription->stripe_status = $stripeSubscription['status'];

        // Update trial end date if applicable
        if (isset($stripeSubscription['trial_end'])) {
            $subscription->trial_ends_at = Carbon::createFromTimestamp($stripeSubscription['trial_end']);
        }

        // Update cancellation date if applicable
        if (isset($stripeSubscription['cancel_at'])) {
            $subscription->ends_at = Carbon::createFromTimestamp($stripeSubscription['cancel_at']);
        } elseif (isset($stripeSubscription['canceled_at'])) {
            $subscription->ends_at = Carbon::createFromTimestamp($stripeSubscription['canceled_at']);
        } else {
            $subscription->ends_at = null;
        }

        $subscription->save();

        Log::info('Updated subscription status in database', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => $subscription->stripe_status,
        ]);

        return $this->successMethod();
    }
}
