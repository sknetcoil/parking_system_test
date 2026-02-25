<?php

namespace App\Services\Auth;

interface CredentialProviderInterface
{
    public function supports(string $grantType): bool;

    public function authenticate(array $credentials): ?array;
}
