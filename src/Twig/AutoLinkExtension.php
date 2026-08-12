<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AutoLinkExtension extends AbstractExtension
{
    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('auto_link', $this->autoLink(...), ['is_safe' => ['html']]),
            new TwigFilter('search_highlight', $this->highlight(...), ['is_safe' => ['html']]),
        ];
    }

    public function autoLink(string $message, string $keyword = ''): string
    {
        $pattern = '~https?://[^\s<>"\']+~iu';
        $offset = 0;
        $html = '';

        preg_match_all($pattern, $message, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$candidate, $position]) {
            $url = preg_replace('~(?:[.,!?;:)}\]]|。|、|，|．|！|？|》|）|】|」|』)+$~u', '', $candidate);
            if (!is_string($url) || $url === '') {
                continue;
            }

            $html .= $this->highlight(substr($message, $offset, $position - $offset), $keyword);
            $escapedUrl = $this->escape($url);
            $html .= sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                $escapedUrl,
                $this->highlight($url, $keyword),
            );
            $offset = $position + strlen($url);
        }

        return $html . $this->highlight(substr($message, $offset), $keyword);
    }

    public function highlight(string $value, string $keyword): string
    {
        if ($keyword === '') {
            return $this->escape($value);
        }

        $parts = preg_split(
            sprintf('/(%s)/iu', preg_quote($keyword, '/')),
            $value,
            flags: PREG_SPLIT_DELIM_CAPTURE,
        );
        if ($parts === false) {
            return $this->escape($value);
        }

        $html = '';
        foreach ($parts as $index => $part) {
            $escaped = $this->escape($part);
            $html .= $index % 2 === 1 ? '<mark class="search-highlight">' . $escaped . '</mark>' : $escaped;
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
