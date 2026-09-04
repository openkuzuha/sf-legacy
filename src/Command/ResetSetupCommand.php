<?php

namespace App\Command;

use App\Settings\EnvLocalWriter;
use App\Settings\SiteSettingsRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bbs:setup:reset', description: '管理用パスワードとサイト情報を初期化し、セットアップ画面を再表示できるようにします。')]
final class ResetSetupCommand extends Command
{
    public function __construct(
        private readonly SiteSettingsRepository $siteSettings,
        private readonly EnvLocalWriter $envLocalWriter,
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
        $confirmation = '管理用パスワード、サイト情報、および .env.local の ADMIN_PASSWORD_HASH・生成済みシークレットを削除します。'
            . '投稿データはそのまま残ります。よろしいですか？';
        if (!(bool) $input->getOption('force') && !$io->confirm($confirmation, false)) {
            $io->note('初期化を中止しました。');

            return Command::SUCCESS;
        }

        $this->siteSettings->resetAdminPasswordHash();
        $this->siteSettings->resetTitle();
        $this->siteSettings->resetAdminName();
        $this->siteSettings->resetAdminEmail();
        $this->envLocalWriter->remove(['ADMIN_PASSWORD_HASH', 'APP_SECRET', 'AUDIT_HMAC_KEY']);

        $io->success('セットアップ状態を初期化しました。/admin/setup から再度セットアップできます。');
        $io->note(
            'それでも /admin/setup が /admin へリダイレクトされる場合は、'
            . 'ADMIN_PASSWORD_HASH が .env や実行環境の環境変数に直接設定されていないか確認してください'
            . '（このコマンドは .env.local のみを書き換えます）。',
        );

        return Command::SUCCESS;
    }
}
