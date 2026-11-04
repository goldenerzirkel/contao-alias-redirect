<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\NotFoundLog;

/**
 * Spalte „endung" in der 404-Liste und Nachtragen für die vorhandenen Einträge.
 *
 * Nachgetragen wird aus dem gespeicherten Pfad. Der ist ohne .html abgelegt — .html-Adressen bekommen
 * deshalb keine Endung, .php, .asp, .env usw. schon. Neue Einträge erfasst NotFoundLog aus dem rohen Pfad.
 */
class AddEndungColumnMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function shouldRun(): bool
    {
        $sm = $this->db->createSchemaManager();

        return $sm->tablesExist([NotFoundLog::TABELLE])
            && !isset($sm->listTableColumns(NotFoundLog::TABELLE)['endung']);
    }

    public function run(): MigrationResult
    {
        $this->db->executeStatement('ALTER TABLE '.NotFoundLog::TABELLE." ADD endung VARCHAR(16) DEFAULT '' NOT NULL");
        $this->db->executeStatement('CREATE INDEX endung ON '.NotFoundLog::TABELLE.' (endung)');

        $nachgetragen = 0;

        foreach ($this->db->fetchAllKeyValue('SELECT id, pfad FROM '.NotFoundLog::TABELLE) as $id => $pfad) {
            if ('' !== ($endung = NotFoundLog::endung((string) $pfad))) {
                $this->db->update(NotFoundLog::TABELLE, ['endung' => $endung], ['id' => (int) $id]);
                ++$nachgetragen;
            }
        }

        return $this->createResult(true, \sprintf('gozi-alias-redirect: Spalte endung angelegt, bei %d Einträgen nachgetragen', $nachgetragen));
    }
}
