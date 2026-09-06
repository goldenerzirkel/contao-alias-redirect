<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Gozi\AliasRedirectBundle\GoziAliasRedirectBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [BundleConfig::create(GoziAliasRedirectBundle::class)->setLoadAfter([
                    ContaoCoreBundle::class,
                    // Die DCA-Dateien dieser Bundles setzen tl_news bzw. tl_calendar_events als GANZES Array —
                    // wer davor lädt, verliert seine Felder (gozi_noRedirect fehlte im News-Formular, 06.09.2026).
                    'Contao\\NewsBundle\\ContaoNewsBundle',
                    'Contao\\CalendarBundle\\ContaoCalendarBundle',
                ])];
    }
}
