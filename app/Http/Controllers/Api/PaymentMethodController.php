<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        $enabled = PaymentGatewayFactory::getEnabled();
        $methods = [];

        foreach ($enabled as $name) {
            $methods[] = [
                'value' => $name,
                'label' => match ($name) {
                    'cod' => 'Cash on Delivery',
                    'sslcommerz' => 'Card / Mobile Banking',
                    'bkash' => 'bKash',
                    'nagad' => 'Nagad',
                    'rocket' => 'Rocket',
                    'moyasar' => 'Mada / Card (Moyasar)',
                    'tabby' => 'Tabby — Pay in 4',
                    'stripe' => 'Card (Stripe)',
                    default => $name,
                },
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'methods' => $methods,
            ],
        ]);
    }
}
