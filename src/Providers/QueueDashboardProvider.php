<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Providers;

use MonkeysLegion\Core\Attribute\Provider;
use MonkeysLegion\DI\Traits\ContainerAware;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Dashboard\DashboardController;
use MonkeysLegion\Router\ControllerScanner;
use MonkeysLegion\Router\Router;
use MonkeysLegion\Template\Loader;

/**
 * QueueDashboardProvider registers the queue dashboard components.
 */
#[Provider]
final class QueueDashboardProvider
{
    use ContainerAware;
    public const VIEW_NAMESPACE = 'queue-dashboard';

    public function register(Loader $loader, ControllerScanner $controllerScanner): void
    {
        // Resolve prefix from configuration before scanning
        if (!defined('ML_QUEUE_DASHBOARD_PREFIX')) {
            $prefix = 'ml-queue';
            if ($this->has(QueueInterface::class)) {
                $queue = $this->resolve(QueueInterface::class);
                $prefix = $queue->getSettings()['path'] ?? 'ml-queue';
            }
            define('ML_QUEUE_DASHBOARD_PREFIX', '/' . ltrim($prefix, '/') . '/dashboard');
        }

        $loader->addNamespace(self::VIEW_NAMESPACE, $this->getViewsPath());
        $controllerScanner->scan(__DIR__ . '/../Dashboard', 'MonkeysLegion\Queue\Dashboard');
    }

    public static function getViewsPath(): string
    {
        return dirname(__DIR__) . '/Template/views';
    }

    public static function getDashboardView(): string
    {
        return self::VIEW_NAMESPACE . '::dashboard.index';
    }

    /**
     * @return array<class-string>
     */
    public static function getControllers(): array
    {
        return [
            DashboardController::class,
        ];
    }
}
