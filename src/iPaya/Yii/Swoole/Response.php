<?php
/**
 * @link http://www.ipaya.cn/
 * @copyright Copyright (c) 2018 ipaya.cn
 */

namespace iPaya\Yii\Swoole;

use Psr\Http\Message\ResponseInterface;
use Yii;
use yii\base\InvalidConfigException;
use yii\web\Cookie;
use Zend\Diactoros\Stream;

class Response extends \yii\web\Response
{
    /**
     * @param bool $enableCookieValidation
     * @param string $validationKey
     * @return ResponseInterface
     * @throws InvalidConfigException
     */
    public function toPsrResponse(bool $enableCookieValidation = true, ?string $validationKey = ''): ResponseInterface
    {
        $headers = [];
        foreach ($this->getHeaders() as $name => $value) {
            $headers[$name] = $value;
        }
        if ($enableCookieValidation) {
            if ($validationKey == '') {
                throw new InvalidConfigException('Request::cookieValidationKey must be configured with a secret key.');
            }
        }
        /** @var Cookie $cookie */
        foreach ($this->getCookies() as $cookie) {
            $value = $cookie->value;
            if ($cookie->expire != 1 && isset($validationKey)) {
                $value = Yii::$app->getSecurity()->hashData(serialize([$cookie->name, $value]), $validationKey);
            }

            // value 需要 urlencode 编码，因为 value 中存在分号
            $sections = [
                $cookie->name . '=' . urlencode($value),
            ];
            if ($cookie->expire) {
                $sections[] = 'expire=' . date('%A, %d-%b-%Y %H:%M:%S GMT', $cookie->expire);
            }
            if ($cookie->domain) {
                $sections[] = 'domain=' . $cookie->domain;
            }
            if ($cookie->path) {
                $sections[] = 'path=' . $cookie->path;
            }
            if ($cookie->secure) {
                $sections[] = 'secure';
            }
            if ($cookie->httpOnly) {
                $sections[] = 'httponly';
            }
            $headers["Set-Cookie"][] = implode('; ', $sections);
        }

        $bodyStream = new Stream('php://memory', 'wb+');

        $this->trigger(self::EVENT_BEFORE_SEND);
        $this->prepare();
        $this->trigger(self::EVENT_AFTER_PREPARE);
        $bodyStream->write($this->content);
        $bodyStream->rewind();
        $this->trigger(self::EVENT_AFTER_SEND);
        $this->isSent = true;

        return new \Zend\Diactoros\Response($bodyStream, $this->getStatusCode(), $headers);
    }
}
