<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260527121900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(164) NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_64C19C1727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, author_id INT NOT NULL, topic_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_9474526CF675F31B (author_id), INDEX IDX_9474526C1F55203D (topic_id), INDEX IDX_9474526C727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE favorite (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, topic_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_68C58ED91F55203D (topic_id), INDEX IDX_68C58ED9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE topic (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, author_id INT NOT NULL, category_id INT NOT NULL, picture VARCHAR(255) DEFAULT NULL, INDEX IDX_9D40DE1BF675F31B (author_id), INDEX IDX_9D40DE1B12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nickname VARCHAR(164) NOT NULL, picture VARCHAR(255) DEFAULT NULL, birth_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE vote (id INT AUTO_INCREMENT NOT NULL, value SMALLINT NOT NULL, created_at DATETIME NOT NULL, author_id INT NOT NULL, comment_id INT NOT NULL, INDEX IDX_5A108564F675F31B (author_id), INDEX IDX_5A108564F8697D13 (comment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C1F55203D FOREIGN KEY (topic_id) REFERENCES topic (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED91F55203D FOREIGN KEY (topic_id) REFERENCES topic (id)');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1BF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1B12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C1F55203D');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C727ACA70');
        $this->addSql('ALTER TABLE favorite DROP FOREIGN KEY FK_68C58ED91F55203D');
        $this->addSql('ALTER TABLE favorite DROP FOREIGN KEY FK_68C58ED9A76ED395');
        $this->addSql('ALTER TABLE topic DROP FOREIGN KEY FK_9D40DE1BF675F31B');
        $this->addSql('ALTER TABLE topic DROP FOREIGN KEY FK_9D40DE1B12469DE2');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564F675F31B');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564F8697D13');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE favorite');
        $this->addSql('DROP TABLE topic');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE vote');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
