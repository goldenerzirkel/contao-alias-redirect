<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Controller\Page;

use Contao\CoreBundle\Controller\Page\AbstractPageController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsPage;
use Contao\CoreBundle\Routing\Page\ContentCompositionInterface;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEITENTYP „ENTFERNT (410 GONE)".
 *
 * Eine bewusst entfernte Seite bleibt als Seite im Baum — mit ihrem Alias und ihrer Liste alter Aliase —
 * und antwortet mit 410 statt 404. Suchmaschinen streichen die Adresse dann schneller, Besucher sehen
 * eine echte Seite im Layout (Artikel erlaubt: „Dieses Angebot gibt es nicht mehr, hier geht es weiter").
 * Kai, 06.09.2026: „410 … gute idee, vielleicht auch als seitentyp?"
 */
#[AsPage(type: 'gozi_gone')]
class GonePageController extends AbstractPageController implements ContentCompositionInterface
{
    public function __invoke(PageModel $pageModel): Response
    {
        return $this->renderPage($pageModel)->setStatusCode(Response::HTTP_GONE);
    }

    public function supportsContentComposition(PageModel $pageModel): bool
    {
        return true;
    }

    protected function setCacheHeaders(Response $response, PageModel $pageModel): Response
    {
        // 410 darf der Cache eine Weile behalten — die Antwort ändert sich nicht, bis die Seite gelöscht wird.
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
