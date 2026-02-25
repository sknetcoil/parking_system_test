<?php

namespace App\Managers;

use App\Core\Database;
use App\Entities\User;
use PDO;

class UserManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return new User($data['id'], $data['email'], $data['password']);
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return new User($data['id'], $data['email'], $data['password']);
    }
}