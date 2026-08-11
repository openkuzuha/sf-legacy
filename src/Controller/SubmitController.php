<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

final class SubmitController
{
    #[Route('/submit', name: 'app_submit', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $data = (new VarCloner())->cloneVar($request);
        $dump = (new HtmlDumper())->dump($data, true);

        return new Response($dump);
    }
}
