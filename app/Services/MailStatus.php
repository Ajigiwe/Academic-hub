<?php

namespace App\Services;

use App\Mail\TestEmail;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
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
     *     using_saved_settings: bool,
     *     config_source_label: string,
     *     smtp_ready: bool,
     *     env_path: string,
     *     env_exists: bool,
     *     env_parse_ok: bool,
     *     env_mailer: ?string,
     *     duplicate_mail_keys: list<string>,
     *     note: ?string
     * }
     */
    public function status(?string $envPathOverride = null): array
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
        $usingSavedSettings = (new MailSettings)->exists();
        $envInfo = $this->inspectEnvFile($envPathOverride);

        $configSourceLabel = match (true) {
            $usingSavedSettings => 'Admin Panel (encrypted server storage)',
            $configCached => 'Cached (bootstrap/cache/config.php)',
            default => 'Live (.env)',
        };

        $smtpReady = $mailer === 'smtp'
            && $host !== ''
            && $port !== ''
            && $username !== ''
            && $passwordSet;

        $note = null;
        if ($usingSavedSettings) {
            if ($isLocalOnly) {
                $note = sprintf(
                    'Saved Admin Panel mail settings are currently set to "%s", so emails are written to storage/logs/laravel.log instead of being delivered to inboxes. Switch Mailer to "smtp" below and save to send real emails.',
                    $mailer,
                );
            } elseif ($mailer === 'smtp' && ! $smtpReady) {
                $missing = [];
                if ($host === '') {
                    $missing[] = 'SMTP host';
                }
                if ($port === '') {
                    $missing[] = 'Port';
                }
                if ($username === '') {
                    $missing[] = 'SMTP username';
                }
                if (! $passwordSet) {
                    $missing[] = 'SMTP password';
                }

                $note = 'Saved Admin Panel SMTP settings are missing: '.implode(', ', $missing).'.';
            }
        } elseif ($envInfo['env_exists'] && ! $envInfo['env_parse_ok']) {
            $note = sprintf(
                'Your active environment file (%s) has a syntax error (such as an unquoted space or unclosed quote), so Laravel may not be reading all MAIL_* values. You can configure SMTP directly in the form below instead, or fix .env in cPanel File Manager and click "Reload .env config".',
                $envInfo['env_path'],
            );
        } elseif ($configCached && $envInfo['env_mailer'] !== null && $envInfo['env_mailer'] !== $mailer) {
            $note = sprintf(
                'Config cache is overriding .env: %s sets MAIL_MAILER="%s", but cached config is still using "%s". Click "Reload .env config" in the top-right corner of this card, or save your SMTP settings below.',
                $envInfo['env_path'],
                $envInfo['env_mailer'],
                $mailer,
            );
        } elseif ($isLocalOnly && $envInfo['env_mailer'] === 'smtp') {
            $note = sprintf(
                '%s already sets MAIL_MAILER="smtp", but PHP runtime still sees "%s". Configure SMTP directly in the form below to bypass cached/external environment overrides, or click "Reload .env config" above.',
                $envInfo['env_path'],
                $mailer,
            );
        } elseif ($isLocalOnly) {
            $note = sprintf(
                'Mailer is set to "%s", so emails (including password resets and order receipts) are written to storage/logs/laravel.log instead of being delivered to inboxes. Enter your SMTP credentials in the form below (recommended), or update MAIL_* values in %s.',
                $mailer,
                $envInfo['env_path'],
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
            'using_saved_settings' => $usingSavedSettings,
            'config_source_label' => $configSourceLabel,
            'smtp_ready' => $smtpReady,
            'env_path' => $envInfo['env_path'],
            'env_exists' => $envInfo['env_exists'],
            'env_parse_ok' => $envInfo['env_parse_ok'],
            'env_mailer' => $envInfo['env_mailer'],
            'duplicate_mail_keys' => $envInfo['duplicate_mail_keys'],
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
            $tips[] = 'Port 465 requires SSL / SMTPS encryption (or switch to Port 587 with TLS / STARTTLS encryption).';
        } else {
            $tips[] = 'Port 465 uses SSL / SMTPS encryption; Port 587 uses TLS / STARTTLS encryption.';
        }

        if (str_contains($lower, 'connection') || str_contains($lower, 'refused') || str_contains($lower, 'timed out') || str_contains($lower, 'getaddrinfo')) {
            $tips[] = 'On shared cPanel hosts where outbound remote connections are restricted, try SMTP host "localhost" (or the exact server hostname shown in cPanel → Email Accounts → Connect Devices).';
        }

        if (str_contains($lower, '535') || str_contains($lower, 'auth') || str_contains($lower, 'credentials')) {
            $tips[] = 'Check SMTP username (use the full email address, e.g. support@amgacademichub.com) and re-enter the email account password in the SMTP form above.';
        }

        $tips[] = 'Ensure From email address matches your cPanel domain email (e.g. support@amgacademichub.com).';

        if (! empty($snapshot['config_cached']) && empty($snapshot['using_saved_settings'])) {
            $tips[] = 'Config is currently cached — click "Reload .env config" above after saving .env in cPanel File Manager.';
        }

        return $tips;
    }

    /**
     * Inspect the active .env file for common cPanel mistakes (wrong folder,
     * duplicate MAIL_* blocks, or Dotenv syntax errors) without ever returning secrets.
     *
     * @return array{
     *     env_path: string,
     *     env_exists: bool,
     *     env_parse_ok: bool,
     *     env_mailer: ?string,
     *     duplicate_mail_keys: list<string>
     * }
     */
    private function inspectEnvFile(?string $envPathOverride = null): array
    {
        $envPath = $envPathOverride ?: app()->environmentFilePath();

        if (! is_file($envPath) || ! is_readable($envPath)) {
            return [
                'env_path' => $envPath,
                'env_exists' => is_file($envPath),
                'env_parse_ok' => false,
                'env_mailer' => null,
                'duplicate_mail_keys' => [],
            ];
        }

        $raw = (string) file_get_contents($envPath);
        $parseOk = true;
        $parsedKeys = [];
        try {
            $parsedKeys = Dotenv::parse($raw);
        } catch (InvalidFileException) {
            $parseOk = false;
        }

        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $mailKeyLines = [];
        $envMailer = null;

        foreach ($lines as $idx => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            if (str_starts_with($trimmed, 'export ')) {
                $trimmed = ltrim(substr($trimmed, 7));
            }
            if (preg_match('/^([A-Z0-9_]+)\s*=(.*)$/', $trimmed, $m) !== 1) {
                continue;
            }

            $key = $m[1];
            if (! str_starts_with($key, 'MAIL_')) {
                continue;
            }

            $mailKeyLines[$key][] = $idx + 1;

            if ($key === 'MAIL_MAILER') {
                $val = trim($m[2]);
                if (
                    (str_starts_with($val, '"') && str_ends_with($val, '"') && strlen($val) >= 2)
                    || (str_starts_with($val, "'") && str_ends_with($val, "'") && strlen($val) >= 2)
                ) {
                    $val = substr($val, 1, -1);
                } elseif (str_contains($val, ' #')) {
                    $val = trim(explode(' #', $val, 2)[0]);
                }
                $envMailer = $val !== '' ? strtolower($val) : null;
            }
        }

        if ($parseOk && isset($mailKeyLines['MAIL_MAILER']) && ! array_key_exists('MAIL_MAILER', $parsedKeys)) {
            $parseOk = false;
        }

        $duplicates = [];
        foreach ($mailKeyLines as $key => $lineNumbers) {
            if (count($lineNumbers) > 1) {
                $duplicates[] = $key.' (lines '.implode(', ', $lineNumbers).')';
            }
        }

        return [
            'env_path' => $envPath,
            'env_exists' => true,
            'env_parse_ok' => $parseOk,
            'env_mailer' => $envMailer,
            'duplicate_mail_keys' => $duplicates,
        ];
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
