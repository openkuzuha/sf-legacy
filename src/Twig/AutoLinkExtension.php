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
        ];
    }

    public function autoLink(string $message): string
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

            $html .= $this->escape(substr($message, $offset, $position - $offset));
            $escapedUrl = $this->escape($url);
            $html .= sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                $escapedUrl,
                $escapedUrl,
            );
            $offset = $position + strlen($url);
        }

        return $html . $this->escape(substr($message, $offset));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
