<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Log;

/**
 * Provider resolution. Two entry points matter:
 *
 *   provider()  — the EFFECTIVE driver for a new checkout.
 *   byName()    — the provider that delivered a webhook (never guessed).
 *
 * The effective driver is the configured one when it is ready to move
 * money. If it is selected but not configured (Paystack without a secret
 * key), we fall back to the sandbox ONLY outside a live environment:
 * a production checkout must fail loudly rather than hand over content
 * for free, and development must keep working while a key is missing.
 */
class PaymentManager
{
    public function __construct(
        private readonly MockProvider $mock,
        private readonly PaystackProvider $paystack,
    ) {}

    public function provider(?string $configured = null): PaymentProvider
    {
        // An explicitly named provider is honoured as-is (tests, webhook
        // dispatch) — the caller has already decided who it wants.
        if ($configured !== null && $configured !== '') {
            return $this->resolve($configured);
        }

        $selected = $this->selected();

        if ($selected->isConfigured()) {
            return $selected;
        }

        // Selected but not ready. Outside production the sandbox keeps
        // the shop usable; in production this throws — checkout must fail
        // rather than silently accept orders it cannot charge for.
        if (! app()->isProduction() && $this->mock->isConfigured()) {
            Log::warning('Payment provider is not configured; using the sandbox gateway.', [
                'selected' => $selected->name(),
                'sandbox_allowed' => true,
            ]);

            return $this->mock;
        }

        throw new \RuntimeException($this->missingReason($selected));
    }

    /** The provider named by configuration, ready or not. */
    public function selected(): PaymentProvider
    {
        return $this->resolve((string) (config('payments.provider') ?: 'mock'));
    }

    /** Is the mock gateway reachable as a page/route right now? */
    public function sandboxAvailable(): bool
    {
        if (! $this->mock->isConfigured()) {
            return false;
        }

        // Live environment: the sandbox must be an explicit choice.
        return ! app()->isProduction() || $this->selected()->name() === 'mock';
    }

    /**
     * Admin-facing status for the Payments card. Secrets never leave the
     * server: the key is reported as a mode, never as a value.
     *
     * @return array{
     *     selected: string, selected_mode: string, selected_ready: bool,
     *     effective: string, effective_mode: string, falling_back: bool,
     *     sandbox_available: bool, sandbox_secret_is_dev_default: bool,
     *     webhook_url: string, callback_url: string,
     *     webhook_reachable: bool, note: ?string
     * }
     */
    public function status(): array
    {
        $selected = $this->selected();
        $selectedReady = $selected->isConfigured();

        $base = rtrim((string) config('app.url'), '/');
        $host = (string) parse_url($base, PHP_URL_HOST);
        $reachable = $host !== ''
            && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && ! str_ends_with($host, '.local');

        $effective = $selected;
        $fallingBack = false;
        $note = null;

        if (! $selectedReady) {
            if (! app()->isProduction() && $this->mock->isConfigured()) {
                $effective = $this->mock;
                $fallingBack = true;
                $note = sprintf(
                    '%s is selected but not configured, so the sandbox is handling checkout. Set its key to go live.',
                    ucfirst($selected->name()),
                );
            } else {
                $note = $this->missingReason($selected);
            }
        }

        return [
            'selected' => $selected->name(),
            'selected_mode' => $selected->mode(),
            'selected_ready' => $selectedReady,
            'effective' => $effective->name(),
            'effective_mode' => $effective->mode(),
            'falling_back' => $fallingBack,
            'sandbox_available' => $this->sandboxAvailable(),
            'sandbox_secret_is_dev_default' => trim((string) config('payments.webhook_secret')) === MockProvider::DEV_SECRET,
            // What the Paystack dashboard must be told. Both come from
            // APP_URL because that is what checkout redirects with — not
            // the admin's current host.
            'webhook_url' => $base.'/payments/webhook',
            'callback_url' => $base.'/payments/verify',
            'webhook_reachable' => $reachable,
            'note' => $note,
        ];
    }

    /**
     * Resolve a provider by name — used by the webhook endpoint, which
     * learns the delivering provider from the signature header rather
     * than from configuration (so the mock sandbox keeps working even
     * when Paystack is the configured driver, and vice versa).
     */
    public function byName(string $name): ?PaymentProvider
    {
        return match ($name) {
            'mock' => $this->mock,
            'paystack' => $this->paystack,
            default => null,
        };
    }

    /** Find the provider whose signature header matches the delivery. */
    public function bySignatureHeader(string $header): ?PaymentProvider
    {
        foreach ($this->all() as $provider) {
            if (strcasecmp($provider->signatureHeader(), $header) === 0) {
                return $provider;
            }
        }

        return null;
    }

    /** Every registered provider (webhook scheme dispatch iterates these). */
    public function all(): array
    {
        return [$this->paystack, $this->mock];
    }

    private function resolve(string $name): PaymentProvider
    {
        return match ($name) {
            '', 'mock' => $this->mock,
            'paystack' => $this->paystack,
            default => throw new \RuntimeException(
                "Unknown PAYMENT_PROVIDER \"{$name}\". Expected \"mock\" or \"paystack\"."
            ),
        };
    }

    /** Why a provider cannot take money right now (admin/checkout copy). */
    private function missingReason(PaymentProvider $provider): string
    {
        if ($provider->name() === 'paystack') {
            return 'Paystack is not configured: set PAYSTACK_SECRET_KEY in the environment.';
        }

        if ($provider->mode() === 'sandbox' && app()->isProduction()) {
            return 'The sandbox gateway needs a real PAYMENT_WEBHOOK_SECRET before it can run in production.';
        }

        return 'No payment provider is configured: set PAYMENT_PROVIDER and its credentials.';
    }
}
