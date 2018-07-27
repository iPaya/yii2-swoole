<?php
/**
 * @link http://www.ipaya.cn/
 * @copyright Copyright (c) 2018 ipaya.cn
 */

namespace iPaya\Yii\Swoole;


use Psr\Http\Message\ServerRequestInterface;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\web\RequestParserInterface;

class Request extends \yii\web\Request
{
    /**
     * @var array
     */
    private $_serverParams = [];
    /**
     * @var HttpServer
     */
    private $server;
    private $_postParams = [];
    private $_bodyParams;
    private $_cookieParams = [];

    /**
     * @param HttpServer $server
     * @param ServerRequestInterface $psrRequest
     * @param array $definitions
     * @return Request
     */
    public static function createFromPsrRequest(HttpServer $server, ServerRequestInterface $psrRequest, $definitions = []): Request
    {
        $uri = $psrRequest->getUri();

        $hostInfo = $uri->getScheme() . '://' . $uri->getHost() . ':' . $uri->getPort();
        $url = $hostInfo . $uri->getPath();
        if ($uri->getQuery()) {
            $url .= '?' . $uri->getQuery();
        }
        $requestUri = $psrRequest->getServerParams()['REQUEST_URI'];

        $request = new Request(ArrayHelper::merge($definitions, [
            'url' => $url,
            'pathInfo' => $requestUri,
            'hostInfo' => $hostInfo,
            'queryParams' => $psrRequest->getQueryParams(),
        ]));

        if (isset($server->getApplicationConfig()['components']['request']['cookieValidationKey'])) {
            $request->cookieValidationKey = $server->getApplicationConfig()['components']['request']['cookieValidationKey'];
        }

        $request->setServerParams($psrRequest->getServerParams());
        $request->setPostParams($psrRequest->getParsedBody() ?? []);
        $request->setRawBody($psrRequest->getBody()->getContents());
        $request->setCookieParams($psrRequest->getCookieParams());

        $headers = $request->getHeaders();
        $headers->set('X-Http-Method-Override', $psrRequest->getMethod());

        if ($psrRequest->hasHeader('X-Requested-With')) {
            $headers->set('X-Requested-With', implode(', ', $psrRequest->getHeader('X-Requested-With')));
        }

        if ($psrRequest->hasHeader('X-Pjax')) {
            $headers->set('X-Pjax', implode(', ', $psrRequest->getHeader('X-Pjax')));
        }

        return $request;
    }

    /**
     * @return array
     */
    public function getServerParams(): array
    {
        return $this->_serverParams;
    }

    /**
     * @param array $serverParams
     */
    public function setServerParams(array $serverParams): void
    {
        $this->_serverParams = $serverParams;
    }

    /**
     * @inheritdoc
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            $postParams = $this->getPostParams();

            if (isset($postParams[$this->methodParam])) {
                $this->_bodyParams = $postParams;
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }

            $rawContentType = $this->getContentType();
            if (($pos = strpos($rawContentType, ';')) !== false) {
                // e.g. text/html; charset=UTF-8
                $contentType = substr($rawContentType, 0, $pos);
            } else {
                $contentType = $rawContentType;
            }

            if (isset($this->parsers[$contentType])) {
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException('The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.');
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $_POST
                $this->_bodyParams = $postParams;
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }

        return $this->_bodyParams;
    }

    /**
     * @inheritdoc
     */
    public function setBodyParams($values)
    {
        $this->_bodyParams = $values;
    }

    /**
     * @return array
     */
    public function getPostParams(): array
    {
        return $this->_postParams;
    }

    /**
     * @param array $values
     */
    public function setPostParams(array $values): void
    {
        $this->_postParams = $values;
    }

    protected function loadCookies()
    {
        $cookies = [];
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            foreach ($this->getCookieParams() as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }
                // 解码
                $value = urldecode($value);
                $data = Yii::$app->getSecurity()->validateData($value, $this->cookieValidationKey);
                if ($data === false) {
                    continue;
                }
                $data = @unserialize($data);
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    $cookies[$name] = Yii::createObject([
                        'class' => 'yii\web\Cookie',
                        'name' => $name,
                        'value' => $data[1],
                        'expire' => null,
                    ]);
                }
            }
        } else {
            foreach ($this->getCookieParams() as $name => $value) {
                $cookies[$name] = Yii::createObject([
                    'class' => 'yii\web\Cookie',
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }

        return $cookies;
    }

    /**
     * @return array
     */
    public function getCookieParams(): array
    {
        return $this->_cookieParams;
    }

    /**
     * @param array $values
     */
    public function setCookieParams(array $values): void
    {
        $this->_cookieParams = $values;
    }
}
