<?php

namespace Elephant\Parser;

use Elephant\Http\RequestClient;
use Elephant\Validator\UriValidator;
use GuzzleHttp\Exception\GuzzleException;
use Elephant\Contracts\{
    ParserInterface,
    SettingsInterface
};

class SitemapParser implements ParserInterface
{
    /**
     * Главный файл карты сайта
     *
     * @var string
     */
    protected $sitemapPath;

    /**
     * Объект клиента
     *
     * @var RequestClient
     */
    protected $client;

    /**
     * Объект настроек
     *
     * @var SettingsInterface
     */
    protected $settings;

    /**
     * Тело карты сайта
     *
     * @var string
     */
    protected $sitemapBody;

    /**
     * Указывает требуется ли сравнивать ссылки в карте сайта с текущим хостом
     *
     * @var bool
     */
    protected $checkLinks;

    /**
     * Указывает максимальное количество ссылок которое будет проверено. 0 - без ограничений
     *
     * @var int
     */
    protected $maxLinks;

    /**
     * @param string $sitemapPath
     * @param bool $checkLinks
     * @param int $maxLinks
     */
    public function __construct(string $sitemapPath = 'sitemap.xml', bool $checkLinks = false, int $maxLinks = 0)
    {
        $this->sitemapPath = $sitemapPath;
        $this->checkLinks = $checkLinks;

        if($maxLinks < 0) {
            $maxLinks = 0;
        }
        $this->maxLinks = $maxLinks;
    }

    /**
     * Отправляет запрос на получение тела карты сайта
     *
     * @throws GuzzleException
     * @return void
     */
    protected function setHttpSitemapBody(): void
    {
        $path = "/" . $this->sitemapPath;
        $response = $this->client->request("GET", $path);
        $this->sitemapBody = (string) $response->getBody();
    }

    /**
     * Проверяет является ли url xml файлом
     *
     * @param string $url
     * @return boolean
     */
    protected function isXmlUrl(string $url) : bool
    {
        $xml = strstr($url, ".xml");
        if($xml === ".xml") {
            return true;
        }
        return false;
    }

    /**
     * Проверяет что строка является ссылкой
     *
     * @param string $link
     * @return bool
     */
    protected function checkLink(string $link)
    {
        if($this->checkLinks && !$this->fullCheck($link)) {
            return false;
        } elseif (!$this->checkLinks && !$this->simpleCheck($link)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $link
     * @return bool
     */
    protected function fullCheck(string $link)
    {
        $settings = $this->settings->getSettings();
        $url = parse_url($settings['base_uri']);
        $validator = new UriValidator($link, $url['host']);

        if($validator->validByHost()) {
            return true;
        }

        return false;
    }

    /**
     * @param string $link
     * @return bool
     */
    protected function simpleCheck(string $link)
    {
        $validator = new UriValidator($link);

        if($validator->valid()) {
            return true;
        }

        return false;
    }

    /**
     * Парсинг карты сайта
     *
     * @param RequestClient $client
     * @param SettingsInterface $settings
     * @return string
     * @throws GuzzleException
     */
    public function parse(RequestClient $client, SettingsInterface $settings): string
    {
        $this->client = $client;
        $this->settings = $settings;
        $this->setHttpSitemapBody();

        $linksCheck = 0;
        $reportText = 'No links were found in the sitemap';

        $xml = new \SimpleXMLElement($this->sitemapBody);

        if(0 !== $xml->count()) {

            $reportText = '';

            foreach($xml->children() as $nodeName => $nodeValue) {

                if(!isset($nodeValue->loc)) {
                    continue;
                }

                $link = trim($nodeValue->loc->__toString());

                if(!$this->checkLink($link)) {
                    continue;
                }

                // TODO доделать чтобы возвращал строку которую передаю в отчет. Здесь же или еще где-то отправляю запросы по ссылкам
                // TODO плюс сделать возможность ходить по xml ссылкам в карте сайта
                if(!$this->isXmlUrl($link)) {
                    $response = $this->client->get($link, ['http_errors' => false, 'allow_redirects' => false]);
                    $reportText .= $link . '<br>' . $response->getStatusCode() . '<br>';
                    $linksCheck++;
                }

                if($this->maxLinks > 0 && $linksCheck >= $this->maxLinks) {
                    break;
                }
            }
        }

        return $reportText;
    }
}