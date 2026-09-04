<?php

namespace App\Command;

use App\Settings\CloudModeSetup;
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
        private readonly CloudModeSetup $cloudModeSetup,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, '確認せずに実行する');
        $this->addOption(
            'with-mode',
            null,
            InputOption::VALUE_NONE,
            '.env.local のCLOUD_MODEも削除し、動作モードの選択からやり直せるようにする'
            . '（保存先が変わるため、選び直した後は現在のモードのデータが見えなくなります）',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $withMode = (bool) $input->getOption('with-mode');

        $confirmation = '管理用パスワード、サイト情報、および .env.local の ADMIN_PASSWORD_HASH・生成済みシークレットを削除します。'
            . '投稿データはそのまま残ります。';
        if ($withMode) {
            $confirmation .= ' さらに --with-mode により .env.local の CLOUD_MODE も削除するため、次回セットアップで'
                . '別のモードを選ぶと、現在のモードで保存されているデータ（投稿・サイト設定など）は見えなくなります。';
        }
        $confirmation .= ' よろしいですか？';
        if (!(bool) $input->getOption('force') && !$io->confirm($confirmation, false)) {
            $io->note('初期化を中止しました。');

            return Command::SUCCESS;
        }

        $this->siteSettings->resetAdminPasswordHash();
        $this->siteSettings->resetTitle();
        $this->siteSettings->resetAdminName();
        $this->siteSettings->resetAdminEmail();
        $keysToRemove = ['ADMIN_PASSWORD_HASH', 'APP_SECRET', 'AUDIT_HMAC_KEY'];
        if ($withMode) {
            $keysToRemove[] = 'CLOUD_MODE';
        }
        $this->envLocalWriter->remove($keysToRemove);

        $io->success('セットアップ状態を初期化しました。/admin/setup から再度セットアップできます。');
        $io->note(
            'それでも /admin/setup が /admin へリダイレクトされる場合は、'
            . 'ADMIN_PASSWORD_HASH が .env や実行環境の環境変数に直接設定されていないか確認してください'
            . '（このコマンドは .env.local のみを書き換えます）。',
        );
        if ($withMode && $this->cloudModeSetup->isFixedByEnvironment()) {
            $io->note(
                'CLOUD_MODEは実行環境の環境変数で指定されているため、.env.local から削除しても'
                . '動作モードの選択画面には戻りません。',
            );
        }

        return Command::SUCCESS;
    }
}
