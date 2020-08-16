<?php declare(strict_types=1);

namespace Teleclient\RequestSimulator;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

use Laminas\Diactoros\Request\Serializer as RequestSerializer;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\Serializer as ResponseSerializer;
use Laminas\Diactoros\ServerRequest;


final class RequestSimulatorMiddleware implements MiddlewareInterface
{
    const PARAM = 'simulated-request';

    public function __construct() {

    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $body = (string) $request->getBody();
            $parsedBody = $this->parseBody($body);
            if (is_array($parsedBody) && isset($parsedBody[self::PARAM])) {
                $requestToSimulate   = $parsedBody[self::PARAM];
                //echo(PHP_EOL.'------------------------------------'.PHP_EOL);
                //print_r($requestToSimulate.PHP_EOL);
                //echo('------------------------------------'.PHP_EOL);
                $deserializedRequest = RequestSerializer::fromString($requestToSimulate);
                $request = new ServerRequest(
                    $request->getServerParams(),
                    $request->getUploadedFiles(),
                    $deserializedRequest->getUri(),
                    $deserializedRequest->getMethod(),
                    $deserializedRequest->getBody(),
                    $deserializedRequest->getHeaders()
                );
            }
        }
        $requestAsString = RequestSerializer::toString($request);
        $response = $handler->handle($request);
        $responseAsString = ResponseSerializer::toString($response);
        $html = sprintf($this->getHtmlTemplate(), self::PARAM, $requestAsString, $responseAsString);
        return new HtmlResponse($html);
    }

    private function parseBody($body)
    {
        $params = [];
        parse_str($body, $params);
        return $params;
    }

    private function getHtmlTemplate()
    {
        return '<html>'
                . '<body>'
                . '<h1>Request simulator</h1>'
                . '<form method="post">'
                . '<h2>Request</h2>'
                . '<textarea name="%s">%s</textarea>'
                . '<input type="submit" />'
                . '</form>'
                . '<h2>Response</h2>'
                . '<code>%s</code>'
                . '</body>'
                . '</html>';
    }
}
