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
        try {
            $this->saveNewArticlesIfNeeded();
        } catch (\Exception $e) {
            return $this->respondWithErrors($e->getMessage());
        }

        if (!$lastArticleId = $postParams->lastId) {
            /** @var Article $lastRecord */
            $lastRecord = ($this->articleRepository->findBy(array(),array('id'=>'DESC'),1,0));
            if (!empty($lastRecord)) {
                $lastArticleId = $lastRecord[0]->getId() + 1;
            }
        }

        $criteria = new Criteria();
        $criteria->where(Criteria::expr()?->lt('id', $lastArticleId))
            ->orderBy(['id' => 'desc'])
            ->setMaxResults($postParams->itemsOnPage)
        ;
        try {
            $articles = $this->articleRepository->matching($criteria);
        }catch (\Exception $e) {
            return $this->respondWithErrors($e->getMessage());
        }
        $articlesForView = $this->getArticlesListForView($articles);

        return $this->respond(
            [
                'articles' => $articlesForView,
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