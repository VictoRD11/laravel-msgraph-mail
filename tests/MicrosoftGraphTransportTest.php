<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use VictoRD11\LaravelMsGraphMail\Exceptions\ConfigurationInvalid;
use VictoRD11\LaravelMsGraphMail\Exceptions\ConfigurationMissing;
use VictoRD11\LaravelMsGraphMail\Exceptions\InvalidResponse;
use VictoRD11\LaravelMsGraphMail\Tests\Stubs\TestMail;
use VictoRD11\LaravelMsGraphMail\Tests\Stubs\TestMailWithCalendar;
use VictoRD11\LaravelMsGraphMail\Tests\Stubs\TestMailWithInlineImage;

it('sends html mails with microsoft graph', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
        'save_to_sent_items' => null,
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMail);

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => '<b>Test</b>'.PHP_EOL,
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'caleb@livewire.com',
                            ],
                        ],
                    ],
                    'ccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'nuno@laravel.com',
                            ],
                        ],
                    ],
                    'bccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'tim@innoge.de',
                            ],
                        ],
                    ],
                    'replyTo' => [],
                    'sender' => [
                        'emailAddress' => [
                            'address' => 'taylor@laravel.com',
                        ],
                    ],
                    'attachments' => [
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-1.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-1.txt',
                            'isInline' => false,
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-2.txt',
                            'isInline' => false,
                        ],
                    ],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});

it('sends text mails with microsoft graph', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMail(false));

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'Text',
                        'content' => 'Test'.PHP_EOL,
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'caleb@livewire.com',
                            ],
                        ],
                    ],
                    'ccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'nuno@laravel.com',
                            ],
                        ],
                    ],
                    'bccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'tim@innoge.de',
                            ],
                        ],
                    ],
                    'replyTo' => [],
                    'sender' => [
                        'emailAddress' => [
                            'address' => 'taylor@laravel.com',
                        ],
                    ],
                    'attachments' => [
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-1.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-1.txt',
                            'isInline' => false,
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-2.txt',
                            'isInline' => false,
                        ],
                    ],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});

it('creates an oauth access token', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Http::fake([
        'https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token' => Http::response(['access_token' => 'foo_access_token']),
        'https://graph.microsoft.com/v1.0*' => Http::response(['value' => []]),
    ]);

    Mail::to('caleb@livewire.com')
        ->send(new TestMail(false));

    Http::assertSent(function (Request $request) {
        if (Str::startsWith($request->url(), 'https://login.microsoftonline.com')) {
            expect($request)
                ->url()->toBe('https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token')
                ->isForm()->toBeTrue()
                ->body()->toBe('grant_type=client_credentials&client_id=foo_client_id&client_secret=foo_client_secret&scope=https%3A%2F%2Fgraph.microsoft.com%2F.default');
        }

        return true;
    });

    expect(Cache::get('microsoft-graph-api-client-credentials-access-token'))
        ->toBe('foo_access_token');
});

it('throws exceptions on invalid access token in response', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Http::fake([
        'https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token' => Http::response(['access_token' => 123]),
    ]);

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(InvalidResponse::class, 'Expected response to contain key access_token of type string, got: 123.');
});

