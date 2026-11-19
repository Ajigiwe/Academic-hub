<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Entitlement;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\MoolreProvider;
use App\Services\Payments\MoolreRequestException;
use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Moolre payment gateway tests: initialization, server-to-server status check,
 * webhook signature verification (HMAC-SHA256 with t=...,v1=...), deduplication,
 * and fulfillment.
 */
class MoolreTest extends TestCase
{
    use RefreshDatabase;

    private object $moolre;

    private string $privateKey = 'test_moolre_private_key_12345';

    private string $publicKey = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyaWQiOjExMTUxNywid2FsbGV0aWQiOiI3ODU5NiIsImV4cCI6MTk1NjUyNzk5OX0.XoebPda86h4yplp426-G_xDThIDT74d8ruNGDCJHzlM';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);

        config([
            'payments.moolre.private_key' => $this->privateKey,
            'payments.moolre.public_key' => $this->publicKey,
            'payments.moolre.username' => 'testuser',
            'payments.moolre.account_number' => '78596',
        ]);

        $this->moolre = new class extends MoolreProvider
        {
            /** @var array<string, array<string, mixed>> keyed by path */
            public array $responses = [];

            /** @var array<string, array{0: int, 1: string}> HTTP failures keyed by path */
            public array $errorResponses = [];

            /** @var list<array{method: string, path: string, payload: array}> */
            public array $calls = [];

            protected function request(string $method, string $path, array $payload = []): array
            {
                $this->calls[] = ['method' => $method, 'path' => $path, 'payload' => $payload];

                if (array_key_exists($path, $this->errorResponses)) {
                    [$status, $message] = $this->errorResponses[$path];
                    throw new MoolreRequestException($status, $path, "Moolre {$path} failed: {$message}");
                }

                if (array_key_exists($path, $this->responses)) {
                    return $this->responses[$path];
                }

                throw new \RuntimeException("No stubbed Moolre response for {$method} {$path}");
            }
        };

