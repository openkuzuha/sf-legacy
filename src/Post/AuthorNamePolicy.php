<?php

namespace App\Post;

final class AuthorNamePolicy
{
    /** @return array{author: string, spoofed: bool} */
    public function forGeneralPost(string $author, string $adminName): array
    {
        if ($adminName === '' || !str_contains($author, $adminName)) {
            return ['author' => $author, 'spoofed' => false];
        }

        return ['author' => $adminName . '（騙り）', 'spoofed' => true];
    }
}
