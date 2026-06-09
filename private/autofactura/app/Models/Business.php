<?php
/**
 * Modelo: Business (Negocio)
 */

class Business extends BaseModel
{
    protected static string $table = 'businesses';

    protected static array $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'stamp_credits',
        'email_verification_token',
        'email_verification_sent_at',
        'email_verified_at',
        'role',
        'is_active',
    ];

    /**
     * Buscar negocio por email
     */
    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', $email);
    }

    public static function findByVerificationToken(string $token): ?array
    {
        return self::findBy('email_verification_token', $token);
    }

    public static function findActiveSuperuser(): ?array
    {
        return Database::fetchOne(
            "SELECT *
             FROM businesses
             WHERE role = 'superuser' AND is_active = 1
             ORDER BY id ASC
             LIMIT 1"
        );
    }

    /**
     * Verificar contraseña
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Hash de contraseña
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Crear usuario/negocio SaaS
     */
    public static function register(array $data): int
    {
        $payload = [
            'name'      => trim($data['name'] ?? ''),
            'email'     => strtolower(trim($data['email'] ?? '')),
            'password'  => self::hashPassword($data['password'] ?? ''),
            'phone'     => trim($data['phone'] ?? '') ?: null,
            'stamp_credits' => max(0, (int) ($data['stamp_credits'] ?? 0)),
            'email_verification_token' => $data['email_verification_token'] ?? self::generateToken(),
            'email_verification_sent_at' => $data['email_verification_sent_at'] ?? date('Y-m-d H:i:s'),
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'role'      => in_array(($data['role'] ?? 'user'), ['user', 'superuser'], true) ? $data['role'] : 'user',
            'is_active' => (int) ($data['is_active'] ?? 1),
        ];

        return self::create($payload);
    }

    /**
     * Listado de usuarios para administración (sin password)
     */
    public static function allForAdmin(): array
    {
        return Database::fetchAll(
            "SELECT id, name, email, phone, stamp_credits, role, is_active, created_at, updated_at
             FROM businesses
             ORDER BY created_at DESC"
        );
    }

    public static function searchTransferTargets(string $term = '', int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $term = trim($term);

        $params = [];
        $where = "WHERE role = 'user' AND is_active = 1";

        if ($term !== '') {
            $where .= " AND (`name` LIKE :term_name OR `email` LIKE :term_email)";
            $params['term_name'] = '%' . $term . '%';
            $params['term_email'] = '%' . $term . '%';
        }

        return Database::fetchAll(
            "SELECT id, name, email, stamp_credits
             FROM businesses
             {$where}
             ORDER BY name ASC
             LIMIT {$limit}",
            $params
        );
    }

    public static function getStampCredits(int $businessId): int
    {
        $business = self::find($businessId);
        return max(0, (int) ($business['stamp_credits'] ?? 0));
    }

    public static function hasStampCredits(int $businessId): bool
    {
        return self::getStampCredits($businessId) > 0;
    }

    public static function isEmailVerified(array $business): bool
    {
        return !empty($business['email_verified_at']);
    }

    public static function markEmailVerified(int $businessId): bool
    {
        return self::update($businessId, [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verification_token' => null,
        ]);
    }

    public static function refreshEmailVerificationToken(int $businessId): ?string
    {
        $token = self::generateToken();
        $updated = self::update($businessId, [
            'email_verification_token' => $token,
            'email_verification_sent_at' => date('Y-m-d H:i:s'),
        ]);

        return $updated ? $token : null;
    }

    public static function canResendVerificationEmail(array $business, int $cooldownSeconds = 300): bool
    {
        if (self::isEmailVerified($business)) {
            return false;
        }

        $sentAt = $business['email_verification_sent_at'] ?? null;
        if (empty($sentAt)) {
            return true;
        }

        return (strtotime((string) $sentAt) ?: 0) <= (time() - $cooldownSeconds);
    }

    public static function addStampCredits(int $businessId, int $credits): bool
    {
        if ($credits <= 0) {
            return false;
        }

        $sql = "UPDATE `businesses`
                SET `stamp_credits` = `stamp_credits` + :credits
                WHERE `id` = :id";

        return Database::execute($sql, [
            'credits' => $credits,
            'id' => $businessId,
        ]) > 0;
    }

    public static function setStampCredits(int $businessId, int $credits): bool
    {
        if ($credits < 0) {
            $credits = 0;
        }

        $sql = "UPDATE `businesses`
                SET `stamp_credits` = :credits
                WHERE `id` = :id";

        return Database::execute($sql, [
            'credits' => $credits,
            'id' => $businessId,
        ]) > 0;
    }

    public static function transferStampCredits(int $fromBusinessId, int $toBusinessId, int $credits): bool
    {
        if ($credits <= 0) {
            return false;
        }

        if ($fromBusinessId === $toBusinessId) {
            return true;
        }

        $pdo = Database::getInstance();
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            Database::beginTransaction();
        }

        try {
            $consumed = self::consumeStampCredit($fromBusinessId, $credits);
            if (!$consumed) {
                throw new RuntimeException('El superusuario no tiene timbres suficientes para surtir esta compra.');
            }

            $added = self::addStampCredits($toBusinessId, $credits);
            if (!$added) {
                throw new RuntimeException('No se pudieron acreditar los timbres al negocio comprador.');
            }

            if ($ownsTransaction) {
                Database::commit();
            }
            return true;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                Database::rollBack();
            }
            throw $e;
        }
    }

    public static function consumeStampCredit(int $businessId, int $quantity = 1): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $sql = "UPDATE `businesses`
                SET `stamp_credits` = `stamp_credits` - :quantity_to_subtract
                WHERE `id` = :id
                  AND `stamp_credits` >= :quantity_required";

        return Database::execute($sql, [
            'quantity_to_subtract' => $quantity,
            'quantity_required' => $quantity,
            'id' => $businessId,
        ]) > 0;
    }
}
