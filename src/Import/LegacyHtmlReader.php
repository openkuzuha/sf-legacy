<?php

namespace App\Import;

use App\Post\PostTypes;
use DateTimeImmutable;
use DateTimeZone;

/** @phpstan-import-type PostRecord from PostTypes */
final class LegacyHtmlReader
{
    public function __construct(private readonly DateTimeZone $timezone = new DateTimeZone('Asia/Tokyo'))
    {
    }

    /** @return list<PostRecord> */
    public function read(string $html, string $location = 'main'): array
    {
        $chunks = preg_split('/<div class="m" id="m(\d+)">/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($chunks === false) {
            return [];
        }

        $posts = [];
        $threadOf = [];
        for ($i = 1; $i < count($chunks); $i += 2) {
            $postId = (int) $chunks[$i];
            $body = $chunks[$i + 1] ?? '';
            $postedAt = $this->parseDate($body);
            if ($postId < 1 || $postedAt === null) {
                continue;
            }

            $title = $this->text($this->between($body, '<span class="ms">', '</span>') ?? '');
            [$author, $email] = $this->parseAuthor(
                $this->between($body, '<span class="mun">', '</span>&nbsp;') ?? ''
            );
            [$message, $replyTo] = $this->parseMessage(
                $this->between($body, '<pre class="msgnormal">', '</pre>')
                    ?? $this->between($body, '<pre>', '</pre>')
                    ?? ''
            );

            if ($replyTo !== null && isset($threadOf[$replyTo])) {
                $threadId = $threadOf[$replyTo];
            } elseif ($replyTo === null) {
                $threadId = $postId;
            } else {
                $threadId = $replyTo;
            }
            $threadOf[$postId] = $threadId;

            $posts[] = [
                'posted_at' => $postedAt,
                'post_id' => $postId,
                'thread_id' => $threadId,
                'location' => $location,
                'host' => null,
                'user_agent' => null,
                'author' => $author,
                'email' => $email,
                'title' => $title,
                'message' => $message,
                'reply_to' => $replyTo,
            ];
        }

        return $posts;
    }

    private function parseDate(string $body): ?int
    {
        if (
            !preg_match(
                '/投稿日時：(\d{4})\/(\d{2})\/(\d{2})\([^)]*\)\s*(\d{2}):(\d{2}):(\d{2})/',
                $body,
                $match,
            )
        ) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat(
            '!Y/m/d H:i:s',
            sprintf('%s/%s/%s %s:%s:%s', $match[1], $match[2], $match[3], $match[4], $match[5], $match[6]),
            $this->timezone,
        );

        return $date === false ? null : $date->getTimestamp();
    }

    /** @return array{string, string} */
    private function parseAuthor(string $block): array
    {
        $email = '';
        if (preg_match('/<a href="mailto:([^"]*)">(.*)<\/a>/s', $block, $match)) {
            $email = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $block = $match[2];
        }

        return [$this->text($block), $email];
    }

    /** @return array{string, int|null} */
    private function parseMessage(string $block): array
    {
        $replyTo = null;
        if (preg_match('/<a href="#a(\d+)">参考：[^<]*<\/a>\s*$/', $block, $match)) {
            $replyTo = (int) $match[1];
            $block = substr($block, 0, -strlen($match[0]));
        }

        $block = preg_replace('/<span class="q">(.*?)<\/span>/s', '$1', $block) ?? $block;

        return [$this->text($block), $replyTo];
    }

    private function text(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function between(string $text, string $start, string $end): ?string
    {
        $offset = strpos($text, $start);
        if ($offset === false) {
            return null;
        }
        $offset += strlen($start);
        $endOffset = strpos($text, $end, $offset);

        return $endOffset === false ? null : substr($text, $offset, $endOffset - $offset);
    }
}
