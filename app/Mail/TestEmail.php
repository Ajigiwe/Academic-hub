<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly array $snapshot,
        public readonly ?string $triggeredBy = null,
    ) {}

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'Academic Hub');

        return new Envelope(
            subject: "Test Email from {$appName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test',
            with: [
                'name' => $this->triggeredBy ?: 'Administrator',
                'mailer' => $this->snapshot['mailer'] ?? 'smtp',
                'host' => $this->snapshot['host'] ?? '',
                'port' => $this->snapshot['port'] ?? '',
                'scheme' => $this->snapshot['scheme_label'] ?? 'auto',
                'from' => trim(($this->snapshot['from_name'] ?? '').' <'.($this->snapshot['from_address'] ?? '').'>'),
                'triggeredBy' => $this->triggeredBy,
                'sentAt' => now('UTC')->format('Y-m-d H:i:s').' UTC',
                'actionUrl' => route('admin.settings'),
            ],
        );
    }
}

