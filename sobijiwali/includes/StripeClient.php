<?php
/**
 * Lightweight Stripe API Client (Zero-Dependency)
 * Uses native PHP cURL to interact with Stripe REST API.
 */

class StripeClient {
    private $secretKey;
    private $apiUrl = 'https://api.stripe.com/v1';

    public function __construct($secretKey = null) {
        if ($secretKey) {
            $this->secretKey = $secretKey;
        } else {
            // Fetch from database
            $db = Database::getInstance();
            $stmt = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'stripe_secret_key'");
            $res = $stmt->fetch();
            $this->secretKey = $res ? $res['setting_value'] : 'sk_test_placeholder';
        }
    }

    /**
     * Create a PaymentIntent (Auth-Only)
     * @param float $amount Amount in decimal (e.g., 10.50)
     * @param string $currency e.g., 'usd'
     * @param string $paymentMethodId The ID of the payment method from the frontend
     * @param array $metadata Optional metadata
     * @return array Response from Stripe
     */
    public function createAuthPaymentIntent($amount, $currency, $paymentMethodId, $metadata = []) {
        $endpoint = '/payment_intents';
        
        // Stripe expects amounts in cents (integers)
        $amountCents = (int)round($amount * 100);

        $data = [
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'payment_method' => $paymentMethodId,
            'confirmation_method' => 'manual',
            'confirm' => 'true',
            'capture_method' => 'manual', // THIS IS KEY: Authorize but do not capture
        ];

        foreach ($metadata as $key => $value) {
            $data["metadata[$key]"] = $value;
        }

        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Capture a previously authorized PaymentIntent
     */
    public function capturePaymentIntent($paymentIntentId, $amountCents = null) {
        $endpoint = "/payment_intents/{$paymentIntentId}/capture";
        $data = [];
        if ($amountCents) {
            $data['amount_to_capture'] = $amountCents;
        }
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Core Request Handler
     */
    private function request($method, $endpoint, $data = []) {
        $url = $this->apiUrl . $endpoint;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->secretKey . ':'); // Stripe uses Basic Auth with key as username
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            return [
                'success' => false,
                'error' => $decodedResponse['error'] ?? 'Stripe API Error',
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => true,
            'data' => $decodedResponse
        ];
    }
}
