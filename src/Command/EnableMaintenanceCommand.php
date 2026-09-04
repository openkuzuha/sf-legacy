<?php

namespace App\Command;

use App\Settings\SiteSettings;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bbs:maintenance:enable', description: 'メンテナンスモードを有効にします。')]
final class EnableMaintenanceCommand extends Command
{
    public function __construct(private readonly SiteSettings $siteSettings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'message',
                InputArgument::OPTIONAL,
                '公開画面に表示するメンテナンス表示文（省略時は現在設定されている値を使う）',
            )
            ->addOption(
                'ends-at',
                null,
                InputOption::VALUE_REQUIRED,
                '終了予定日時（例: 2026-09-10T12:00。省略時は現在設定されている値を維持する）',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $message = $input->getArgument('message');
        $message = is_string($message) && $message !== '' ? $message : $this->siteSettings->maintenanceMessage();

        $endsAt = $input->getOption('ends-at');
        if (!is_string($endsAt)) {
            $endsAt = $this->siteSettings->maintenanceEndsAt()?->format('Y-m-d\TH:i') ?? '';
        }

        try {
            // 投稿受付・表示文・終了予定日時は現状を維持し、メンテナンス有効フラグだけを立てる。
            $this->siteSettings->setOperationStatus(
                $this->siteSettings->postingEnabled(),
                true,
                $message,
                $endsAt,
            );
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('メンテナンスモードを有効にしました。');
        $io->text('表示文: ' . $message);
        if ($endsAt !== '') {
            $io->text('終了予定日時: ' . $endsAt);
        }

        return Command::SUCCESS;
    }
}
