<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: check_in and recommendation tables.
 */
final class Version20260524184723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create check_in and recommendation tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE check_in (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, energy_level INTEGER NOT NULL, focus_goal VARCHAR(255) NOT NULL, distraction_risk VARCHAR(255) NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE recommendation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, priority VARCHAR(50) NOT NULL, risk_level VARCHAR(50) NOT NULL, next_action VARCHAR(255) NOT NULL, reasoning CLOB NOT NULL, created_at DATETIME NOT NULL, check_in_id INTEGER NOT NULL, CONSTRAINT FK_433224D28AA34DF1 FOREIGN KEY (check_in_id) REFERENCES check_in (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_433224D28AA34DF1 ON recommendation (check_in_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE recommendation');
        $this->addSql('DROP TABLE check_in');
        $this->addSql('DROP TABLE recommendation');
    }
}
