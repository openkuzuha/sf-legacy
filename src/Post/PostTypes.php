<?php

namespace App\Post;

/**
 * @phpstan-type PostInput array{
 *     author: string,
 *     email: string,
 *     title: string,
 *     message: string,
 *     host: ?string,
 *     user_agent: ?string
 * }
 * @phpstan-type PostRecord array{
 *     posted_at: int,
 *     post_id: int,
 *     thread_id: int,
 *     location: string,
 *     host: ?string,
 *     user_agent: ?string,
 *     author: string,
 *     email: string,
 *     title: string,
 *     message: string,
 *     reply_to: ?int
 * }
 */
final class PostTypes
{
}
