<?php

namespace App\Post;

/**
 * @phpstan-type PostInput array{
 *     author: string,
 *     email: string,
 *     title: string,
 *     message: string,
 *     host: ?string,
 *     user_agent: ?string,
 *     thread_id: ?int,
 *     reply_to: ?int
 * }
 * @phpstan-type PostRecord array{
 *     posted_at: string,
 *     post_id: int,
 *     thread_id: int,
 *     location: string,
 *     host: ?string,
 *     user_agent: ?string,
 *     author: string,
 *     email: string,
 *     title: string,
 *     message: string,
 *     auto_link: bool,
 *     reply_to: ?int
 * }
 */
final class PostTypes
{
}
