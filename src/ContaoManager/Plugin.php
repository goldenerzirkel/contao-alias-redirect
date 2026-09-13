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
                    'Contao\\FaqBundle\\ContaoFaqBundle',
                    // Das Feld gozi_redirects haengt sich an tl_page_i18nl10n; die Tabelle wird von
                    // gozi-i18nl10n deklariert. Fehlt das Bundle, tut die DCA-Datei nichts.
                    'GoZi\\I18nl10nBundle\\GoZiI18nl10nBundle',
                ])];
    }
}
