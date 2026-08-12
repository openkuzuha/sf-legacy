<?php

namespace App\Controller;

use App\Log\PostLog;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class HelloController
{
    public function __construct(
        #[Autowire(param: 'app.title')]
        private readonly string $appTitle,
        private readonly Environment $twig,
        private readonly PostLog $postLog,
    ) {
    }

    #[Route('/', name: 'app_hello')]
    public function __invoke(): Response
    {
        $posts = array_map(function (array $post): array {
            $post['posted_at_display'] = $this->formatPostedAt($post['posted_at']);

            return $post;
        }, $this->postLog->all());

        return new Response($this->twig->render('bbs/index.html.twig', [
            'app_title' => $this->appTitle,
            'posts' => $posts,
        ]));
    }

    private function formatPostedAt(int $timestamp): string
    {
        $date = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Asia/Tokyo'));
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        return $date->format('Y/m/d') . '(' . $weekdays[(int) $date->format('w')] . ') ' . $date->format('H:i:s');
    }
}
