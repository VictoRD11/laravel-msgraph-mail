<?php

namespace VictoRD11\LaravelMsGraphMail\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use VictoRD11\LaravelMsGraphMail\Contracts\TokenProviderInterface;

class MicrosoftGraphApiService
{
    public function __construct(
        protected readonly TokenProviderInterface $tokenProvider
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendMail(string $from, array $payload): Response
    {
        return $this->getBaseRequest()
            ->post("/users/{$from}/sendMail", $payload)
            ->throw();
    }

    /**
     * Send a raw MIME message.
     * see https://learn.microsoft.com/en-us/graph/outlook-send-mime-message
     */
    public function sendMimeMail(string $from, string $mimeMessage): Response
    {
        return $this->getBaseRequest()
            ->withBody(base64_encode($mimeMessage), 'text/plain')
            ->post("/users/{$from}/sendMail")
            ->throw();
    }

    protected function getBaseRequest(): PendingRequest
    {
        return Http::withToken($this->tokenProvider->getAccessToken())
            ->baseUrl('https://graph.microsoft.com/v1.0');
    }
}
