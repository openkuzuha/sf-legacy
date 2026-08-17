<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bbs:audit-key:generate', description: '投稿者監査用のHMAC鍵を生成します。')]
final class GenerateAuditKeyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('AUDIT_HMAC_KEY=' . base64_encode(random_bytes(32)));

        return Command::SUCCESS;
    }
}
