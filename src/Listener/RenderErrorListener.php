<?php

namespace Laminas\ApiTools\ApiProblem\Listener;

use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Exception\ExceptionInterface as ViewExceptionInterface;
use Throwable;

use function json_encode;

/**
 * RenderErrorListener.
 *
 * Provides a listener on the render.error event, at high priority.
 */
class RenderErrorListener extends AbstractListenerAggregate
{
    /** @var bool */
    protected $displayExceptions = false;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'onRenderError'], 100);
    }

    /**
     * @param bool $flag
     * @return RenderErrorListener
     */
    public function setDisplayExceptions($flag)
    {
        $this->displayExceptions = (bool) $flag;

        return $this;
    }

    /**
     * Handle rendering errors.
     *
     * Rendering errors are usually due to trying to render a template in
     * the PhpRenderer, when we have no templates.
     *
     * As such, report as an unacceptable response.
     */
    public function onRenderError(MvcEvent $e)
    {
        $response    = $e->getResponse();
        $status      = 406;
        $title       = 'Not Acceptable';
        $describedBy = 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html';
        $detail      = 'Your request could not be resolved to an acceptable representation.';
        $details     = false;

        $exception = $e->getParam('exception');
        if (
            ($exception instanceof Throwable || $exception instanceof Exception)
            && ! $exception instanceof ViewExceptionInterface
        ) {
            $code = $exception->getCode();
            if ($code >= 100 && $code <= 600) {
                $status = $code;
            } else {
                $status = 500;
            }
            $title   = 'Unexpected error';
            $detail  = $exception->getMessage();
            $details = [
                'code'    => $exception->getCode(),
                'message' => $exception->getMessage(),
                'trace'   => $exception->getTraceAsString(),
            ];
        }

        $payload = [
            'status'      => $status,
            'title'       => $title,
            'describedBy' => $describedBy,
            'detail'      => $detail,
        ];
        if ($details && $this->displayExceptions) {
            $payload['details'] = $details;
        }

        $response->getHeaders()->addHeaderLine('content-type', ApiProblem::CONTENT_TYPE);
        $response->setStatusCode($status);
        $response->setContent(json_encode($payload));

        $e->stopPropagation();
    }
}