it('throws exceptions when config is invalid', function (array $config, Exception $exception) {
    Config::set('mail.mailers.microsoft-graph', $config);
    Config::set('mail.default', 'microsoft-graph');

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(get_class($exception), $exception->getMessage());
})->with([
    [
        [
            'transport' => 'microsoft-graph',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationMissing('tenant_id'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 123,
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationInvalid('tenant_id', 123),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_secret' => 'foo_client_secret',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationMissing('client_id'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => '',
            'client_secret' => 'foo_client_secret',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationInvalid('client_id', ''),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => 'foo_client_id',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationMissing('client_secret'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => 'foo_client_id',
            'client_secret' => null,
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationInvalid('client_secret', null),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
        ],
        new ConfigurationMissing('from.address'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
            'access_token_ttl' => false,
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationInvalid('access_token_ttl', false),
    ],
]);

it('sends html mails with inline images with microsoft graph', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');
    Config::set('filesystems.default', 'local');
    Config::set('filesystems.disks.local.root', realpath(__DIR__.'/Resources/files'));

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMailWithInlineImage);

    Http::assertSent(function (Request $value) {
        // ContentId gets random generated, so get this value first and check for equality later
        $inlineImageContentId = json_decode($value->body())->message->attachments[0]->contentId;

        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => '<b>Test</b><img src="cid:'.$inlineImageContentId.'">'.PHP_EOL,
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'caleb@livewire.com',
                            ],
                        ],
                    ],
                    'ccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'nuno@laravel.com',
                            ],
                        ],
                    ],
                    'bccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'tim@innoge.de',
                            ],
                        ],
                    ],
                    'replyTo' => [],
                    'sender' => [
                        'emailAddress' => [
                            'address' => 'taylor@laravel.com',
                        ],
                    ],
                    'attachments' => [
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'blue.jpg',
                            'contentType' => 'image',
                            'contentBytes' => '/9j/4AAQSkZJRgABAQEASABIAAD//gATQ3JlYXRlZCB3aXRoIEdJTVD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wgARCABLAGQDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAWAQEBAQAAAAAAAAAAAAAAAAAABQj/2gAMAwEAAhADEAAAAZ71TDAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAABw/9oACAEBAAEFAgL/xAAUEQEAAAAAAAAAAAAAAAAAAABw/9oACAEDAQE/AQL/xAAUEQEAAAAAAAAAAAAAAAAAAABw/9oACAECAQE/AQL/xAAUEAEAAAAAAAAAAAAAAAAAAABw/9oACAEBAAY/AgL/xAAUEAEAAAAAAAAAAAAAAAAAAABw/9oACAEBAAE/IQL/2gAMAwEAAgADAAAAEEkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkv/xAAUEQEAAAAAAAAAAAAAAAAAAABw/9oACAEDAQE/EAL/xAAUEQEAAAAAAAAAAAAAAAAAAABw/9oACAECAQE/EAL/xAAUEAEAAAAAAAAAAAAAAAAAAABw/9oACAEBAAE/EAL/2Q==',
                            'contentId' => $inlineImageContentId,
                            'isInline' => true,
                        ],
                    ],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});

test('the configured mail sender can be overwritten', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    $mailable = new TestMail(false);
    $mailable->from('other-mail@laravel.com', 'Other Mail');

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send($mailable);

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/other-mail@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'Text',
                        'content' => 'Test'.PHP_EOL,
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'caleb@livewire.com',
                            ],
                        ],
                    ],
                    'ccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'nuno@laravel.com',
                            ],
                        ],
                    ],
                    'bccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'tim@innoge.de',
                            ],
                        ],
                    ],
                    'replyTo' => [],
                    'sender' => [
                        'emailAddress' => [
                            'address' => 'other-mail@laravel.com',
                        ],
                    ],
                    'attachments' => [
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-1.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-1.txt',
                            'isInline' => false,
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-2.txt',
                            'isInline' => false,
                        ],
                    ],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});

it('sends custom mail headers with microsoft graph', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
        'save_to_sent_items' => null,
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMail(includeHeaders: true));

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => '<b>Test</b>'.PHP_EOL,
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'caleb@livewire.com',
                            ],
                        ],
                    ],
                    'ccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'nuno@laravel.com',
                            ],
                        ],
                    ],
                    'bccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'tim@innoge.de',
                            ],
                        ],
                    ],
                    'replyTo' => [],
                    'sender' => [
                        'emailAddress' => [
                            'address' => 'taylor@laravel.com',
                        ],
                    ],
                    'attachments' => [
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-1.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-1.txt',
                            'isInline' => false,
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-2.txt',
                            'isInline' => false,
                        ],
                    ],
                    'internetMessageHeaders' => [[
                        'name' => 'X-Custom-Header',
                        'value' => 'Custom Header',
                    ]],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});

it('creates an oauth access token with password authentication', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'auth_method' => 'password',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'username' => 'test@example.com',
        'password' => 'test_password',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Http::fake([
        'https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token' => Http::response(['access_token' => 'foo_access_token']),
        'https://graph.microsoft.com/v1.0*' => Http::response(['value' => []]),
    ]);

    Mail::to('taylor@laravel.com')
        ->send(new TestMail(false));

    Http::assertSent(function (Request $request) {
        if (Str::startsWith($request->url(), 'https://login.microsoftonline.com')) {
            expect($request)
                ->url()->toBe('https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token')
                ->isForm()->toBeTrue()
                ->body()->toBe('grant_type=password&username=test%40example.com&password=test_password&client_id=foo_client_id&client_secret=foo_client_secret&scope=https%3A%2F%2Fgraph.microsoft.com%2F.default');
        }

        return true;
    });

    expect(Cache::get('microsoft-graph-api-password-access-token'))
        ->toBe('foo_access_token');
});

