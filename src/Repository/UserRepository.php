<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    /**
     * IMPORTANT: whitelist of fields that the user-list page is allowed to
     * ORDER BY. NOTE: DQL uses entity field names (camelCase), not database
     * column names (snake_case) — the alias is `u` from createQueryBuilder.
     */
    public const SORTABLE_COLUMNS = [
        'id' => 'u.id',
        'name' => 'u.name',
        'email' => 'u.email',
        'status' => 'u.status',
        'lastLoginAt' => 'u.lastLoginAt',
        'createdAt' => 'u.createdAt',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * IMPORTANT: upgrades the password hash on the fly — invoked by the
     * security system on successful authentication.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * IMPORTANT: returns the user list with the requested sort applied.
     *
     * NOTE: when sorting by {@see User::$lastLoginAt}, NULL values (users
     * who have never logged in) are pushed to the BOTTOM — most recently
     * active accounts are visible at the top. We achieve this without
     * native NULLS LAST (MySQL doesn't support it) by sorting a CASE
     * expression first: NULL → 1, anything else → 0.
     *
     * @return User[]
     */
    public function findAllSorted(string $sortKey = 'lastLoginAt', string $direction = 'DESC'): array
    {
        $column = self::SORTABLE_COLUMNS[$sortKey] ?? self::SORTABLE_COLUMNS['lastLoginAt'];
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('u');

        // IMPORTANT: only push NULLs down when the user actually chose to
        // sort by lastLoginAt. Sorting other columns keeps normal semantics.
        if ($sortKey === 'lastLoginAt') {
            $qb->addOrderBy('CASE WHEN u.lastLoginAt IS NULL THEN 1 ELSE 0 END', 'ASC');
        }

        $qb->addOrderBy($column, $dir)
            // NOTE: tie-breaker for stable ordering across pages.
            ->addOrderBy('u.id', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * IMPORTANT: looks up the user that matches the verification token
     * sent by e-mail. Returns null if the token is unknown.
     */
    public function findByVerificationToken(string $token): ?User
    {
        return $this->findOneBy(['emailVerificationToken' => $token]);
    }

    /**
     * IMPORTANT: convenience for the "Delete unverified" toolbar action.
     *
     * @return User[]
     */
    public function findUnverified(): array
    {
        return $this->findBy(['status' => UserStatus::Unverified]);
    }
}