<?php declare(strict_types=1);

namespace TeleclientTest\RequestSimulator;

use Teleclient\RequestSimulator\RequestSimulatorMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use PHPUnit\Framework\TestCase;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\Uri;


class RequestSimulatorMiddlewareTest extends TestCase
{
    private $middleware;

    protected function setUp(): void
    {
        $this->middleware = new RequestSimulatorMiddleware();
    }

    public function testNotSimulateRequest()
    {
        $request = new ServerRequest(
            [],   // array $serverParams
            [],   // $uploadedFiles
            new Uri(), 
            'GET',
            //$body = 'php://input',
            //array $headers = [],
            //array $cookies = [],
            //array $queryParams = [],
            //$parsedBody = null,
            //string $protocol = '1.1'
        );
        $responseBody = json_encode([
            'boo' => 'foo'
        ]);
        $response = new Response(
            'php://memory', 
            200, 
            ['content-type' => 'application/json']
        );
        $response->getBody()->write($responseBody);

        $next = new class($response) implements RequestHandlerInterface {
            private $response;
            public function __construct($response) {
                $this->response = $response;
            }
            public function handle(ServerRequestInterface $request): ResponseInterface {
                return $this->response;
            }
        };

        /* @var $result ResponseInterface */
        $result = $this->middleware->process($request, $next);

        $body = (string) $result->getBody();

        //echo(PHP_EOL.'------------------------'.PHP_EOL);
        //echo($result->getHeaderLine('Content-type').PHP_EOL);
        //echo('------------------------'.PHP_EOL);
        //echo($body.PHP_EOL);
        //echo('------------------------'.PHP_EOL);
        $this->assertStringContainsString('text/html', $result->getHeaderLine('Content-type'));
        $this->assertStringContainsString('{"boo":"foo"}', $body);
        $this->assertStringContainsString('<html>', $body);
    }


    public function testSimulateRequest()
    {
        $simulatedRequest = "DELETE / HTTP/1.1\r\nBar: faz\r\nHost: php-middleware.com";
        $postBody = http_build_query([
            RequestSimulatorMiddleware::PARAM => $simulatedRequest
        ]);

        $stream = new Stream('php://memory', 'wb+');
        $stream->write($postBody);
        $request = new ServerRequest(
            [], 
            [], 
            new Uri(), 
            'POST', 
            $stream,
            ['Content-type' => 'application/x-www-form-urlencoded']
        );

        $responseBody = json_encode(['boo' => 'foo']);
        $response = new Response('php://memory', 200, ['content-type' => 'application/json']);
        $response->getBody()->write($responseBody);

        $next = new class($this, $response) implements RequestHandlerInterface {
            private $middleware;
            private $response;
            public function __construct($middleware, $response) {
                $this->middleware = $middleware;
                $this->response   = $response;
            }
            public function handle(ServerRequestInterface $request): ResponseInterface {
                $this->middleware->assertSame('DELETE', $request->getMethod());
                $this->middleware->assertSame('faz',    $request->getHeaderLine('Bar'));
                return $this->response;
            }
        };

        /* @var $result ResponseInterface */
        $result = $this->middleware->process($request, $next);

        $body = (string) $result->getBody();

        $this->assertStringContainsString('text/html', $result->getHeaderLine('Content-type'));
        $this->assertStringContainsString('{"boo":"foo"}', $body);
        $this->assertStringContainsString('<html>', $body);
        $this->assertStringContainsString('DELETE / HTTP/1.1', $body);
    }
}
