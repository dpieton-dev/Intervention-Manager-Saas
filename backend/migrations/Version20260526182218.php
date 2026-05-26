<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260526182218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ticket_attachment (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_EFCC4BEF700047D2 (ticket_id), INDEX IDX_EFCC4BEFB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE ticket_attachment ADD CONSTRAINT FK_EFCC4BEF700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
        $this->addSql('ALTER TABLE ticket_attachment ADD CONSTRAINT FK_EFCC4BEFB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket_attachment DROP FOREIGN KEY FK_EFCC4BEF700047D2');
        $this->addSql('ALTER TABLE ticket_attachment DROP FOREIGN KEY FK_EFCC4BEFB03A8386');
        $this->addSql('DROP TABLE ticket_attachment');
    }
}
