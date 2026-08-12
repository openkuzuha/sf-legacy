<?php

namespace App\Command;

use App\Import\LegacyHtmlReader;
use App\Post\PostRecordCodec;
use App\Post\PostRepository;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bbs:import', description: '旧HTMLまたはJSON Linesから投稿を取り込みます。')]
final class ImportPostsCommand extends Command
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly PostRecordCodec $codec,
        private readonly LegacyHtmlReader $legacyHtmlReader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, '入力ファイル、URL、または標準入力を示す -')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'legacy-html または jsonl（省略時は自動判定）')
            ->addOption('location', null, InputOption::VALUE_REQUIRED, '取り込み先の名前', 'main')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '解析だけを行い保存しない');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $input->getArgument('source');
        if (!is_string($source)) {
            throw new RuntimeException('入力元を指定してください。');
        }
        $text = $this->readSource($source);
        $format = $input->getOption('format');
        $format = is_string($format) ? $format : $this->detectFormat($source, $text);
        $location = $input->getOption('location');
        if (!is_string($location) || $location === '') {
            throw new RuntimeException('locationには空でない名前を指定してください。');
        }
        $records = match ($format) {
            'legacy-html' => $this->legacyHtmlReader->read($text, $location),
            'jsonl' => $this->readJsonl($text),
            default => throw new RuntimeException(sprintf('未対応の形式 "%s" です。', $format)),
        };
        if ($records === []) {
            throw new RuntimeException('インポート対象の投稿が見つかりませんでした。');
        }

        $imported = 0;
        $skipped = 0;
        foreach ($records as $record) {
            if ((bool) $input->getOption('dry-run')) {
                ++$imported;
            } elseif ($this->posts->import($record)) {
                ++$imported;
            } else {
                ++$skipped;
            }
        }

        $output->writeln(sprintf(
            '%s: %d件（既存と同一: %d件）',
            (bool) $input->getOption('dry-run') ? '解析成功' : '取り込み完了',
            $imported,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    private function readSource(string $source): string
    {
        if ($source === '-') {
            return (string) stream_get_contents(STDIN);
        }
        if (!preg_match('#^https?://#i', $source) && !is_readable($source)) {
            throw new RuntimeException(sprintf('入力を読み込めません: %s', $source));
        }
        $text = @file_get_contents($source);
        if ($text === false) {
            throw new RuntimeException(sprintf('入力を読み込めません: %s', $source));
        }

        return $text;
    }

    private function detectFormat(string $source, string $text): string
    {
        $path = parse_url($source, PHP_URL_PATH);

        return is_string($path) && str_ends_with(strtolower($path), '.jsonl')
            || str_starts_with(ltrim($text), '{') ? 'jsonl' : 'legacy-html';
    }

    /** @return list<array{posted_at:int, post_id:int, thread_id:int, location:string, host:?string, user_agent:?string, author:string, email:string, title:string, message:string, reply_to:?int}> */
    private function readJsonl(string $text): array
    {
        $records = [];
        foreach (preg_split('/\R/', $text) ?: [] as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }
            $record = $this->codec->decode($line);
            if ($record === null) {
                throw new RuntimeException(sprintf('JSON Linesの%d行目が不正です。', $lineNumber + 1));
            }
            $records[] = $record;
        }

        return $records;
    }
}
