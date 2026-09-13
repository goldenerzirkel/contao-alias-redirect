<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Gozi\AliasRedirectBundle\Service\NotFoundLog;

/**
 * Die Arbeitsliste bleibt ohne Pflege nicht klein: ein einziger Scanner legt an einem Tag Tausende
 * Adressen an, die nie wieder gefragt werden.
 *
 * Geloescht wird nur, was seit 90 Tagen nicht mehr aufgelaufen ist UND nicht erledigt ist. Erledigte
 * Eintraege bleiben: an ihnen haengt die Zuordnung zu einer Weiterleitung, und sie zeigen, ob eine
 * Adresse trotz Weiterleitung wieder auflaeuft.
 */
#[AsCronJob('daily')]
final class CleanNotFoundLogCron
{
    public const TAGE = 90;

    public function __construct(private readonly NotFoundLog $log)
    {
    }

    public function __invoke(): void
    {
        $this->log->aufraeumen(self::TAGE);
    }
}
