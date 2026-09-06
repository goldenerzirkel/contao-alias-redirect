<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;

/** Indextabelle anlegen und einmal aus den Seiten füllen. */
class CreateAliasIndexMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function shouldRun(): bool
    {
        return $this->db->createSchemaManager()->tablesExist(['tl_page'])
            && !$this->db->createSchemaManager()->tablesExist([AliasIndex::TABELLE]);
    }

    public function run(): MigrationResult
    {
        $this->db->executeStatement(<<<'SQL'
            CREATE TABLE tl_gozi_alias_redirect (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                tstamp INT UNSIGNED DEFAULT 0 NOT NULL,
                alias VARCHAR(255) NOT NULL DEFAULT '' COLLATE `utf8mb4_bin`,
                root INT UNSIGNED DEFAULT 0 NOT NULL,
                pid INT UNSIGNED DEFAULT 0 NOT NULL,
                gone TINYINT(1) DEFAULT 0 NOT NULL,
                INDEX alias (alias),
                INDEX pid (pid),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC
            SQL);
        $index = new AliasIndex($this->db, new AliasRedirects($this->db));
        $stand = $index->neuAufbauen();

        return $this->createResult(true, sprintf('gozi-alias-redirect: Index angelegt, %d Einträge aus %d Seiten', $stand['eintraege'], $stand['seiten']));
    }
}
