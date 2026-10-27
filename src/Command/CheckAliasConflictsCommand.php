<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Command;

use Gozi\AliasRedirectBundle\Service\AliasBelegung;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Bestandsaufnahme: jede Adresse, die in einem Baum mehr als eine Seite meint — mit allen Fundstellen.
 *
 * Die Pruefungen beim Speichern verhindern neue Konflikte; was schon in der Datenbank steht (Kopien von
 * vor dem 27.10.2026, Daten aus der Contao-4-Zeit), findet nur dieser Befehl. Rueckgabe 1 bei Konflikten,
 * damit er sich auch in ein Deploy-Skript haengen laesst.
 */
#[AsCommand(name: 'gozi:alias-redirect:pruefen', description: 'Doppelt verwendete Adressen auflisten (Seitenpfade und Weiterleitungen je Baum und Sprache).')]
class CheckAliasConflictsCommand extends Command
{
    public function __construct(private readonly AliasBelegung $belegung)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $konflikte = $this->belegung->konflikte();
        if ([] === $konflikte) {
            $output->writeln('Keine doppelt verwendeten Adressen.');

            return Command::SUCCESS;
        }
        foreach ($konflikte as $k) {
            $output->writeln(sprintf('<comment>Wurzel %d</comment>  %s', $k['wurzel'], $k['alias']));
            foreach ($k['verwendungen'] as $v) {
                $output->writeln('    '.$this->belegung->beschreibe([$v]));
            }
        }
        $output->writeln(sprintf('%d doppelt verwendete Adressen.', \count($konflikte)));

        return Command::FAILURE;
    }
}
