<?php

namespace Database\Seeders;

use App\Models\Payment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Payment::create([
            'user_id' => 2,
            'payment_intent_id' => 'pi_123456789', // Replace with actual Stripe Payment Intent ID
            'payment_method' => 'card', // Example: 'card', 'paypal', etc.
            'amount' => 1000, // Replace with actual amount (in cents if using Stripe)
            'stripe_customer_id' => 'cus_ABC123', // Replace with actual Stripe customer ID
            'subscription_id' => 'sub_ABC123', // Replace with actual subscription ID if applicable
            'status' => 'successful', // Example: 'pending', 'completed', 'failed'
        ]);

    }
}
