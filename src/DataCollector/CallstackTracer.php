<?php

declare(strict_types=1);

namespace PhpQuality\DataCollector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CallstackTracer implements EventSubscriberInterface
{
    /** @var string|null Controller class file */
    private ?string $controllerFile = null;

    /** @var string|null Controller class name */
    private ?string $controllerClass = null;

    /** @var string|null Controller method name */
    private ?string $controllerMethod = null;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onController', 0],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $controller = $event->getController();

        if (is_array($controller) && isset($controller[0]) && is_object($controller[0])) {
            $reflection = new \ReflectionClass($controller[0]);
            $this->controllerFile = $reflection->getFileName() ?: null;
            $this->controllerClass = $reflection->getName();
            $this->controllerMethod = $controller[1] ?? null;
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $reflection = new \ReflectionClass($controller);
            $this->controllerFile = $reflection->getFileName() ?: null;
            $this->controllerClass = $reflection->getName();
            $this->controllerMethod = '__invoke';
        }
    }

    /**
     * Get all included files with controller file first
     * @return array<string>
     */
    public function getTracedFiles(): array
    {
        $files = get_included_files();

        // Put controller file first if exists
        if ($this->controllerFile !== null) {
            $files = array_filter($files, fn($f) => $f !== $this->controllerFile);
            array_unshift($files, $this->controllerFile);
        }

        return array_values($files);
    }

    public function getControllerFile(): ?string
    {
        return $this->controllerFile;
    }

    public function getControllerInfo(): ?string
    {
        if ($this->controllerClass === null) {
            return null;
        }

        return $this->controllerClass . '::' . ($this->controllerMethod ?? '?');
    }

    public function reset(): void
    {
        $this->controllerFile = null;
        $this->controllerClass = null;
        $this->controllerMethod = null;
    }
}
