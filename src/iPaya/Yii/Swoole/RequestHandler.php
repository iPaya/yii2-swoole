<?php
/**
 * @link http://www.ipaya.cn/
 * @copyright Copyright (c) 2018 ipaya.cn
 */

namespace iPaya\Yii\Swoole;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use yii\base\Application;

class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var HttpServer
     */
    private $server;

    public function __construct(HttpServer $server)
    {
        $this->server = $server;
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $psrRequest): ResponseInterface
    {
        $sever = $this->server;
        $app = $sever->getApplication();

        $app->setComponents($sever->getApplicationComponents());

        $request = Request::createFromPsrRequest($this->server, $psrRequest);

        $app->set('request', $request);

        $app->state = Application::STATE_BEFORE_REQUEST;
        $app->trigger(Application::EVENT_BEFORE_REQUEST);

        $app->state = Application::STATE_HANDLING_REQUEST;
        /** @var Response $response */
        $response = $app->handleRequest($request);

        $app->state = Application::STATE_AFTER_REQUEST;
        $app->trigger(Application::EVENT_AFTER_REQUEST);

        $app->state = Application::STATE_SENDING_RESPONSE;

        $psrResponse = $response->toPsrResponse($request->enableCookieValidation, $request->cookieValidationKey);

        $app->state = Application::STATE_END;

        return $psrResponse;
    }

}
