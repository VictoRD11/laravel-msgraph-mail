<?php

namespace VictoRD11\LaravelMsGraphMail;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\IdentificationHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use VictoRD11\LaravelMsGraphMail\Services\MicrosoftGraphApiService;

class MicrosoftGraphTransport extends AbstractTransport
{
    public const MIME_MODE_AUTO = 'auto';

    public const MIME_MODE_ALWAYS = 'always';

    public const MIME_MODE_NEVER = 'never';

    public const MIME_MODES = [
        self::MIME_MODE_AUTO,
        self::MIME_MODE_ALWAYS,
        self::MIME_MODE_NEVER,
    ];

    public function __construct(
        protected MicrosoftGraphApiService $microsoftGraphApiService,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
        protected string $mimeMode = self::MIME_MODE_AUTO,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'microsoft+graph+api://';
    }

    /**
     * @throws RequestException
     */
    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Message) {
            throw new LogicException(sprintf('%s only supports %s instances, %s given.', self::class, Message::class, get_debug_type($original)));
        }

        $email = MessageConverter::toEmail($original);
        $envelope = $message->getEnvelope();

        if ($this->shouldSendAsMime($email)) {
            $messages = $this->hasCalendarAttachment($email) ? $this->toMeetingRequests($email) : [$email];

            foreach ($messages as $mime) {
                $this->microsoftGraphApiService->sendMimeMail(
                    $envelope->getSender()->getAddress(),
                    $this->toMimeString($mime),
                );
            }

            return;
        }

        $html = $this->bodyToString($email->getHtmlBody());

        [$attachments, $html] = $this->prepareAttachments($email, $html);

        $payload = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $html === null ? 'Text' : 'HTML',
                    'content' => $html ?: $this->bodyToString($email->getTextBody()),
                ],
                'toRecipients' => $this->transformEmailAddresses($this->getRecipients($email, $envelope)),
                'ccRecipients' => $this->transformEmailAddresses(collect($email->getCc())),
                'bccRecipients' => $this->transformEmailAddresses(collect($email->getBcc())),
                'replyTo' => $this->transformEmailAddresses(collect($email->getReplyTo())),
                'sender' => $this->transformEmailAddress($envelope->getSender()),
                'attachments' => $attachments,
            ],
            'saveToSentItems' => config('mail.mailers.microsoft-graph.save_to_sent_items', false) ?? false,
        ];

        if (filled($headers = $this->getInternetMessageHeaders($email))) {
            $payload['message']['internetMessageHeaders'] = $headers;
        }

        $this->microsoftGraphApiService->sendMail($envelope->getSender()->getAddress(), $payload);
    }

    /**
     * Decide whether the message has to be submitted as raw MIME instead of the JSON payload.
     *
     * The JSON "fileAttachment" resource only carries a bare media type, so Content-Type
     * parameters such as "method=REQUEST" on text/calendar parts are lost and Outlook shows
     * a plain .ics file instead of a meeting request. Raw MIME keeps them intact.
     */
    protected function shouldSendAsMime(Email $email): bool
    {
        return match ($this->mimeMode) {
            self::MIME_MODE_ALWAYS => true,
            self::MIME_MODE_NEVER => false,
            default => $this->hasCalendarAttachment($email),
        };
    }

    protected function hasCalendarAttachment(Email $email): bool
    {
        foreach ($email->getAttachments() as $attachment) {
            if ($this->isCalendarPart($attachment)) {
                return true;
            }
        }

        return false;
    }

    protected function isCalendarPart(DataPart $part): bool
    {
        // The subtype may still carry Content-Type parameters, e.g. "calendar; method=REQUEST".
        $subtype = strtolower(trim((string) strtok($part->getMediaSubtype(), ';')));

        return $part->getMediaType() === 'text' && $subtype === 'calendar';
    }

    /**
     * Split a message with a calendar invitation into the messages submitted to Graph.
     *
     * Exchange turns such a message into a meeting request and delivers it only to the
     * ATTENDEE entries of the calendar, dropping every other To/Cc/Bcc recipient. To and Cc
     * recipients are therefore added as attendees, while each Bcc recipient gets a separate
     * invitation listing only themselves, so that no other recipient learns about them.
     *
     * @return list<Email>
     */
    protected function toMeetingRequests(Email $email): array
    {
        $messages = [];

        if (filled($email->getTo()) || filled($email->getCc())) {
            $headers = clone $email->getHeaders();
            $headers->remove('Bcc');

            $attendees = array_merge(
                array_map(fn (Address $address) => [$address, 'REQ-PARTICIPANT'], $email->getTo()),
                array_map(fn (Address $address) => [$address, 'OPT-PARTICIPANT'], $email->getCc()),
            );

            $messages[] = $this->withCalendar($email, $headers, fn (string $ics) => $this->addCalendarAttendees($ics, $attendees));
        }

        foreach ($email->getBcc() as $bcc) {
            $headers = clone $email->getHeaders();
            $headers->remove('Cc');
            $headers->remove('Bcc');
            // A fresh Message-ID is generated so that the copies are not treated as duplicates.
            $headers->remove('Message-ID');
            $headers->remove('To');
            $headers->addMailboxListHeader('To', [$bcc]);

            $messages[] = $this->withCalendar($email, $headers, fn (string $ics) => $this->addCalendarAttendees(
                $this->removeCalendarAttendees($ics),
                [[$bcc, 'OPT-PARTICIPANT']],
            ));
        }

        return $messages;
    }

    /**
     * Copy the message with the given headers, passing every calendar part through $transform.
     *
     * @param  callable(string): string  $transform
     */
    protected function withCalendar(Email $email, Headers $headers, callable $transform): Email
    {
        $copy = new Email($headers);

        if ($email->getTextBody() !== null) {
            $copy->text($email->getTextBody(), $email->getTextCharset() ?? 'utf-8');
        }

        if ($email->getHtmlBody() !== null) {
            $copy->html($email->getHtmlBody(), $email->getHtmlCharset() ?? 'utf-8');
        }

        foreach ($email->getAttachments() as $attachment) {
            $copy->addPart($this->isCalendarPart($attachment)
                ? new DataPart($transform($attachment->getBody()), $attachment->getFilename(), $attachment->getContentType())
                : $attachment);
        }

        return $copy;
    }

    /**
     * Add ATTENDEE properties to every VEVENT for addresses that are not listed yet.
     *
     * @param  array<array{0: Address, 1: string}>  $attendees  pairs of address and ROLE
     */
    protected function addCalendarAttendees(string $ics, array $attendees): string
    {
        $eol = str_contains($ics, "\r\n") ? "\r\n" : "\n";

        preg_match_all('/^ATTENDEE[;:].*?mailto:([^\s;:"]+)/mi', (string) preg_replace('/\r?\n[ \t]/', '', $ics), $matches);
        $listed = array_map('strtolower', $matches[1]);

        $lines = '';
        foreach ($attendees as [$address, $role]) {
            if (in_array(strtolower($address->getAddress()), $listed, true)) {
                continue;
            }

            $listed[] = strtolower($address->getAddress());

            $name = str_replace(['"', "\r", "\n"], '', $address->getName());
            $line = 'ATTENDEE;ROLE='.$role.';PARTSTAT=NEEDS-ACTION;RSVP=TRUE'
                .($name !== '' ? ';CN="'.$name.'"' : '')
                .':mailto:'.$address->getAddress();

            $lines .= $this->foldCalendarLine($line, $eol).$eol;
        }

        if ($lines === '') {
            return $ics;
        }

        return (string) preg_replace_callback('/^END:VEVENT/mi', fn (array $match) => $lines.$match[0], $ics);
    }

    protected function removeCalendarAttendees(string $ics): string
    {
        return (string) preg_replace('/^ATTENDEE[;:][^\r\n]*(?:\r?\n[ \t][^\r\n]*)*\r?\n/mi', '', $ics);
    }

    /**
     * Fold a content line to 75 octets as required by RFC 5545 without splitting UTF-8 characters.
     */
    protected function foldCalendarLine(string $line, string $eol): string
    {
        $chunks = [];
        $limit = 75;

        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $chunks[] = $chunk;
            $line = substr($line, strlen($chunk));
            // Continuation lines start with a space, which counts towards the limit.
            $limit = 74;
        }

        $chunks[] = $line;

        return implode($eol.' ', $chunks);
    }

    /**
     * Serialize the message to MIME for Graph.
     *
     * Symfony strips the Bcc header on serialization because SMTP carries Bcc in the envelope.
     * Graph has no envelope for MIME submissions, so the header is restored here to keep
     * Bcc recipients; Exchange removes it again before delivery.
     */
    protected function toMimeString(Email $email): string
    {
        $headers = $email->getPreparedHeaders();

        if (filled($bcc = $email->getBcc())) {
            $headers->addMailboxListHeader('Bcc', $bcc);
        }

        return $headers->toString().$email->getBody()->toString();
    }

    /**
     * Symfony exposes message bodies either as a string or as a stream resource.
     *
     * @param  resource|string|null  $body
     */
    protected function bodyToString(mixed $body): ?string
    {
        if (is_string($body) || $body === null) {
            return $body;
        }

        $contents = stream_get_contents($body);

        return $contents === false ? null : $contents;
    }

    /**
     * @return array<int, array<int<0, max>, array<string, bool|string|null>>|string|null>
     */
    protected function prepareAttachments(Email $email, ?string $html): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $fileName = $headers->getHeaderParameter('Content-Disposition', 'filename');

            // Inline parts embedded via Message::embed() reference the generated Content-ID
            // in the HTML body, so it has to be used here for the "cid:" lookup to succeed.
            $contentIdHeader = $headers->get('Content-ID');
            $contentId = $contentIdHeader instanceof IdentificationHeader ? $contentIdHeader->getId() : null;

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $fileName,
                'contentType' => $attachment->getMediaType(),
                'contentBytes' => base64_encode($attachment->getBody()),
                'contentId' => $contentId ?? $fileName,
                'isInline' => $headers->getHeaderBody('Content-Disposition') === 'inline',
            ];
        }

        return [$attachments, $html];
    }

    /**
     * @param  Collection<array-key, Address>  $recipients
     * @return array<array-key, array{emailAddress: array{address: string}}>
     */
    protected function transformEmailAddresses(Collection $recipients): array
    {
        return $recipients
            ->map(fn (Address $recipient) => $this->transformEmailAddress($recipient))
            ->all();
    }

    /**
     * @return array{emailAddress: array{address: string}}
     */
    protected function transformEmailAddress(Address $address): array
    {
        return [
            'emailAddress' => [
                'address' => $address->getAddress(),
            ],
        ];
    }

    /**
     * @return Collection<array-key, Address>
     */
    protected function getRecipients(Email $email, Envelope $envelope): Collection
    {
        return collect($envelope->getRecipients())
            ->filter(fn (Address $address) => ! in_array($address, array_merge($email->getCc(), $email->getBcc()), true));
    }

    /**
     * Transforms given Symfony Headers
     * to Microsoft Graph internet message headers
     * see https://learn.microsoft.com/en-us/graph/api/resources/internetmessageheader?view=graph-rest-1.0
     *
     * @return list<array{name: string, value: string}>|null
     */
    protected function getInternetMessageHeaders(Email $email): ?array
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof HeaderInterface && str_starts_with($header->getName(), 'X-')) {
                $headers[] = ['name' => $header->getName(), 'value' => $header->getBodyAsString()];
            }
        }

        return $headers ?: null;
    }
}
