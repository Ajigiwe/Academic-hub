<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Encrypted server-side SMTP configuration managed via Admin -> Settings.
 *
 * Shared cPanel hosts without terminal access often suffer from cached config
 * files, split deployment folders, or LiteSpeed/Apache environment variables
 * overriding .env. Storing admin-configured SMTP credentials encrypted in
 * private storage (`storage/app/private/mail/smtp.enc`) lets the admin configure
 * and verify mail directly from the browser without touching .env or SSH.
 */
class MailSettings
{
    public const STORAGE_KEY = 'mail/smtp.enc';

    /**
     * Tests leave this false so PHPUnit's isolated mail assertions never load
     * developer machine overrides; MailSettings-specific tests opt in cleanly.
     */
    public static bool $applyInTests = false;

    public function exists(): bool
    {
        if ($this->overridesDisabledForTests()) {
            return false;
        }

        try {
            return Storage::disk('local')->exists(self::STORAGE_KEY);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     mailer: string,
     *     host: string,
     *     port: int,
     *     encryption: string,
     *     scheme: ?string,
     *     username: string,
     *     password: string,
     *     from_address: string,
     *     from_name: string,
     *     updated_at?: string
     * }|null
     */
    public function get(): ?array
    {
        if ($this->overridesDisabledForTests()) {
            return null;
        }

        try {
            $disk = Storage::disk('local');
            if (! $disk->exists(self::STORAGE_KEY)) {
                return null;
            }

            $raw = $disk->get(self::STORAGE_KEY);
            if (! is_string($raw) || $raw === '') {
                return null;
            }

            $decrypted = Crypt::decryptString($raw);
            $data = json_decode($decrypted, true);

            if (! is_array($data) || empty($data['mailer'])) {
                return null;
            }

            return $data;
        } catch (Throwable $e) {
            Log::warning('Unable to read saved SMTP settings override.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Encrypt and persist SMTP configuration, then activate it immediately.
     *
     * @param  array{
     *     mailer: string,
     *     host?: ?string,
     *     port?: int|string|null,
     *     encryption?: ?string,
     *     username?: ?string,
     *     password?: ?string,
     *     from_address: string,
     *     from_name: string
     * }  $input
     */
    public function save(array $input): array
    {
        $existing = $this->get();

        $mailer = strtolower(trim((string) $input['mailer']));
        $port = (int) ($input['port'] ?? 465);
        $encryption = strtolower(trim((string) ($input['encryption'] ?? 'ssl')));
        $scheme = self::resolveScheme($encryption, $port);

        $incomingPassword = (string) ($input['password'] ?? '');
        $password = $incomingPassword !== ''
            ? $incomingPassword
            : (string) ($existing['password'] ?? '');

        $payload = [
            'mailer' => $mailer,
            'host' => trim((string) ($input['host'] ?? '')),
            'port' => $port > 0 ? $port : 465,
            'encryption' => in_array($encryption, ['ssl', 'tls', 'none'], true) ? $encryption : 'ssl',
            'scheme' => $scheme,
            'username' => trim((string) ($input['username'] ?? '')),
            'password' => $password,
            'from_address' => trim((string) $input['from_address']),
            'from_name' => trim((string) $input['from_name']),
            'updated_at' => now('UTC')->toIso8601String(),
        ];

        $encrypted = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        Storage::disk('local')->put(self::STORAGE_KEY, $encrypted);

        $this->applyConfig($payload);
        Mail::forgetMailers();

        return $payload;
    }

    public function clear(): void
    {
        Storage::disk('local')->delete(self::STORAGE_KEY);
        Mail::forgetMailers();
    }

    /**
     * Safe, secret-free defaults for populating the Admin Settings form.
     *
     * @return array{
     *     mailer: string,
     *     host: string,
     *     port: string,
     *     encryption: string,
     *     username: string,
     *     has_saved_password: bool,
     *     from_address: string,
     *     from_name: string
     * }
     */
    public function formDefaults(): array
    {
        $saved = $this->get();
        if ($saved !== null) {
            return [
                'mailer' => $saved['mailer'],
                'host' => $saved['host'],
                'port' => (string) $saved['port'],
                'encryption' => $saved['encryption'],
                'username' => $saved['username'],
                'has_saved_password' => $saved['password'] !== '',
                'from_address' => $saved['from_address'],
                'from_name' => $saved['from_name'],
            ];
        }

        $host = trim((string) config('mail.mailers.smtp.host', ''));
        $port = (int) config('mail.mailers.smtp.port', 465);
        $scheme = config('mail.mailers.smtp.scheme');
        $fromAddress = trim((string) config('mail.from.address', ''));

        $encryption = match (true) {
            $scheme === 'smtps' || $port === 465 => 'ssl',
            $scheme === 'smtp' || $port === 587 => 'tls',
            default => 'ssl',
        };

        return [
            'mailer' => 'smtp',
            'host' => $host === '127.0.0.1' ? '' : $host,
            'port' => $port === 2525 ? '465' : (string) $port,
            'encryption' => $encryption,
            'username' => trim((string) config('mail.mailers.smtp.username', '')),
            'has_saved_password' => false,
            'from_address' => $fromAddress === 'hello@example.com' ? '' : $fromAddress,
            'from_name' => trim((string) config('mail.from.name', config('app.name', 'Academic Hub'))),
        ];
    }

    public static function apply(): bool
    {
        $instance = new static;
        if ($instance->overridesDisabledForTests()) {
            return false;
        }

        $settings = $instance->get();
        if ($settings === null) {
            return false;
        }

        $instance->applyConfig($settings);

        return true;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function applyConfig(array $settings): void
    {
        $port = (int) ($settings['port'] ?? 465);
        $encryption = (string) ($settings['encryption'] ?? 'ssl');
        $scheme = array_key_exists('scheme', $settings)
            ? $settings['scheme']
            : self::resolveScheme($encryption, $port);

        config([
            'mail.default' => $settings['mailer'] ?? 'smtp',
            'mail.mailers.smtp.host' => $settings['host'] ?? '',
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.username' => $settings['username'] ?? '',
            'mail.mailers.smtp.password' => $settings['password'] ?? '',
            'mail.from.address' => $settings['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['from_name'] ?? config('mail.from.name'),
        ]);
    }

    public static function resolveScheme(string $encryption, int $port): ?string
    {
        return match (strtolower($encryption)) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            'none' => null,
            default => $port === 465 ? 'smtps' : 'smtp',
        };
    }

    private function overridesDisabledForTests(): bool
    {
        return app()->runningUnitTests() && ! static::$applyInTests;
    }
}
