<?php

namespace App\Services;

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Runtime inspection and test-delivery helper for the active mailer.
 *
 * Used by both Admin -> Settings (for cPanel hosts without terminal/SSH)
 * and the `php artisan mail:test` CLI command. Secrets such as MAIL_PASSWORD
 * are never exposed — only presence/absence and masked usernames are shown.
 */
class MailStatus
{
    /**
     * Read-only diagnostic snapshot of Laravel's mail configuration.
     *
     * @return array{
     *     mailer: string,
     *     is_local_only: bool,
     *     host: string,
     *     port: string,
     *     scheme: ?string,
     *     scheme_label: string,
     *     username_masked: ?string,
     *     password_set: bool,
     *     from_address: string,
     *     from_name: string,
     *     config_cached: bool,
     *     smtp_ready: bool,
     *     note: ?string
     * }
     */
    public function status(): array
    {
        $mailer = (string) (config('mail.default') ?: 'log');
        $isLocalOnly = in_array($mailer, ['log', 'array'], true);

        $host = trim((string) config('mail.mailers.smtp.host', ''));
        $port = trim((string) config('mail.mailers.smtp.port', ''));
        $scheme = config('mail.mailers.smtp.scheme');
        $schemeStr = is_string($scheme) && $scheme !== '' ? $scheme : null;

        $schemeLabel = match (true) {
            $schemeStr === 'smtps' => 'smtps (SSL)',
            $schemeStr === 'smtp' => 'smtp (STARTTLS)',
            (int) $port === 465 => 'smtps (SSL · port 465)',
            default => 'auto (STARTTLS)',
        };

        $username = trim((string) config('mail.mailers.smtp.username', ''));
        $password = (string) config('mail.mailers.smtp.password', '');
        $passwordSet = $password !== '';

        $fromAddress = trim((string) config('mail.from.address', ''));
        $fromName = trim((string) config('mail.from.name', config('app.name', 'Academic Hub')));
        $configCached = app()->configurationIsCached();

        $smtpReady = $mailer === 'smtp'
            && $host !== ''
            && $port !== ''
            && $username !== ''
            && $passwordSet;

        $note = null;
        if ($isLocalOnly) {
            $note = sprintf(
                'Mailer is set to "%s", so emails (including password resets) are written to storage/logs/laravel.log instead of being delivered to inboxes. Update MAIL_* values in .env via cPanel File Manager, then click "Reload .env config" below.',
                $mailer,
            );
        } elseif ($mailer === 'smtp' && ! $smtpReady) {
            $missing = [];
            if ($host === '') {
                $missing[] = 'MAIL_HOST';
            }
            if ($port === '') {
                $missing[] = 'MAIL_PORT';
            }
            if ($username === '') {
                $missing[] = 'MAIL_USERNAME';
            }
            if (! $passwordSet) {
                $missing[] = 'MAIL_PASSWORD';
            }

            $note = 'SMTP is selected, but '.implode(', ', $missing).' missing from the active environment.';
        }

        return [
            'mailer' => $mailer,
            'is_local_only' => $isLocalOnly,
            'host' => $host,
            'port' => $port,
            'scheme' => $schemeStr,
            'scheme_label' => $schemeLabel,
            'username_masked' => $this->maskUsername($username),
            'password_set' => $passwordSet,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'config_cached' => $configCached,
            'smtp_ready' => $smtpReady,
            'note' => $note,
        ];
    }

    /**
     * Dispatch a branded test email and return a structured outcome with
     * cPanel-specific troubleshooting tips on failure.
     *
     * @return array{
     *     ok: bool,
     *     mailer: string,
     *     is_local_only: bool,
     *     recipient: string,
     *     error?: string,
     *     tips?: list<string>
     * }
     */
    public function sendTest(string $recipient, ?string $triggeredBy = null): array
    {
        $snapshot = $this->status();

        try {
            Mail::to($recipient)->send(new TestEmail($snapshot, $triggeredBy));

            return [
                'ok' => true,
                'mailer' => $snapshot['mailer'],
                'is_local_only' => $snapshot['is_local_only'],
                'recipient' => $recipient,
            ];
        } catch (Throwable $e) {
            Log::warning('Admin test email failed.', [
                'recipient' => $recipient,
                'mailer' => $snapshot['mailer'],
                'host' => $snapshot['host'],
                'port' => $snapshot['port'],
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'mailer' => $snapshot['mailer'],
                'is_local_only' => $snapshot['is_local_only'],
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'tips' => $this->troubleshootingTips($e->getMessage(), $snapshot),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    public function troubleshootingTips(string $errorMessage, array $snapshot): array
    {
        $tips = [];
        $lower = strtolower($errorMessage);
        $port = (int) ($snapshot['port'] ?? 0);

        if ($port === 465 || str_contains($lower, 'ssl') || str_contains($lower, 'stream') || str_contains($lower, 'eof') || str_contains($lower, 'timed out')) {
            $tips[] = 'Port 465 requires SSL/SMTPS: in .env set MAIL_PORT=465, MAIL_ENCRYPTION=ssl, MAIL_SCHEME=smtps (or try TLS on Port 587 with MAIL_PORT=587, MAIL_ENCRYPTION=tls, MAIL_SCHEME=smtp).';
        } else {
            $tips[] = 'Port 465 uses SSL (MAIL_PORT=465, MAIL_ENCRYPTION=ssl, MAIL_SCHEME=smtps); Port 587 uses STARTTLS (MAIL_PORT=587, MAIL_ENCRYPTION=tls, MAIL_SCHEME=smtp).';
        }

        if (str_contains($lower, 'connection') || str_contains($lower, 'refused') || str_contains($lower, 'timed out') || str_contains($lower, 'getaddrinfo')) {
            $tips[] = 'On shared cPanel hosts where outbound remote connections are restricted, try MAIL_HOST=localhost (or the exact server hostname shown in cPanel → Email Accounts → Connect Devices).';
        }

        if (str_contains($lower, '535') || str_contains($lower, 'auth') || str_contains($lower, 'credentials')) {
            $tips[] = 'Check MAIL_USERNAME (use the full email address, e.g. support@amgacademichub.com) and wrap MAIL_PASSWORD="..." in double quotes in .env if it contains #, $, or spaces.';
        }

        $tips[] = 'Ensure MAIL_FROM_ADDRESS matches your cPanel domain email (e.g. support@amgacademichub.com).';

        if (! empty($snapshot['config_cached'])) {
            $tips[] = 'Config is currently cached — click "Reload .env config" above after saving .env in cPanel File Manager.';
        }

        return $tips;
    }

    private function maskUsername(string $username): ?string
    {
        if ($username === '') {
            return null;
        }

        if (str_contains($username, '@')) {
            [$local, $domain] = explode('@', $username, 2);
            $prefix = mb_substr($local, 0, min(3, mb_strlen($local)));

            return "{$prefix}***@{$domain}";
        }

        $prefix = mb_substr($username, 0, min(3, mb_strlen($username)));

        return "{$prefix}***";
    }
}
