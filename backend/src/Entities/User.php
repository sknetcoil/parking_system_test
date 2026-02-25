<?php

namespace App\Entities;

class User
{
    public function __construct(
        public ?int $id,
        public string $email,
        public string $password
        )
    {
    }
}