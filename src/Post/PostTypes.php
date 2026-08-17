<?php

namespace App\Post;

/**
 * @phpstan-type PostInput array{
 *     author: string,
 *     author_spoofed?: bool,
 *     email: string,
 *     title: string,
 *     message: string,
 *     auto_link?: bool,
 *     thread_id: ?int,
 *     reply_to: ?int
 * }
 * @phpstan-type PostRecord array{
 *     posted_at: string,
 *     post_id: int,
 *     thread_id: int,
 *     location: string,
 *     author: string,
 *     author_spoofed?: bool,
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
