<?php

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


class RbcNewsController extends ApiController
{
    private const URL = 'https://www.rbc.ru/newspaper/';
    private const NEWS_COUNT = 15;
    private Crawler $crawler;
    private Client $client;
    private ArticleRepository $articleRepository;

    public function __construct(ManagerRegistry $orm)
    {
        $this->articleRepository = new ArticleRepository($orm);
        $this->crawler = new Crawler();
        $this->client = new Client();
    }

    /**
     * @Route("/api/articles-list", name="rbc_news")
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function newsList()
    {
        try {
            $this->saveNewArticlesIfNeeded();
        } catch (\Exception $e) {
            return $this->respond([
                [
                    'error' => $e->getMessage(),
                ]
            ]);
        }

        $articles = $this->articleRepository->findBy([], ['id' => 'desc'], self::NEWS_COUNT);
        $articlesForView = $this->getArticlesListForView($articles);

        return $this->respond([
            [
                'articles' => $articlesForView,
                'count' => 0
            ]
        ]);
    }

//    /**
//     * @Route("/article/{id}", name="detail_article", requirements={"id"="\d+"})
//     * @throws \GuzzleHttp\Exception\GuzzleException
//     */
//    public function detailPage(Request $request)
//    {
//        $article = $this->articleRepository->find($request->get('id'));
//        if ($article) {
//            return $this->render('detailPage.html.twig', ['article' => $article->convertToArray()]);
//        }
//
//        return $this->render('notFound.html.twig');
//    }

    private function getArticles($lastArticleHref = null): array
    {
        $content = $this->client->get(self::URL)->getBody()->getContents();
        $this->crawler->addHtmlContent($content);
        $links = $this->crawler->filter('.news-feed__list > a')->reduce(function (Crawler $node, $i) {
            return $i < self::NEWS_COUNT;
        });

        $articles = [];
        /** @var \DOMElement $link */
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href == $lastArticleHref) {
                break;
            }
            $article = new Article($href);

            /** @var \DOMElement $node */
            foreach ($link->childNodes as $node) {
                if ($node->nodeType == 1) {
                    switch ($node->getAttribute('class')) {
                        case 'news-feed__item__title':
                            $article->setTitle(trim($node->textContent));
                            break;
                        case 'news-feed__item__date':
                            $arr = explode(', ', trim($node->textContent));
                            $article->setPostTime(new \DateTime($arr[0]));
                            $article->setCategory($arr[1]);
                            break;
                        default:
                            break;
                    }
                }
            }

            $this->getDetailPageContent($href, $article);
            $articles[] = $article;
        }

        return $articles;
    }

    private function getDetailPageContent(string $href, Article $article): void
    {
        $detailContent = $this->client->get($href)->getBody()->getContents();
        $detailCrawler = new Crawler($detailContent);

        $img = $detailCrawler->filter('div.article__main-image__wrap > img')->first();
        if (!is_null($img->getNode(0))) {
            $article->setImg($img->image()->getUri());
        }

        $articleContent = $detailCrawler->filter('div.article__text')->first();
        if (!is_null($articleContent->getNode(0))) {
            $body = $articleContent->text();
            $article->setBody($body);
            $article->setDescription($this->prepareDescription($body));
        }
    }

    private function prepareDescription(string $body): string
    {
        $string = mb_substr($body, 0, 200);
        return $string . '...';
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

        $articles = $this->getArticles($lastHref);
        if ($articles) {
            krsort($articles);
            $this->articleRepository->addList($articles, true);
        }
    }
}
