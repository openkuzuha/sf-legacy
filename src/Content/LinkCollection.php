<?php

namespace App\Content;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LinkCollection
{
    private readonly CommonMarkConverter $converter;

    public function __construct(
        #[Autowire(param: 'app.links_filename')]
        private readonly string $filename,
    ) {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** @return list<array{label: string, url: string}> */
    public function links(): array
    {
        $markdown = @file_get_contents($this->filename);
        if ($markdown === false) {
            return [];
        }

        $html = (string) $this->converter->convert($markdown);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $links = [];
        foreach ($document->getElementsByTagName('a') as $link) {
            $url = $link->getAttribute('href');
            if ($url === '') {
                continue;
            }
            $links[] = [
                'label' => trim($link->textContent),
                'url' => $url,
            ];
        }

        return $links;
    }
}