it('throws exceptions when password auth config is invalid', function (array $config, Exception $exception) {
    Config::set('mail.mailers.microsoft-graph', $config);
    Config::set('mail.default', 'microsoft-graph');

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(get_class($exception), $exception->getMessage());
})->with([
    [
        [
            'transport' => 'microsoft-graph',
            'auth_method' => 'password',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
            'tenant_id' => 'foo_tenant_id',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationMissing('username'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'auth_method' => 'password',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
            'tenant_id' => 'foo_tenant_id',
            'username' => 'test@example.com',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationMissing('password'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'auth_method' => 'password',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
            'tenant_id' => 'foo_tenant_id',
            'username' => '',
            'password' => 'test_password',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationInvalid('username', ''),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'auth_method' => 'invalid_method',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
            'tenant_id' => 'foo_tenant_id',
            'from' => [
                'address' => 'taylor@laravel.com',
                'name' => 'Taylor Otwell',
            ],
        ],
        new ConfigurationInvalid('auth_method', 'invalid_method'),
    ],
]);

it('sends mails with calendar attachments as mime with microsoft graph', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMailWithCalendar);

    Http::assertSentCount(2);

    $requests = Http::recorded()->map(fn (array $pair) => $pair[0]);

    foreach ($requests as $request) {
        expect($request)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->hasHeader('Content-Type', 'text/plain')->toBeTrue();
    }

    $invitation = base64_decode($requests[0]->body(), true);

    expect($invitation)
        ->toBeString()
        ->toContain('Subject: Dev Test')
        ->toContain('From: Taylor Otwell <taylor@laravel.com>')
        ->toContain('To: caleb@livewire.com')
        ->toContain('Cc: nuno@laravel.com')
        ->not->toContain('Bcc:')
        ->not->toContain('tim@innoge.de')
        ->toContain('X-FE-Attachment-Name: invite.ics')
        ->toContain('Content-Type: text/calendar; charset=UTF-8; method=REQUEST; name=invite.ics')
        ->toContain('Content-Disposition: attachment; name=invite.ics; filename=invite.ics')
        ->toContain('<b>Test</b>');

    expect(unfoldCalendar(calendarFromMime($invitation)))->toBe(str_replace(
        "END:VEVENT\r\n",
        "ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:caleb@livewire.com\r\n"
        ."ATTENDEE;ROLE=OPT-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:nuno@laravel.com\r\n"
        ."END:VEVENT\r\n",
        TestMailWithCalendar::ICS,
    ));

    $bccCopy = base64_decode($requests[1]->body(), true);

    expect($bccCopy)
        ->toBeString()
        ->toContain('Subject: Dev Test')
        ->toContain('To: tim@innoge.de')
        ->not->toContain('Cc:')
        ->not->toContain('Bcc:')
        ->not->toContain('caleb@livewire.com')
        ->not->toContain('nuno@laravel.com')
        ->toContain('<b>Test</b>');

    expect(unfoldCalendar(calendarFromMime($bccCopy)))->toBe(str_replace(
        "END:VEVENT\r\n",
        "ATTENDEE;ROLE=OPT-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:tim@innoge.de\r\n"
        ."END:VEVENT\r\n",
        TestMailWithCalendar::ICS,
    ));

    preg_match_all('/^Message-ID: (.+)$/m', $invitation.$bccCopy, $ids);
    expect(array_unique($ids[1]))->toHaveCount(2);
});

it('keeps existing calendar attendees and folds added ones', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    $ics = "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:foo\r\n"
        ."ATTENDEE;ROLE=REQ-PARTICIPANT;CN=Caleb:mailto:CALEB@\r\n livewire.com\r\n"
        ."END:VEVENT\r\nEND:VCALENDAR\r\n";

    $email = (new Email)
        ->from('taylor@laravel.com')
        ->to('caleb@livewire.com')
        ->cc(new Address('nuno@laravel.com', 'Нуно Мадуро "Laravel" Very Long Display Name'))
        ->subject('Calendar Test')
        ->html('<b>Test</b>')
        ->attach($ics, 'invite.ics', 'text/calendar; method=REQUEST');

    Mail::mailer('microsoft-graph')->getSymfonyTransport()->send($email);

    Http::assertSentCount(1);

    $calendar = calendarFromMime(base64_decode(Http::recorded()[0][0]->body(), true));

    expect(substr_count($calendar, 'ATTENDEE'))->toBe(2);

    foreach (explode("\r\n", $calendar) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    expect(unfoldCalendar($calendar))->toContain(
        'ATTENDEE;ROLE=OPT-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN="Нуно Мадуро Laravel Very Long Display Name":mailto:nuno@laravel.com'
        ."\r\nEND:VEVENT"
    );
});

