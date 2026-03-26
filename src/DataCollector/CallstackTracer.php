<?php

declare(strict_types=1);

namespace PhpQuality\DataCollector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CallstackTracer implements EventSubscriberInterface
{
    /** @var array<string> Files included before controller execution */
    private array $filesBeforeController = [];

    /** @var array<string> Files loaded during controller execution */
    private array $controllerFiles = [];

    /** @var string|null Controller class file */
    private ?string $controllerFile = null;

    public static function getSubscribedEvents(): array
    {
        return [
            // High priority: capture state BEFORE controller runs
            KernelEvents::CONTROLLER => ['onController', 1000],
            // Low priority: capture state AFTER controller runs (before response sent)
            KernelEvents::RESPONSE => ['onResponse', -1000],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        // Capture all files loaded before controller execution
        $this->filesBeforeController = get_included_files();

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
        // Get all files after controller execution
        $filesAfterController = get_included_files();

        // Files loaded during controller = difference
        $this->controllerFiles = array_values(
            array_diff($filesAfterController, $this->filesBeforeController)
        );

        // Add controller file at the beginning if not already included
        if ($this->controllerFile !== null && !in_array($this->controllerFile, $this->controllerFiles, true)) {
            array_unshift($this->controllerFiles, $this->controllerFile);
        }
    }

    /**
     * @return array<string>
     */
    public function getTracedFiles(): array
    {
        return $this->controllerFiles;
    }

    public function getControllerFile(): ?string
    {
        return $this->controllerFile;
    }

    public function reset(): void
    {
        $this->filesBeforeController = [];
        $this->controllerFiles = [];
        $this->controllerFile = null;
    }
}
