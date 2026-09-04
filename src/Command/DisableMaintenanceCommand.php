<?php

namespace App\Command;

use App\Settings\SiteSettings;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bbs:maintenance:disable', description: 'メンテナンスモードを無効にします。')]
final class DisableMaintenanceCommand extends Command
{
    public function __construct(private readonly SiteSettings $siteSettings)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // 投稿受付・表示文・終了予定日時は次回の有効化に備えてそのまま残し、
            // メンテナンス有効フラグだけを下ろす。
            $this->siteSettings->setOperationStatus(
                $this->siteSettings->postingEnabled(),
                false,
                $this->siteSettings->maintenanceMessage(),
                $this->siteSettings->maintenanceEndsAt()?->format('Y-m-d\TH:i') ?? '',
            );
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('メンテナンスモードを無効にしました。');

        return Command::SUCCESS;
    }
}
