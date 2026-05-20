<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260520191107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_member ADD project_role_id INT NOT NULL');
        $this->addSql('ALTER TABLE project_member ADD CONSTRAINT FK_67401132401D2EC9 FOREIGN KEY (project_role_id) REFERENCES project_role (id)');
        $this->addSql('CREATE INDEX IDX_67401132401D2EC9 ON project_member (project_role_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_member DROP FOREIGN KEY FK_67401132401D2EC9');
        $this->addSql('DROP INDEX IDX_67401132401D2EC9 ON project_member');
        $this->addSql('ALTER TABLE project_member DROP project_role_id');
    }
}
