<?php

namespace App\Command;

use App\Post\PostArchive;
use App\Post\PostRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bbs:data:reset', description: 'すべての投稿と過去ログを削除します。')]
final class ResetDataCommand extends Command
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly PostArchive $archive,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, '確認せずに実行する');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!(bool) $input->getOption('force') && !$io->confirm('すべての投稿と過去ログを削除します。よろしいですか？', false)) {
            $io->note('初期化を中止しました。');

            return Command::SUCCESS;
        }

        $postCount = $this->posts->clear();
        $archiveCount = $this->archive->clear();
        $io->success(sprintf('初期化完了: 投稿%d件、過去ログ%d件を削除しました。', $postCount, $archiveCount));

        return Command::SUCCESS;
    }
}
