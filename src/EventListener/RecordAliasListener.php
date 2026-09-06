<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\EventListener;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;

/**
 * NACHRICHTEN UND TERMINE: dieselbe Mechanik wie an der Seite (PageAliasListener), an tl_news und
 * tl_calendar_events. Ändert ein Redakteur den Alias einer Nachricht, wandert der alte in die Liste am
 * Datensatz; die alte Adresse „/blog/alter-alias" leitet auf die neue Nachricht. Kai, 06.09.2026:
 * „redirection für news und events ergänzen".
 */
class RecordAliasListener
{
    /** @var array<string, array<int,string>> Alias je Datensatz beim Laden — für „Version wiederherstellen". */
    private array $aliasVorher = [];

    public function __construct(
        private readonly AliasRedirects $redirects,
        private readonly Connection $db,
        private readonly AliasIndex $index,
    ) {
    }

    #[AsCallback(table: 'tl_news', target: 'config.onload')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload')]
    public function felderEinhaengen(?DataContainer $dc = null): void
    {
        if (null === $dc) {
            return;
        }
        $table = $dc->table;
        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] ?? [] as $name => $palette) {
            if ('__selector__' === $name || !\is_string($palette) || !preg_match('/\balias\b/', $palette) || str_contains($palette, AliasRedirects::FELD)) {
                continue;
            }
            PaletteManipulator::create()
                ->addField(['gozi_noRedirect', AliasRedirects::FELD], 'alias', PaletteManipulator::POSITION_AFTER)
                ->applyToPalette($name, $table);
        }
        if ($dc->id) {
            $alias = $this->db->fetchOne('SELECT alias FROM '.$table.' WHERE id = ?', [(int) $dc->id]);
            if (\is_string($alias)) {
                $this->aliasVorher[$table][(int) $dc->id] = $alias;
            }
        }
    }

    /** Nach Contaos eigener Alias-Erzeugung (Priorität 0): der alte Alias wandert über das Formular in die Liste. */
    #[AsCallback(table: 'tl_news', target: 'fields.alias.save', priority: -10)]
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.alias.save', priority: -10)]
    public function beiAliasWechsel(mixed $value, DataContainer $dc): mixed
    {
        $aktuell = $dc->getCurrentRecord();
        $alt = (string) ($aktuell['alias'] ?? '');
        $neu = (string) $value;
        if ('' !== $alt && $alt !== $neu && !Input::post('gozi_noRedirect')) {
            $liste = Input::post(AliasRedirects::FELD) ?? ($aktuell[AliasRedirects::FELD] ?? null);
            Input::setPost(AliasRedirects::FELD, $this->redirects->mitAltemAlias($liste, $alt, $neu));
        }

        return $value;
    }

    #[AsCallback(table: 'tl_news', target: 'fields.gozi_redirects.save')]
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.gozi_redirects.save')]
    public function listeBereinigen(mixed $value, DataContainer $dc): mixed
    {
        $aktuellerAlias = (string) (Input::post('alias') ?? ($dc->getCurrentRecord()['alias'] ?? ''));
        $liste = $this->redirects->bereinige($value, $aktuellerAlias);

        return [] === $liste ? null : serialize($liste);
    }

    #[AsCallback(table: 'tl_news', target: 'config.onsubmit', priority: -32)]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -32)]
    public function nachSpeichern(DataContainer $dc): void
    {
        if ($dc->id) {
            $this->db->update($dc->table, ['gozi_noRedirect' => 0], ['id' => (int) $dc->id]);
            $this->index->neu($dc->table, (int) $dc->id);
        }
    }

    #[AsCallback(table: 'tl_news', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.ondelete')]
    public function beiLoeschen(DataContainer $dc): void
    {
        if ($dc->id) {
            $this->index->weg($dc->table, (int) $dc->id);
        }
    }

    /** @param array<string,mixed> $data */
    #[AsCallback(table: 'tl_news', target: 'config.onrestore_version')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onrestore_version')]
    public function beiWiederherstellung(string $table, int $pid, int $version, array $data): void
    {
        $vorher = $this->aliasVorher[$table][$pid] ?? '';
        $neu = (string) ($data['alias'] ?? '');
        if ('' !== $vorher && '' !== $neu && $vorher !== $neu) {
            $liste = $this->redirects->mitAltemAlias($data[AliasRedirects::FELD] ?? null, $vorher, $neu);
            $this->db->update($table, [AliasRedirects::FELD => serialize($liste), 'tstamp' => time()], ['id' => $pid]);
            try {
                (new Versions($table, $pid))->create(true);
            } catch (\Throwable) {
            }
        }
        $this->index->neu($table, $pid);
    }
}
