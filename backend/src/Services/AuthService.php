<?php

namespace App\Services;

use App\Services\Auth\CredentialProviderInterface;
use App\Services\Auth\PasswordCredentialProvider;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthService
{
    /** @var CredentialProviderInterface[] */
    private array $credentialProviders;
    private string $key;

    public function __construct()
    {
        $this->key = getenv('JWT_SECRET') ?: 'secret_key';

        // OIDC-ready extension point: add providers (e.g. google_oidc) without changing login/token core.
        $this->credentialProviders = [
            new PasswordCredentialProvider(),
        ];
    }

    public function login(string $email, string $password): ?string
    {
        $provider = $this->resolveProvider('password');
        if ($provider === null) {
            return null;
        }

        $user = $provider->authenticate([
            'email' => $email,
            'password' => $password,
        ]);
        if ($user === null) {
            return null;
        }

        $payload = [
            'iss' => 'parking_app',
            'aud' => 'parking_app',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + (60 * 60), // 1 hour
            'sub' => $user['id'],
            'email' => $user['email']
        ];

        return JWT::encode($payload, $this->key, 'HS256');
    }

    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));
            return (array)$decoded;
        }
        catch (Exception $e) {
            return null;
        }
    }

    private function resolveProvider(string $grantType): ?CredentialProviderInterface
    {
        foreach ($this->credentialProviders as $provider) {
            if ($provider->supports($grantType)) {
                return $provider;
            }
        }
        return null;
    }
}
