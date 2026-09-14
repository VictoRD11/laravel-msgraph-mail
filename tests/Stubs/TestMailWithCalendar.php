<?php

namespace VictoRD11\LaravelMsGraphMail\Tests\Stubs;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TestMailWithCalendar extends Mailable
{
    use Queueable, SerializesModels;

    public const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:foo\r\nSUMMARY:Dev Test\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Dev Test',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content()
    {
        return new Content(html: 'html-mail');
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => self::ICS, 'invite.ics')
                ->withMime('text/calendar; charset=UTF-8; method=REQUEST'),
        ];
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-FE-Attachment-Name' => 'invite.ics',
        ]);
    }
}