function unfoldCalendar(string $ics): string
{
    return (string) preg_replace('/\r\n[ \t]/', '', $ics);
}

function calendarFromMime(string $mime): string
{
    preg_match('/Content-Type: text\/calendar.*?\r\n\r\n(.*?)\r\n--/s', $mime, $match);

    return (string) base64_decode($match[1], true);
}

it('sends every mail as mime when mime_mode is always', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'mime_mode' => 'always',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->send(new TestMail(false));

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Content-Type', 'text/plain')->toBeTrue();

        expect(base64_decode($value->body(), true))
            ->toBeString()
            ->toContain('Subject: Dev Test')
            ->toContain('To: caleb@livewire.com')
            ->toContain('Content-Disposition: attachment;')
            ->toContain('filename=test-file-1.txt')
            ->toContain('filename=test-file-2.txt');

        return true;
    });
});

it('sends calendar attachments as json when mime_mode is never', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'mime_mode' => 'never',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->send(new TestMailWithCalendar);

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->isJson()->toBeTrue()
            ->body()->json()->toHaveKey('message.attachments.0.name', 'invite.ics');

        return true;
    });
});

it('throws exceptions when mime_mode is invalid', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'mime_mode' => 'sometimes',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(ConfigurationInvalid::class, (new ConfigurationInvalid('mime_mode', 'sometimes'))->getMessage());
});

it('sends html and text bodies given as stream resources', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);

    Cache::set('microsoft-graph-api-client-credentials-access-token', 'foo_access_token', 3600);

    Http::fake();

    $html = fopen('php://memory', 'r+');
    fwrite($html, '<b>Stream</b>');
    rewind($html);

    $email = (new Email)
        ->from('taylor@laravel.com')
        ->to('caleb@livewire.com')
        ->subject('Stream Test')
        ->html($html);

    Mail::mailer('microsoft-graph')->getSymfonyTransport()->send($email);

    Http::assertSent(function (Request $value) {
        expect($value->body())->json()->toMatchArray([
            'message' => [
                'subject' => 'Stream Test',
                'body' => [
                    'contentType' => 'HTML',
                    'content' => '<b>Stream</b>',
                ],
                'toRecipients' => [['emailAddress' => ['address' => 'caleb@livewire.com']]],
                'ccRecipients' => [],
                'bccRecipients' => [],
                'replyTo' => [],
                'sender' => ['emailAddress' => ['address' => 'taylor@laravel.com']],
                'attachments' => [],
            ],
            'saveToSentItems' => false,
        ]);

        return true;
    });

    Http::fake();

    $text = fopen('php://memory', 'r+');
    fwrite($text, 'Plain stream');
    rewind($text);

    $email = (new Email)
        ->from('taylor@laravel.com')
        ->to('caleb@livewire.com')
        ->subject('Stream Text Test')
        ->text($text);

    Mail::mailer('microsoft-graph')->getSymfonyTransport()->send($email);

    Http::assertSent(function (Request $value) {
        expect($value->body())->json()->toMatchArray([
            'message' => [
                'subject' => 'Stream Text Test',
                'body' => [
                    'contentType' => 'Text',
                    'content' => 'Plain stream',
                ],
                'toRecipients' => [['emailAddress' => ['address' => 'caleb@livewire.com']]],
                'ccRecipients' => [],
                'bccRecipients' => [],
                'replyTo' => [],
                'sender' => ['emailAddress' => ['address' => 'taylor@laravel.com']],
                'attachments' => [],
            ],
            'saveToSentItems' => false,
        ]);

        return true;
    });
});

it('rejects raw messages that cannot be converted to an email', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);

    Http::fake();

    $envelope = new Envelope(new Address('taylor@laravel.com'), [new Address('caleb@livewire.com')]);

    expect(fn () => Mail::mailer('microsoft-graph')->getSymfonyTransport()->send(new RawMessage('raw'), $envelope))
        ->toThrow(LogicException::class, RawMessage::class);

    Http::assertNothingSent();
});

it('throws an exception when auth_method is not a string', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'auth_method' => ['password'],
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(ConfigurationInvalid::class, 'auth_method');
});
