<?php

declare(strict_types=1);

namespace App\Controller;

use App\Blog\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    public function __construct(private readonly ArticleRepository $articles)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('blog/index.html.twig', [
            'featured' => $this->articles->findFeatured(),
            'articles' => $this->articles->findAll(),
        ]);
    }

    #[Route('/category/{slug}', name: 'category', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function category(string $slug): Response
    {
        $articles = $this->articles->findByCategorySlug($slug);
        if ([] === $articles) {
            throw $this->createNotFoundException('No articles found for category "'.$slug.'".');
        }

        return $this->render('blog/category.html.twig', [
            'categoryName' => $this->articles->findCategoryName($slug),
            'articles' => $articles,
        ]);
    }

    #[Route('/articles/{slug}', name: 'article', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function article(string $slug): Response
    {
        $article = $this->articles->findOneBySlug($slug);
        if (null === $article) {
            throw $this->createNotFoundException('No article found for slug "'.$slug.'".');
        }

        return $this->render('blog/article.html.twig', [
            'article' => $article,
            'more' => \array_slice($this->articles->findAllExcept($slug), 0, 3),
        ]);
    }
}
