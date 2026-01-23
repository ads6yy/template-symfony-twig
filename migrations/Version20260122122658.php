<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260122122658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adding account status on User entity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD account_status VARCHAR(255) NOT NULL, DROP is_active');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD is_active TINYINT NOT NULL, DROP account_status');
    }
}
