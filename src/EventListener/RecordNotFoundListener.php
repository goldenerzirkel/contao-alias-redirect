<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\NotFoundLog;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Jede Anfrage, die mit 404 endet, kommt in die Arbeitsliste.
 *
 * An der ANTWORT und nicht an der Ausnahme, weil Contao 5 auf zwei Arten 404 liefert: mit einer
 * veroeffentlichten 404-Seite im Baum als gerenderte Seite (keine Ausnahme), sonst als
 * NotFoundHttpException. Die Antwort ist der einzige Punkt, an dem beide Wege zusammenlaufen.
 *
 * Nach den Weiterleitungen: wer per 301 weitergereicht wird, laeuft nicht ins Leere und gehoert nicht
 * in die Liste. Die Antwort ist dann kein 404 mehr, der Fall entfaellt hier von selbst.
 */
#[AsEventListener(event: 'kernel.response', priority: -64)]
final class RecordNotFoundListener
{
    public function __construct(
        private readonly NotFoundLog $log,
        private readonly ScopeMatcher $scope,
        private readonly Connection $db,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || 404 !== $event->getResponse()->getStatusCode()) {
            return;
        }
        $request = $event->getRequest();
        if ($this->scope->isBackendRequest($request) || !$this->log->gehoertHinein($request)) {
            return;
        }
        try {
            $this->log->erfasse(
                $request->getPathInfo(),
                $request->getHost(),
                $this->wurzel($request->getHost()),
                (string) $request->headers->get('referer', ''),
            );
        } catch (\Throwable) {
            // Das Protokoll darf nie die Antwort gefaehrden: ein Schreibfehler bleibt folgenlos.
        }
    }

    /** Wurzelseite des Hosts — nur zur Einordnung in der Liste, nicht fuer die Aufloesung. */
    private function wurzel(string $host): int
    {
        $id = $this->db->fetchOne("SELECT id FROM tl_page WHERE type = 'root' AND published = 1 AND dns = ? LIMIT 1", [$host]);

        return false === $id || null === $id ? 0 : (int) $id;
    }
}
