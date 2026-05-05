<?php

require_once __DIR__ . '/../Core/Database.php';

class User
{
    public int $id;
    public string $name;
    public string $email;
    public string $role;
    public string $passwordHash;

    public function __construct(array $data)
    {
        $this->id = (int)($data['id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->role = $data['role'] ?? '';
        $this->passwordHash = $data['password_hash'] ?? '';
    }

    public static function findByEmail(string $email): ?self
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new self($row) : null;
    }

    public static function create(string $name, string $email, string $password, string $role): int
    {
        $pdo = Database::getConnection();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hash, $role]);
        return (int)$pdo->lastInsertId();
    }

    public static function emailExists(string $email): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (bool)$stmt->fetchColumn();
    }
}
