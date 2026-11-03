<?php
declare(strict_types=1);

/**
 * Admin authentication: password login through Supabase Auth, then a second
 * check that the account is listed in the pra_admins allow list.
 */
class Auth
{
    private Supabase $db;

    public function __construct(Supabase $db)
    {
        $this->db = $db;
    }

    public function attempt(string $email, string $password): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return 'Enter your email and password.';
        }

        try {
            $this->db->request(
                'POST',
                '/auth/v1/token?grant_type=password',
                json_encode(['email' => $email, 'password' => $password]),
                false,
                ['Content-Type: application/json']
            );
        } catch (SupabaseException $e) {
            return 'Sign in failed. Check your email and password.';
        }

        if (!$this->isAdmin($email)) {
            return 'This account is not on the archive admin list.';
        }

        session_regenerate_id(true);
        $_SESSION['pra_admin'] = ['email' => $email, 'since' => time()];

        return null;
    }

    public function isAdmin(string $email): bool
    {
        if ($email === '' || !$this->db->hasServiceKey()) {
            return false;
        }
        try {
            $result = $this->db->select(
                'pra_admins',
                'select=email&email=eq.' . rawurlencode(strtolower($email)) . '&is_active=eq.true&limit=1',
                true
            );
        } catch (SupabaseException $e) {
            return false;
        }
        return count($result['rows']) > 0;
    }

    /** @return array{email: string, since: int}|null */
    public function user(): ?array
    {
        if (!isset($_SESSION['pra_admin']) || !is_array($_SESSION['pra_admin'])) {
            return null;
        }
        $admin = $_SESSION['pra_admin'];
        if (!isset($admin['email']) || !is_string($admin['email'])) {
            return null;
        }
        return ['email' => $admin['email'], 'since' => (int) ($admin['since'] ?? 0)];
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function requireLogin(string $loginUrl = 'login.php'): void
    {
        if (!$this->check()) {
            redirect($loginUrl);
        }
    }

    public function logout(): void
    {
        unset($_SESSION['pra_admin']);
        session_regenerate_id(true);
    }
}
