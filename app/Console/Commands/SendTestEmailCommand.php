<?php

namespace App\Console\Commands;

use App\Services\MailStatus;
use Illuminate\Console\Command;

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
    public function handle(MailStatus $mailStatus): int
    {
        $recipient = $this->argument('email') ?: $this->ask('Enter the recipient email address');

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$recipient}");
            return Command::FAILURE;
        }

        $status = $mailStatus->status();

        $this->info("=== Mail Configuration Test ===");
        $this->line("Default Mailer: <comment>{$status['mailer']}</comment>");
        $this->line("From Address:   <comment>{$status['from_address']} ({$status['from_name']})</comment>");

        if ($status['mailer'] === 'smtp') {
            $this->line("SMTP Host:      <comment>{$status['host']}</comment>");
            $this->line("SMTP Port:      <comment>{$status['port']}</comment>");
            $this->line("SMTP Scheme:    <comment>{$status['scheme_label']}</comment>");
            $this->line("SMTP Username:  <comment>" . ($status['username_masked'] ?? 'none') . "</comment>");
        }

        $this->newLine();
        $this->info("Sending test email to: {$recipient}...");

        $result = $mailStatus->sendTest($recipient, 'artisan mail:test');

        if ($result['ok']) {
            $this->newLine();
            $this->info("✓ Test email dispatched successfully!");
            $this->line("Please check the inbox (and spam/junk folder) of: <comment>{$recipient}</comment>");

            if ($result['is_local_only']) {
                $this->warn("Note: MAIL_MAILER is currently set to '{$result['mailer']}'. The email was written to storage/logs/laravel.log rather than sent over the internet.");
            }

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->error("✗ Failed to send test email:");
        $this->line($result['error'] ?? 'Unknown error');

        if (! empty($result['tips'])) {
            $this->newLine();
            $this->warn("Troubleshooting Tips:");
            foreach ($result['tips'] as $idx => $tip) {
                $num = $idx + 1;
                $this->line("{$num}. {$tip}");
            }
        }

        return Command::FAILURE;
    }
}

