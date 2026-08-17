<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'bbs:admin:password-hash',
    description: '管理画面用パスワードのハッシュを生成します。',
)]
final class GenerateAdminPasswordHashCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $password = $io->askHidden('管理画面用パスワードを入力してください');
        if (!is_string($password) || $password === '') {
            $io->error('パスワードを空にはできません。');

            return Command::FAILURE;
        }

        $confirmation = $io->askHidden('確認のため、もう一度入力してください');
        if (!is_string($confirmation) || !hash_equals($password, $confirmation)) {
            $io->error('入力したパスワードが一致しません。');

            return Command::FAILURE;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $io->writeln('次の値を .env.local などに設定してください。');
        $io->newLine();
        $io->writeln("ADMIN_PASSWORD_HASH='" . $hash . "'");

        return Command::SUCCESS;
    }
}
