<?php

declare(strict_types=1);

namespace App\Blog;

/**
 * An immutable representation of a blog article parsed from a markdown file.
 *
 * The {@see $content} property holds the rendered HTML body and is only
 * populated when a single article is loaded for its detail page; list views
 * leave it null to avoid rendering every body up front.
 */
final readonly class Article
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $author,
        public string $role,
        public \DateTimeImmutable $date,
        public string $category,
        public array $tags,
        public int $readingTime,
        public bool $featured,
        public ?string $content = null,
    ) {
    }

    /**
     * URL-safe form of the category, e.g. "AI Engineering" -> "ai-engineering".
     */
    public function categorySlug(): string
    {
        return strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $this->category), '-'));
    }

    /**
     * Initials used for the author avatar, e.g. "Marta Olsen" -> "MO".
     */
    public function authorInitials(): string
    {
        $parts = preg_split('/\s+/', trim($this->author)) ?: [];
        $initials = array_map(static fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)), $parts);

        return implode('', \array_slice($initials, 0, 2));
    }
}
