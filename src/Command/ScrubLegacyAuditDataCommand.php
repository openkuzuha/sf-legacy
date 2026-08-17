<?php

namespace App\Command;

use App\Audit\LegacyAuditScrubber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bbs:audit:scrub-legacy', description: '既存投稿から生IPアドレスとUser-Agentを消去します。')]
final class ScrubLegacyAuditDataCommand extends Command
{
    public function __construct(private readonly LegacyAuditScrubber $scrubber)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, '実際にデータを書き換える')
            ->addOption('backup-confirmed', null, InputOption::VALUE_NONE, 'バックアップを確認済みとして実行する');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apply = (bool) $input->getOption('apply');
        if ($apply && !(bool) $input->getOption('backup-confirmed')) {
            $output->writeln('<error>実行には --apply と --backup-confirmed の両方が必要です。</error>');

            return Command::INVALID;
        }
        $result = $this->scrubber->run($apply);
        $output->writeln(sprintf(
            '%s%s: %d件を確認し、%d件に生IPアドレスまたはUser-Agentがありました。',
            $apply ? '消去完了 ' : 'dry-run ',
            $result['storage'],
            $result['scanned'],
            $result['affected'],
        ));

        return Command::SUCCESS;
    }
}
