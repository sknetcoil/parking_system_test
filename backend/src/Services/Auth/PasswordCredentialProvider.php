<?php

namespace App\Services\Auth;

use App\Managers\UserManager;

class PasswordCredentialProvider implements CredentialProviderInterface
{
    private UserManager $userManager;

    public function __construct()
    {
        $this->userManager = new UserManager();
    }

    public function supports(string $grantType): bool
    {
        return $grantType === 'password';
    }

    public function authenticate(array $credentials): ?array
    {
        $email = $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;
        if (!$email || !$password) {
            return null;
        }

        $user = $this->userManager->findByEmail($email);
        if (!$user || !password_verify($password, $user->password)) {
            return null;
        }

        return [
            'id' => $user->id,
            'email' => $user->email,
        ];
    }
}
