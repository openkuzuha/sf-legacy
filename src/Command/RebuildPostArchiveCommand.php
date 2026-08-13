<?php

namespace App\Command;

use App\Post\PostArchive;
use App\Post\PostRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bbs:archive:rebuild', description: '正本の投稿ログから日別アーカイブを再構築します。')]
final class RebuildPostArchiveCommand extends Command
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly PostArchive $archive,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $written = 0;
        $unchanged = 0;
        foreach ($this->posts->all() as $post) {
            if ($this->archive->put($post)) {
                ++$written;
            } else {
                ++$unchanged;
            }
        }

        $output->writeln(sprintf('再構築完了: %d件（既存と同一: %d件）', $written, $unchanged));

        return Command::SUCCESS;
    }
}
