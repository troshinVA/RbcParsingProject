<?php

namespace App\Controller;

use App\Entity\Article;
use App\Helpers\ArticleRbcParser;
use App\Helpers\BaseArticleParser;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ArticlesApiController extends ApiController
{
    private ArticleRepository $articleRepository;
    private BaseArticleParser $articleParser;

    public function __construct(ManagerRegistry $orm)
    {
        $this->articleRepository = new ArticleRepository($orm);
        $this->articleParser = new ArticleRbcParser();
    }

    /**
     * @Route("/articles", methods={"POST"})
     */
    public function articlesAction(Request $request)
    {
        $postParams = json_decode($request->getContent());
        $isLastPage = false;
        try {
            $this->saveNewArticlesIfNeeded();
        } catch (\Exception $e) {
            return $this->respondWithErrors($e->getMessage());
        }

        $criteria = new Criteria();
        if (property_exists($postParams, 'firstId')) {
            $criteria->where(Criteria::expr()?->gt('id', $postParams->firstId))
                ->orderBy(['id' => 'desc']);
        } else if ($postParams->lastId) {
            $criteria->where(Criteria::expr()?->lt('id', $postParams->lastId))
                ->orderBy(['id' => 'desc'])
                ->setMaxResults($postParams->itemsOnPage);
        } else {
            $criteria->orderBy(['id' => 'desc'])
                ->setMaxResults($postParams->itemsOnPage);
        }

        try {
            $articles = $this->articleRepository->matching($criteria);
        } catch (\Exception $e) {
            return $this->respondWithErrors($e->getMessage());
        }

        $articlesForView = [];
        if ($articles->count() > 0) {
            $articlesForView = $this->getArticlesListForView($articles);
        } else {
            $isLastPage = true;
        }

        return $this->respond(
            [
                'articles' => $articlesForView,
                'meta' => [
                    'isLastPage' => $isLastPage
                ]
            ],
        );
    }

    /**
     * @Route("/article/update_rating/", methods="PATCH")
     */
    public function articleRatingUpdate(Request $request)
    {
        $patchParams = json_decode($request->getContent());
        $article = $this->articleRepository->findOneBy(['id' => $patchParams->id]);
        if ($article) {
            $article->setRating($patchParams->rating);
            $this->articleRepository->update($article);
            return $this->respond(
                [
                    'success' => true,
                ],
            );
        }

        return $this->respond(
            [
                'success' => false,
                'error' => 'Entity not found'
            ],
        );

    }

    private function getArticlesListForView($articles): array
    {
        $articlesForView = [];
        /** @var Article $article */
        foreach ($articles as $article) {
            $articlesForView[] = $article->convertToArray();
        }

        return $articlesForView;
    }

    private function saveNewArticlesIfNeeded(): void
    {
        $lastRow = $this->articleRepository->findOneBy([], ['id' => 'desc']);
        $lastHref = $lastRow?->getHref();

        $articles = $this->articleParser->getArticles($lastHref);
        if ($articles) {
            krsort($articles);
            $this->articleRepository->addList($articles, true);
        }
    }
}