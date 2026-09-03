<?php
declare(strict_types=1);

final class AuthService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function authenticate(string $email, string $password): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, first_name, last_name, email, password_hash
             FROM users WHERE email = :email AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['email' => mb_strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Reduce diferencias temporales entre usuarios existentes e inexistentes.
            if (!$user) {
                password_verify($password, '$2y$10$RKitxK.JjEvowDG4ts/cLueVbQ5JEUVOXlBDOFaFZdV5nAWEzeU7e');
            }
            return null;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);
        unset($user['password_hash']);
        return $user;
    }

    public function register(string $firstName, string $lastName, string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'INSERT INTO users (first_name, last_name, email, password_hash, email_verified_at)
                 VALUES (:first_name, :last_name, :email, :password_hash, NOW())'
            );
            $statement->execute([
                'first_name' => trim($firstName),
                'last_name' => trim($lastName) ?: null,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $this->db->lastInsertId();
            $this->db->prepare('INSERT INTO user_preferences (user_id) VALUES (:user_id)')
                ->execute(['user_id' => $userId]);
            $this->db->commit();
            return ['id' => $userId, 'first_name' => trim($firstName), 'last_name' => trim($lastName), 'email' => $email];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function createPasswordReset(string $email): ?string
    {
        $statement = $this->db->prepare('SELECT id FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
        $statement->execute(['email' => mb_strtolower(trim($email))]);
        $userId = $statement->fetchColumn();
        if (!$userId) {
            return null;
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $this->db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id OR expires_at < NOW()')
            ->execute(['user_id' => $userId]);
        $this->db->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        )->execute(['user_id' => $userId, 'token_hash' => $tokenHash]);
        return $plainToken;
    }

    public function resetPassword(string $plainToken, string $newPassword): bool
    {
        $tokenHash = hash('sha256', $plainToken);
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'SELECT id, user_id FROM password_reset_tokens
                 WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['token_hash' => $tokenHash]);
            $token = $statement->fetch();
            if (!$token) {
                $this->db->rollBack();
                return false;
            }
            $this->updatePasswordHash((int) $token['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
            $this->db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id')
                ->execute(['id' => $token['id']]);
            $this->db->prepare('DELETE FROM user_sessions WHERE user_id = :user_id')
                ->execute(['user_id' => $token['user_id']]);
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function updatePasswordHash(int $userId, string $hash): void
    {
        $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => $hash, 'id' => $userId]);
    }
}
