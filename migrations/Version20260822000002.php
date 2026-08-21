<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMPORTANT: Adds the previous_status column used to restore the user's
 * pre-block status on "Unblock" (so unverified→blocked→unblocked stays
 * unverified, not active).
 *
 * NOTE: existing rows get NULL, which the controller treats as "no
 * pre-block status known" → falls back to Active on unblock.
 */
final class Version20260822000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add previous_status column to user table to preserve pre-block status.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD previous_status VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP previous_status');
    }
}