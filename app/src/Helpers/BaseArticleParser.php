<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

abstract class BaseArticleParser
{
    protected Crawler $crawler;
    protected Client $client;
    protected $url;
    protected $numberItems;

    public function __construct($url, $numberItems)
    {
        $this->crawler = new Crawler();
        $this->client = new Client();
        $this->url = $url;
        $this->numberItems = $numberItems;
    }

    abstract public function getArticles(): array;
}