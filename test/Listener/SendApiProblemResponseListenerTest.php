<?php

namespace LaminasTest\ApiTools\ApiProblem\Listener;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ApiProblem\Exception\DomainException;
use Laminas\ApiTools\ApiProblem\Listener\SendApiProblemResponseListener;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\ResponseSender\ResponseSenderInterface;
use Laminas\Mvc\ResponseSender\SendResponseEvent;
use PHPUnit\Framework\TestCase;

use function count;
use function json_decode;
use function ob_get_clean;
use function ob_start;

class SendApiProblemResponseListenerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->exception  = new DomainException('Random error', 400);
        $this->apiProblem = new ApiProblem(400, $this->exception);
        $this->response   = new ApiProblemResponse($this->apiProblem);
        $this->event      = new SendResponseEvent();
        $this->event->setResponse($this->response);
        $this->listener = new SendApiProblemResponseListener();
    }

    public function testListenerImplementsResponseSenderInterface()
    {
        $this->assertInstanceOf(ResponseSenderInterface::class, $this->listener);
    }

    public function testDisplayExceptionsFlagIsFalseByDefault()
    {
        $this->assertFalse($this->listener->displayExceptions());
    }

    /**
     * @depends testDisplayExceptionsFlagIsFalseByDefault
     */
    public function testDisplayExceptionsFlagIsMutable()
    {
        $this->listener->setDisplayExceptions(true);
        $this->assertTrue($this->listener->displayExceptions());
    }

    /**
     * @depends testDisplayExceptionsFlagIsFalseByDefault
     */
    public function testSendContentDoesNotRenderExceptionsByDefault()
    {
        ob_start();
        $this->listener->sendContent($this->event);
        $contents = ob_get_clean();
        $this->assertIsString($contents);
        $data = json_decode($contents, true);
        $this->assertStringNotContainsString("\n", $data['detail']);
        $this->assertStringNotContainsString($this->exception->getTraceAsString(), $data['detail']);
    }

    public function testEnablingDisplayExceptionFlagRendersExceptionStackTrace()
    {
        $this->listener->setDisplayExceptions(true);
        ob_start();
        $this->listener->sendContent($this->event);
        $contents = ob_get_clean();
        $this->assertIsString($contents);
        $data = json_decode($contents, true);
        $this->assertArrayHasKey('trace', $data);
        $this->assertIsArray($data['trace']);
        $this->assertGreaterThanOrEqual(1, count($data['trace']));
    }

    public function testSendContentDoesNothingIfEventDoesNotContainApiProblemResponse()
    {
        $this->event->setResponse(new HttpResponse());
        ob_start();
        $this->listener->sendContent($this->event);
        $contents = ob_get_clean();
        $this->assertIsString($contents);
        $this->assertEmpty($contents);
    }

    public function testSendHeadersMergesApplicationAndProblemHttpHeaders()
    {
        $appResponse = new HttpResponse();
        $appResponse->getHeaders()->addHeaderLine('Access-Control-Allow-Origin', '*');

        $listener = new SendApiProblemResponseListener();
        $listener->setApplicationResponse($appResponse);

        ob_start();
        $listener->sendHeaders($this->event);
        ob_get_clean();

        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Access-Control-Allow-Origin'));
    }
}
