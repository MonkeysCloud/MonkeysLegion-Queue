<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Providers;

use MonkeysLegion\Queue\Dashboard\DashboardController;
use MonkeysLegion\Queue\Providers\QueueDashboardProvider;
use MonkeysLegion\Template\Loader;

use PHPUnit\Framework\TestCase;

/**
 * QueueDashboardProviderTest ensures correctly dashboard provider registration.
 */
final class QueueDashboardProviderTest extends TestCase
{
    public function testGetViewsPathPointsToDashboardTemplateDirectory(): void
    {
        $provider = new QueueDashboardProvider();

        $this->assertSame(
            dirname(__DIR__, 3) . '/src/Template/views',
            $provider->getViewsPath()
        );
    }

    public function testGetDashboardViewReturnsNamespacedPath(): void
    {
        $provider = new QueueDashboardProvider();

        $this->assertSame('queue-dashboard::dashboard.index', $provider->getDashboardView());
    }

    public function testRegisterAddsDashboardNamespaceToViewEngine(): void
    {
        $provider = new QueueDashboardProvider();
        $loader = $this->getMockBuilder(Loader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addNamespace'])
            ->getMock();

        $loader->expects($this->once())
            ->method('addNamespace')
            ->with('queue-dashboard', $provider->getViewsPath());

        $routes = new \MonkeysLegion\Router\RouteCollection();
        $router = new \MonkeysLegion\Router\Router($routes);
        $scanner = new \MonkeysLegion\Router\ControllerScanner($router);
        $provider->register($loader, $scanner);
    }

    public function testGetControllersReturnsDashboardControllerClass(): void
    {
        $this->assertSame(
            [DashboardController::class],
            QueueDashboardProvider::getControllers()
        );
    }
}
