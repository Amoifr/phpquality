<?php

declare(strict_types=1);

namespace PhpQuality\DataCollector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CallstackTracer implements EventSubscriberInterface
{
    /** @var array<string, true> */
    private array $tracedFiles = [];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelEvent', 1000],
            KernelEvents::CONTROLLER => ['onKernelEvent', 1000],
            KernelEvents::VIEW => ['onKernelEvent', 1000],
            KernelEvents::RESPONSE => ['onKernelEvent', 1000],
            KernelEvents::EXCEPTION => ['onKernelEvent', 1000],
        ];
    }

    public function onKernelEvent(RequestEvent|ControllerEvent|ViewEvent|ResponseEvent|ExceptionEvent $event): void
    {
        $this->captureBacktrace();
    }

    public function captureBacktrace(): void
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($backtrace as $frame) {
            if (isset($frame['file'])) {
                $this->tracedFiles[$frame['file']] = true;
            }
        }
    }

    /**
     * @return array<string>
     */
    public function getTracedFiles(): array
    {
        return array_keys($this->tracedFiles);
    }

    public function reset(): void
    {
        $this->tracedFiles = [];
    }
}
