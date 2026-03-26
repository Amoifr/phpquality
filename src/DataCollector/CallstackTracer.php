<?php

declare(strict_types=1);

namespace PhpQuality\DataCollector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CallstackTracer implements EventSubscriberInterface
{
    /** @var array<string> Files included at request start (after routing) */
    private array $filesAtRequestStart = [];

    /** @var array<string> Files loaded during request handling */
    private array $requestFiles = [];

    /** @var string|null Controller class file */
    private ?string $controllerFile = null;

    public static function getSubscribedEvents(): array
    {
        return [
            // Very low priority on REQUEST: after routing, capture baseline
            KernelEvents::REQUEST => ['onRequest', -1000],
            // Capture controller info
            KernelEvents::CONTROLLER => ['onController', 0],
            // Very low priority on RESPONSE: capture final state
            KernelEvents::RESPONSE => ['onResponse', -1000],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        // Capture baseline after routing but before controller resolution
        $this->filesAtRequestStart = get_included_files();
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Get controller file
        $controller = $event->getController();
        if (is_array($controller) && isset($controller[0]) && is_object($controller[0])) {
            $reflection = new \ReflectionClass($controller[0]);
            $this->controllerFile = $reflection->getFileName() ?: null;
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $reflection = new \ReflectionClass($controller);
            $this->controllerFile = $reflection->getFileName() ?: null;
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Get all files at end of request
        $filesAtEnd = get_included_files();

        // Files loaded during request = difference from baseline
        $this->requestFiles = array_values(
            array_diff($filesAtEnd, $this->filesAtRequestStart)
        );

        // Ensure controller file is first
        if ($this->controllerFile !== null) {
            // Remove if present elsewhere
            $this->requestFiles = array_values(
                array_filter($this->requestFiles, fn($f) => $f !== $this->controllerFile)
            );
            // Add at beginning
            array_unshift($this->requestFiles, $this->controllerFile);
        }
    }

    /**
     * @return array<string>
     */
    public function getTracedFiles(): array
    {
        return $this->requestFiles;
    }

    public function getControllerFile(): ?string
    {
        return $this->controllerFile;
    }

    public function reset(): void
    {
        $this->filesAtRequestStart = [];
        $this->requestFiles = [];
        $this->controllerFile = null;
    }
}
