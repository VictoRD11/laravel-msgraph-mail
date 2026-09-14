<?php

namespace VictoRD11\LaravelMsGraphMail;

use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use VictoRD11\LaravelMsGraphMail\Contracts\TokenProviderInterface;
use VictoRD11\LaravelMsGraphMail\Exceptions\ConfigurationInvalid;
use VictoRD11\LaravelMsGraphMail\Exceptions\ConfigurationMissing;
use VictoRD11\LaravelMsGraphMail\Providers\ClientCredentialsTokenProvider;
use VictoRD11\LaravelMsGraphMail\Providers\PasswordTokenProvider;
use VictoRD11\LaravelMsGraphMail\Services\MicrosoftGraphApiService;

class LaravelMsGraphMailServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-msgraph-mail');
    }

    public function boot(): void
    {
        Mail::extend('microsoft-graph', function (array $config): MicrosoftGraphTransport {
            throw_if(blank($config['from']['address'] ?? []), new ConfigurationMissing('from.address'));

            $accessTokenTtl = $config['access_token_ttl'] ?? 3000;
            if (! is_int($accessTokenTtl)) {
                throw new ConfigurationInvalid('access_token_ttl', $accessTokenTtl);
            }

            $mimeMode = $config['mime_mode'] ?? MicrosoftGraphTransport::MIME_MODE_AUTO;
            if (! is_string($mimeMode) || ! in_array($mimeMode, MicrosoftGraphTransport::MIME_MODES, true)) {
                throw new ConfigurationInvalid('mime_mode', $mimeMode);
            }

            $authMethod = $config['auth_method'] ?? 'client_credentials';
            $tokenProvider = $this->createTokenProvider($config, $authMethod, $accessTokenTtl);

            return new MicrosoftGraphTransport(
                new MicrosoftGraphApiService($tokenProvider),
                mimeMode: $mimeMode,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createTokenProvider(array $config, string $authMethod, int $accessTokenTtl): TokenProviderInterface
    {
        $tenantId = $this->requireConfigString($config, 'tenant_id');
        $clientId = $this->requireConfigString($config, 'client_id');
        $clientSecret = $this->requireConfigString($config, 'client_secret');

        return match ($authMethod) {
            'client_credentials' => new ClientCredentialsTokenProvider(
                tenantId: $tenantId,
                clientId: $clientId,
                clientSecret: $clientSecret,
                accessTokenTtl: $accessTokenTtl,
            ),
            'password' => new PasswordTokenProvider(
                tenantId: $tenantId,
                clientId: $clientId,
                clientSecret: $clientSecret,
                username: $this->requireConfigString($config, 'username'),
                password: $this->requireConfigString($config, 'password'),
                accessTokenTtl: $accessTokenTtl,
            ),
            default => throw new ConfigurationInvalid('auth_method', $authMethod),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return non-empty-string
     */
    protected function requireConfigString(array $config, string $key): string
    {
        if (! array_key_exists($key, $config)) {
            throw new ConfigurationMissing($key);
        }

        $value = $config[$key];
        if (! is_string($value) || $value === '') {
            throw new ConfigurationInvalid($key, $value);
        }

        return $value;
    }
}
