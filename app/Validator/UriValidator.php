<?php

namespace App\Validator;

use App\Contracts\Validator;

class UriValidator implements Validator
{
    /**
     * Host
     *
     * @var string
     */
    protected $host;

    /**
     * Строка содержащая url
     *
     * @var string
     */
    protected $url;

    /**
     * Конструктор заполняет параметры
     *
     * @param string $host
     * @param string $uri
     * @return void
     */
    public function __construct(string $host, string $uri)
    {
        $this->host = $host;
        $this->url = $uri;
    }

    /**
     * Проверяет на соответствие протоколу http или https
     *
     * @param string $scheme
     * @return boolean
     */
    protected function isHttp(string $scheme): bool
    {
        return false !== strpos($scheme, 'http') || false !== strpos($scheme, 'https');
    }

    /**
     * Проверяет url на валидность и возвращает true или false
     *
     * @return boolean
     */
    public function valid(): bool
    {
        $linkData = parse_url($this->url);

        if(isset($linkData["scheme"]) && !$this->isHttp($linkData["scheme"])) {
            return false;
        } elseif (isset($linkData["host"]) && $linkData["host"] !== $this->host) {
            return false;
        } elseif (isset($linkData["path"]) && strlen($linkData["path"]) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Удаляет все повторяющиеся ссылки в массиве
     *
     * @param array $repeat
     * @return array
     */
    public static function uniqueUrl(array $repeat): array
    {
        // TODO убрать из массива ссылки вида /club_cards и /club_cards/
        $unique = [];

        foreach ($repeat as $item) {
            if(!in_array($item, $unique) && $item !== "/") {
                array_push($unique, $item);
            }
        }
        return $unique;
    }
}