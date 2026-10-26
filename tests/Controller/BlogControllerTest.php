<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Blog\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class BlogControllerTest extends KernelTestCase
{
    public function testCategoryPageListsItsArticlesNewestFirst(): void
    {
        $html = $this->request('/category/ai-engineering')->getContent() ?: '';

        self::assertStringContainsString('AI Engineering', $html);

        /** @var ArticleRepository $repository */
        $repository = self::getContainer()->get(ArticleRepository::class);
        $articles = $repository->findByCategorySlug('ai-engineering');

        self::assertNotEmpty($articles);
        self::assertStringContainsString($articles[0]->title, $html);
        self::assertStringContainsString(\count($articles).' articles', $html);

        // Every listed article belongs to the category, newest first.
        $previous = null;
        foreach ($articles as $article) {
            self::assertSame('AI Engineering', $article->category);
            self::assertStringContainsString($article->title, $html);

            if (null !== $previous) {
                self::assertLessThanOrEqual($previous, $article->date);
            }
            $previous = $article->date;
        }

        // The only category links on the page point back at this category.
        self::assertSame(
            ['/category/ai-engineering'],
            array_values(array_unique($this->categoryHrefs($html))),
        );
    }

    public function testCategoryPageWithASingleArticleUsesTheSingular(): void
    {
        $html = $this->request('/category/cloud-economics')->getContent() ?: '';

        self::assertStringContainsString('Cloud Economics', $html);
        self::assertStringContainsString('1 article', $html);
        self::assertStringNotContainsString('1 articles', $html);
    }

    public function testUnknownCategoryIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->request('/category/does-not-exist');
    }

    public function testCategorySlugWithUnsupportedCharactersIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->request('/category/AI%20Engineering');
    }

    public function testHomepageChipsLinkToCategoryPages(): void
    {
        $html = $this->request('/')->getContent() ?: '';

        $hrefs = $this->categoryHrefs($html);

        // One chip per card plus the featured block.
        /** @var ArticleRepository $repository */
        $repository = self::getContainer()->get(ArticleRepository::class);
        self::assertCount(\count($repository->findAll()) + 1, $hrefs);
        self::assertContains('/category/ai-engineering', $hrefs);

        // The stretched-link refactor must not nest one anchor inside another.
        self::assertSame(1, $this->maxAnchorNestingDepth($html));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the kernel in debug mode registers Symfony's exception
        // handler; hand it back so PHPUnit does not flag the test as risky.
        restore_exception_handler();
    }

    private function request(string $uri): Response
    {
        $kernel = self::bootKernel();

        return $kernel->handle(Request::create($uri), HttpKernelInterface::MAIN_REQUEST, false);
    }

    /**
     * @return list<string>
     */
    private function categoryHrefs(string $html): array
    {
        preg_match_all('~<a\s[^>]*href="(/category/[^"]*)"~', $html, $matches);

        return $matches[1];
    }

    private function maxAnchorNestingDepth(string $html): int
    {
        preg_match_all('~</?a[\s>]~', $html, $matches, \PREG_OFFSET_CAPTURE);

        $depth = 0;
        $max = 0;
        foreach ($matches[0] as [$tag, $_offset]) {
            $depth += str_starts_with($tag, '</') ? -1 : 1;
            $max = max($max, $depth);
        }

        return $max;
    }
}
