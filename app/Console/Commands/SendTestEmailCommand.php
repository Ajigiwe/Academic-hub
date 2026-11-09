<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email? : The recipient email address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a branded test email to verify SMTP configuration and delivery';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recipient = $this->argument('email') ?: $this->ask('Enter the recipient email address');

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$recipient}");
            return Command::FAILURE;
        }

        $mailer = config('mail.default');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->info("=== Mail Configuration Test ===");
        $this->line("Default Mailer: <comment>{$mailer}</comment>");
        $this->line("From Address:   <comment>{$fromAddress} ({$fromName})</comment>");

        if ($mailer === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            $scheme = config('mail.mailers.smtp.scheme') ?: 'none (STARTTLS)';
            $user = config('mail.mailers.smtp.username') ? substr(config('mail.mailers.smtp.username'), 0, 4) . '***' : 'none';
            $this->line("SMTP Host:      <comment>{$host}</comment>");
            $this->line("SMTP Port:      <comment>{$port}</comment>");
            $this->line("SMTP Scheme:    <comment>{$scheme}</comment>");
            $this->line("SMTP Username:  <comment>{$user}</comment>");
        }

        $this->newLine();
        $this->info("Sending test email to: {$recipient}...");

        try {
            Mail::send('emails.reset-password', [
                'name' => 'Academic Hub Tester',
                'email' => $recipient,
                'url' => route('login'),
            ], function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject('Test Email from Academic Hub');
            });

            $this->newLine();
            $this->info("✓ Test email dispatched successfully!");
            $this->line("Please check the inbox (and spam/junk folder) of: <comment>{$recipient}</comment>");
            
            if ($mailer === 'log') {
                $this->warn("Note: MAIL_MAILER is currently set to 'log'. The email was written to storage/logs/laravel.log rather than sent over the internet.");
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error("✗ Failed to send test email:");
            $this->line($e->getMessage());

            $this->newLine();
            $this->warn("Troubleshooting Tips:");
            $this->line("1. Port 465 requires SSL / smtps. In .env set: MAIL_PORT=465, MAIL_ENCRYPTION=ssl, MAIL_SCHEME=smtps");
            $this->line("2. Port 587 requires TLS. In .env set: MAIL_PORT=587, MAIL_ENCRYPTION=tls, MAIL_SCHEME=smtp");
            $this->line("3. Ensure MAIL_USERNAME matches the full email address (e.g. support@amgacademichub.com)");
            $this->line("4. Check that MAIL_FROM_ADDRESS matches the authorized sending domain in cPanel.");

            return Command::FAILURE;
        }
    }
}
