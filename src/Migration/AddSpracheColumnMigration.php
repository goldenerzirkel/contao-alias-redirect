<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;

/**
 * Spalte „sprache" im Index und einmaliger Neuaufbau.
 *
 * Ohne den Neuaufbau blieben die Uebersetzungen aussen vor: contao:migrate legt zwar die Spalte an,
 * fuellt aber keine Zeilen. Erst der Neuaufbau nimmt tl_page_i18nl10n als Quelle mit auf.
 */
class AddSpracheColumnMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function shouldRun(): bool
    {
        $sm = $this->db->createSchemaManager();

        return $sm->tablesExist([AliasIndex::TABELLE])
            && !isset($sm->listTableColumns(AliasIndex::TABELLE)['sprache']);
    }

    public function run(): MigrationResult
    {
        $this->db->executeStatement('ALTER TABLE '.AliasIndex::TABELLE." ADD sprache VARCHAR(5) DEFAULT '' NOT NULL");
        $index = new AliasIndex($this->db, new AliasRedirects($this->db));
        $stand = $index->neuAufbauen();

        return $this->createResult(true, sprintf('gozi-alias-redirect: Spalte sprache angelegt, Index neu aufgebaut (%d Eintraege aus %d Datensaetzen)', $stand['eintraege'], $stand['seiten']));
    }
}
