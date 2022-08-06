<?php

namespace App\Helpers;

use App\Entity\Article;
use Symfony\Component\DomCrawler\Crawler;

class ArticleRbcParser extends BaseArticleParser
{
    public function __construct()
    {
        parent::__construct('https://www.rbc.ru/short_news/', 15);
    }

    public function getArticles($lastArticleHref = null): array
    {
        $articles = [];
        try {
            $content = $this->client->get($this->url)->getBody()->getContents();
            $this->crawler->addHtmlContent($content);
            $articlesDOM = $this->crawler
                ->filter('div.js-news-feed-list > div.js-news-feed-item')
                ->reduce(function (Crawler $node, $i) {
                    return $i < $this->numberItems;
                })
                ->filter('div > div.item__wrap');

            $links = $articlesDOM->filter('a.item__link');
            $categories = $articlesDOM->filter('div.item__bottom > a.item__category');
            $times = $articlesDOM->filter('div.item__bottom > span.item__category');
            $titles = $links->filter('span.item__title-wrap');

            foreach ($links as $key => $link) {
                $href = $link?->getAttribute('href');
                if ($href == $lastArticleHref) {
                    break;
                }

                $article = new Article($href);
                $article->setRating(random_int(0, 10));
                $article->setTitle($titles->getNode($key)->textContent);
                $article->setCategory($categories->getNode($key)->textContent);
                try {
                    $time = new \DateTime($times->getNode($key)->textContent);
                } catch (\Exception $e) {
                    $time = new \DateTime();
                }
                $article->setPostTime($time);

                foreach ($link->childNodes as $node) {
                    if ($node->nodeType == 1) {
                        switch ($node->getAttribute('class')) {
                            case 'item__title-wrap':
                                $article->setTitle(trim($node->textContent));
                                break;
                            case 'item__image-block':
                                foreach ($node->childNodes as $imgNode) {
                                    if ($imgNode->nodeType == 1) {
                                        $article->setImg($imgNode->getAttribute('src'));
                                    }
                                }
                                break;
                        }
                    }
                }

                $this->getDetailPageContent($href, $article);
                if ($article->getTitle()) {
                    $articles[] = $article;
                }
            }

            return $articles;
        } catch (\Exception $e) {
            return $articles;
        }
    }

    private function getDetailPageContent(string $href, Article $article): void
    {
        $detailContent = $this->client->get($href)->getBody()->getContents();
        $detailCrawler = new Crawler($detailContent);

        $articleContent = $detailCrawler->filter('div.article__text')->first();

        if (!is_null($articleContent->getNode(0))) {
            $body = $articleContent->text();
            $article->setBody($body);
            $article->setDescription($this->prepareDescription($body));
        } else {
            $body = "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Deleniti distinctio eaque exercitationem hic id itaque perferendis quisquam repellendus sequi suscipit!";
            $article->setBody($body);
            $article->setDescription($this->prepareDescription($body));
        }
    }

    private function prepareDescription(string $body): string
    {
        if (strlen($body) <= 200) {
            return $body;
        }

        $string = mb_substr($body, 0, 200);
        return $string . '...';
    }
}