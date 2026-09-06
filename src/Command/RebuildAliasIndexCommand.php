<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Command;

use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gozi:alias-redirect:rebuild', description: 'Index alter Aliase aus den Seiten neu aufbauen (tl_gozi_alias_redirect).')]
class RebuildAliasIndexCommand extends Command
{
    public function __construct(private readonly AliasIndex $index)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->index->vorhanden()) {
            $output->writeln('<error>Tabelle tl_gozi_alias_redirect fehlt — erst contao:migrate ausführen.</error>');

            return Command::FAILURE;
        }
        $stand = $this->index->neuAufbauen();
        $output->writeln(sprintf('Index neu aufgebaut: %d Einträge aus %d Seiten.', $stand['eintraege'], $stand['seiten']));

        return Command::SUCCESS;
    }
}
