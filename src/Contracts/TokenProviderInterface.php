<?php

namespace InnoGE\LaravelMsGraphMail\Contracts;

interface TokenProviderInterface
{
    public function getAccessToken(): string;
}