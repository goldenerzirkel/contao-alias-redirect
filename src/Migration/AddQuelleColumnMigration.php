<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;

/** Index um die Quelle (Seite, Nachricht, Termin) erweitern und neu füllen. */
class AddQuelleColumnMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function shouldRun(): bool
    {
        $sm = $this->db->createSchemaManager();

        return $sm->tablesExist([AliasIndex::TABELLE]) && !isset($sm->listTableColumns(AliasIndex::TABELLE)['quelle']);
    }

    public function run(): MigrationResult
    {
        $this->db->executeStatement('ALTER TABLE '.AliasIndex::TABELLE." ADD quelle VARCHAR(32) NOT NULL DEFAULT 'tl_page', ADD INDEX quelle_pid (quelle, pid)");
        $stand = (new AliasIndex($this->db, new AliasRedirects($this->db)))->neuAufbauen();

        return $this->createResult(true, sprintf('gozi-alias-redirect: Index um Quelle erweitert, %d Einträge aus %d Datensätzen', $stand['eintraege'], $stand['seiten']));
    }
}