        $this->app->instance(MoolreProvider::class, $this->moolre);
    }

    public function test_name_and_configuration_status(): void
    {
        $provider = app(PaymentManager::class)->provider('moolre');

        $this->assertSame('moolre', $provider->name());
        $this->assertTrue($provider->isConfigured());
        $this->assertSame('live', $provider->mode());
        $this->assertSame('78596', $provider->accountNumber());
    }

    public function test_account_number_extracts_wallet_id_from_jwt_if_not_explicit(): void
    {
        config(['payments.moolre.account_number' => '']);
        $provider = app(PaymentManager::class)->provider('moolre');

        $this->assertSame('78596', $provider->accountNumber());
    }

    public function test_initiate_payment_posts_expected_payload(): void
    {
        $bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = User::where('email', 'student@pastq.test')->first();

        $this->moolre->responses['/embed/link'] = [
            'status' => 1,
            'code' => 'POS09',
            'message' => 'POS payment link successfully generated.',
            'data' => [
                'authorization_url' => 'https://pos.moolre.com/test-pos-link-123',
                'reference' => 'uuid-moolre-ref-1',
            ],
        ];

        $order = $this->pendingOrder('PQ-MOOLRE01', $student, $bundle);
        config(['app.url' => 'https://amgacademichub.com']);

        $provider = app(PaymentManager::class)->provider('moolre');
        $result = $provider->initiatePayment(
            $order->reference,
            2500, // 25.00 GHS in pesewas
            'GHS',
            $student->email,
            'https://amgacademichub.com/payments/verify/PQ-MOOLRE01',
            'https://amgacademichub.com',
        );

        $this->assertSame('https://pos.moolre.com/test-pos-link-123', $result['redirect_url']);
        $this->assertSame('uuid-moolre-ref-1', $result['provider_ref']);
        $this->assertSame('MOBILE_MONEY', $result['channel']);

        $call = $this->moolre->calls[0];
        $this->assertSame('POST', $call['method']);
        $this->assertSame('/embed/link', $call['path']);
        $this->assertSame('25.00', $call['payload']['amount']);
        $this->assertSame('GHS', $call['payload']['currency']);
        $this->assertSame('78596', $call['payload']['accountnumber']);
        $this->assertSame('PQ-MOOLRE01', $call['payload']['externalref']);
        $this->assertSame('https://amgacademichub.com/payments/webhook', $call['payload']['callback']);
        $this->assertSame('https://amgacademichub.com/payments/verify/PQ-MOOLRE01', $call['payload']['redirect']);
    }

    public function test_initiate_payment_throws_when_moolre_rejects(): void
    {
        $bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = User::where('email', 'student@pastq.test')->first();

        $this->moolre->responses['/embed/link'] = [
            'status' => 0,
            'code' => 'INP02',
            'message' => 'Transaction already exists!',
        ];

        $order = $this->pendingOrder('PQ-MOOLRE02', $student, $bundle);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transaction already exists!');

        app(PaymentManager::class)->provider('moolre')->initiatePayment(
            $order->reference,
            1000,
            'GHS',
            $student->email,
            'https://amgacademichub.com/verify',
            'https://amgacademichub.com',
        );
    }

    public function test_verify_payment_successful(): void
    {
        $this->moolre->responses['/open/transact/status'] = [
            'status' => 1,
            'code' => 'SS01',
            'message' => 'Transaction Successful',
            'data' => [
                'txstatus' => 1,
                'txtype' => 2,
                'accountnumber' => '78596',
                'amount' => '30.00',
                'externalref' => 'PQ-SUCCESS01',
                'transactionid' => '99887766',
                'currency' => 'GHS',
            ],
        ];

        $provider = app(PaymentManager::class)->provider('moolre');
        $res = $provider->verifyPayment('PQ-SUCCESS01');

        $this->assertTrue($res['successful']);
        $this->assertFalse($res['pending']);
        $this->assertSame(3000, $res['amount_pesewas']);
        $this->assertSame('GHS', $res['currency']);
        $this->assertSame('PQ-SUCCESS01', $res['provider_ref']);
        $this->assertSame('99887766', $res['provider_txn_id']);
        $this->assertSame('MOBILE_MONEY', $res['channel']);
    }

    public function test_verify_payment_pending(): void
    {
        $this->moolre->responses['/open/transact/status'] = [
            'status' => 1,
            'code' => 'PEN01',
            'message' => 'Transaction Pending',
            'data' => [
                'txstatus' => 0,
                'externalref' => 'PQ-PENDING01',
            ],
        ];

        $provider = app(PaymentManager::class)->provider('moolre');
        $res = $provider->verifyPayment('PQ-PENDING01');

        $this->assertFalse($res['successful']);
        $this->assertTrue($res['pending']);
        $this->assertStringContainsString('confirming', $res['failure_reason']);
    }

    public function test_verify_payment_handles_not_found(): void
    {
        $this->moolre->errorResponses['/open/transact/status'] = [404, 'Reference not found'];

        $provider = app(PaymentManager::class)->provider('moolre');
        $res = $provider->verifyPayment('PQ-NOTFOUND');

        $this->assertFalse($res['successful']);
        $this->assertFalse($res['pending']);
        $this->assertStringContainsString('no record', $res['failure_reason']);
    }

    public function test_signature_verification_validates_moolre_header(): void
    {
        $provider = app(PaymentManager::class)->provider('moolre');
        $raw = '{"status":1,"code":"P01","data":{"externalref":"PQ-SIG1"}}';

        $now = time();
        $hash = hash_hmac('sha256', $raw, $this->privateKey);
        $header = "t={$now},v1={$hash}";

        $this->assertTrue($provider->verifySignature($raw, $header));
    }

    public function test_signature_verification_rejects_expired_timestamp(): void
    {
        $provider = app(PaymentManager::class)->provider('moolre');
        $raw = '{"status":1,"code":"P01","data":{"externalref":"PQ-SIG2"}}';

        $old = time() - 400; // Older than 5 minutes (300s)
        $hash = hash_hmac('sha256', $raw, $this->privateKey);
        $header = "t={$old},v1={$hash}";

        $this->assertFalse($provider->verifySignature($raw, $header));
    }

    public function test_signature_verification_rejects_tampered_body(): void
    {
        $provider = app(PaymentManager::class)->provider('moolre');
        $raw = '{"status":1,"code":"P01","data":{"amount":"10.00"}}';
        $tampered = '{"status":1,"code":"P01","data":{"amount":"100.00"}}';

        $now = time();
        $hash = hash_hmac('sha256', $raw, $this->privateKey);
        $header = "t={$now},v1={$hash}";

        $this->assertFalse($provider->verifySignature($tampered, $header));
    }

    public function test_moolre_webhook_delivers_and_fulfills_order(): void
    {
        $bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = User::where('email', 'student@pastq.test')->first();
        $order = $this->pendingOrder('PQ-WEBHOOK1', $student, $bundle, 2500);

        $this->moolre->responses['/open/transact/status'] = [
            'status' => 1,
            'code' => 'SS01',
            'data' => [
                'txstatus' => 1,
                'amount' => '25.00',
                'externalref' => 'PQ-WEBHOOK1',
                'currency' => 'GHS',
                'transactionid' => 'tx_12345',
            ],
        ];

        $payload = [
            'status' => 1,
            'code' => 'P01',
            'message' => 'Transaction Successful',
            'data' => [
                'txstatus' => 1,
                'externalref' => 'PQ-WEBHOOK1',
                'amount' => '25.00',
                'currency' => 'GHS',
                'transactionid' => 'tx_12345',
            ],
        ];

        $raw = json_encode($payload);
        $now = time();
        $sig = "t={$now},v1=".hash_hmac('sha256', $raw, $this->privateKey);

        $response = $this->call(
            'POST',
            '/payments/webhook',
            [],
            [],
            [],
            ['HTTP_X_MOOLRE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
            $raw,
        );

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        // Order is fulfilled
        $order->refresh();
        $this->assertSame('PAID', $order->status);

        // Payment recorded
        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('SUCCESSFUL', $payment->status);
        $this->assertSame('tx_12345', $payment->provider_txn_id);

        // Entitlements granted
        $this->assertTrue(
            Entitlement::where('user_id', $student->id)
                ->whereIn('resource_id', $bundle->resources->pluck('id'))
                ->exists(),
        );

        // Deduplication event recorded
        $this->assertTrue(WebhookEvent::where('reference', 'PQ-WEBHOOK1')->exists());
    }

    private function pendingOrder(
        string $reference,
        User $student,
        Bundle $bundle,
        int $amountPesewas = 1500,
    ): Order {
        $order = Order::create([
            'reference' => $reference,
            'user_id' => $student->id,
            'status' => 'PENDING',
            'amount_pesewas' => $amountPesewas,
            'currency' => 'GHS',
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'bundle_id' => $bundle->id,
            'unit_price_pesewas' => $amountPesewas,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'provider' => 'moolre',
            'reference' => $reference,
            'provider_ref' => $reference,
            'channel' => 'MOBILE_MONEY',
            'amount_pesewas' => $amountPesewas,
            'currency' => 'GHS',
            'status' => 'PENDING',
        ]);

        return $order;
    }
}
