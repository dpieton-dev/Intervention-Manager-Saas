<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528185750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_presence (id INT AUTO_INCREMENT NOT NULL, last_seen_at DATETIME NOT NULL, user_id INT NOT NULL, project_id INT NOT NULL, INDEX IDX_54394C7EA76ED395 (user_id), INDEX IDX_54394C7E166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE project_presence ADD CONSTRAINT FK_54394C7EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE project_presence ADD CONSTRAINT FK_54394C7E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_presence DROP FOREIGN KEY FK_54394C7EA76ED395');
        $this->addSql('ALTER TABLE project_presence DROP FOREIGN KEY FK_54394C7E166D1F9C');
        $this->addSql('DROP TABLE project_presence');
    }
}
