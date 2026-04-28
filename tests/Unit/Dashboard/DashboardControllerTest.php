<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Tests\Unit\Dashboard;

use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Queue\Dashboard\DashboardController;
use MonkeysLegion\Queue\Providers\QueueDashboardProvider;
use MonkeysLegion\Queue\Tests\Fixtures\MemoryQueue;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Compiler;
use MonkeysLegion\Template\Loader;
use MonkeysLegion\Template\Parser;
use MonkeysLegion\Template\Renderer;

use Psr\Http\Message\ServerRequestInterface;

use PHPUnit\Framework\TestCase;

/**
 * DashboardControllerTest covers the web interface for queue management.
 */
final class DashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $container = new \MonkeysLegion\DI\Container();
        \MonkeysLegion\DI\Container::setInstance($container);
    }

    public function testIndexReturnsHtmlResponse(): void
    {
        $queue = new MemoryQueue(['default_queue' => 'default', 'failed_queue' => 'failed']);
        $controller = $this->makeController($queue);

        $result = $controller->index($this->makeRequest());

        $this->assertSame(200, $result->getStatusCode());
        $this->assertStringContainsString('text/html', $result->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('MonkeysLegion', (string)$result->getBody());
        $this->assertStringContainsString('Queue Performance Cluster', (string)$result->getBody());
    }

    public function testDashboardViewPathReturnsProviderViewPath(): void
    {
        $queue = new MemoryQueue(['default_queue' => 'default', 'failed_queue' => 'failed']);
        $controller = $this->makeController($queue);

        $this->assertSame('queue-dashboard::dashboard.index', $controller->dashboardViewPath());
    }

    public function testControllerHasRoutePrefixAttribute(): void
    {
        $reflection = new \ReflectionClass(DashboardController::class);
        $attributes = $reflection->getAttributes(RoutePrefix::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('/ml-queue/dashboard', $attributes[0]->newInstance()->prefix);
    }

    public function testIndexMethodHasRouteAttribute(): void
    {
        $reflection = new \ReflectionMethod(DashboardController::class, 'index');
        $attributes = $reflection->getAttributes(Route::class);

        $this->assertCount(1, $attributes);

        $route = $attributes[0]->newInstance();
        $this->assertSame(['GET'], $route->methods);
        $this->assertSame('/', $route->path);
        $this->assertSame('queue.dashboard.index', $route->name);
    }

    public function testAllTabMethodsReturnSuccessResponses(): void
    {
        $queue = new MemoryQueue(['default_queue' => 'default', 'failed_queue' => 'failed']);
        $queue->jobs['default'][] = ['id' => 'job1', 'job' => 'DemoJob', 'attempts' => 1, 'created_at' => 'now'];
        $queue->failed[] = ['id' => 'f1', 'job' => 'FailJob', 'attempts' => 2, 'failed_at' => 'now'];
        $controller = $this->makeController($queue);

        $this->assertSame(200, $controller->queues($this->makeRequest(query: ['queue' => 'default']))->getStatusCode());
        $this->assertSame(200, $controller->delayed($this->makeRequest(query: ['queue' => 'default']))->getStatusCode());
        $this->assertSame(200, $controller->failed($this->makeRequest(query: ['limit' => '20']))->getStatusCode());
        $this->assertSame(200, $controller->maintenance($this->makeRequest())->getStatusCode());
    }

    public function testActionMethodsMutateQueueAndRedirect(): void
    {
        $queue = new MemoryQueue(['default_queue' => 'default', 'failed_queue' => 'failed']);
        $queue->failed = [
            ['id' => 'f1', 'job' => 'FailJobA', 'attempts' => 1, 'failed_at' => 'now'],
            ['id' => 'f2', 'job' => 'FailJobB', 'attempts' => 2, 'failed_at' => 'now'],
        ];
        $queue->delayed['default'][] = ['run_at' => time() + 10, 'data' => ['id' => 'd1', 'job' => 'DelayedJob']];
        $controller = $this->makeController($queue);

        $retryResponse = $controller->retryFailed($this->makeRequest(method: 'POST', body: ['limit' => '1']));
        $this->assertSame(302, $retryResponse->getStatusCode());
        $this->assertSame('/ml-queue/dashboard/failed', $retryResponse->getHeaderLine('Location'));
        $this->assertCount(1, $queue->lastRetried);

        $deleteResponse = $controller->deleteFailed($this->makeRequest(method: 'POST', body: ['job_ids' => ['f2']]));
        $this->assertSame(302, $deleteResponse->getStatusCode());
        $this->assertSame(['/ml-queue/dashboard/failed'], [$deleteResponse->getHeaderLine('Location')]);
        $this->assertSame(['f2'], $queue->lastRemovedFailed);

        $clearFailedResponse = $controller->clearFailed($this->makeRequest(method: 'POST'));
        $this->assertSame(302, $clearFailedResponse->getStatusCode());
        $this->assertSame(0, count($queue->failed));

        $clearQueueResponse = $controller->clearQueue($this->makeRequest(method: 'POST', body: ['queue' => 'default']));
        $this->assertSame(302, $clearQueueResponse->getStatusCode());
        $this->assertSame('default', $queue->lastClearedQueue);

        $processDelayedResponse = $controller->processDelayed($this->makeRequest(method: 'POST', body: ['queue' => 'default']));
        $this->assertSame(302, $processDelayedResponse->getStatusCode());
        $this->assertSame('default', $queue->lastDelayedProcessedQueue);

        $purgeResponse = $controller->purge($this->makeRequest(method: 'POST'));
        $this->assertSame(302, $purgeResponse->getStatusCode());
        $this->assertTrue($queue->wasPurged);
    }

    public function testControllerHasRouteAttributesForAllPublicEndpoints(): void
    {
        $expected = [
            'index',
            'queues',
            'delayed',
            'failed',
            'maintenance',
            'retryFailed',
            'deleteFailed',
            'clearFailed',
            'clearQueue',
            'purge',
            'processDelayed',
        ];

        foreach ($expected as $methodName) {
            $method = new \ReflectionMethod(DashboardController::class, $methodName);
            $attributes = $method->getAttributes(Route::class);
            $this->assertCount(1, $attributes, "{$methodName} should have exactly one Route attribute");
        }
    }

    private function makeController(MemoryQueue $queue): DashboardController
    {
        $cacheDir = sys_get_temp_dir() . '/monkeyslegion-queue-dashboard-tests';
        \MonkeysLegion\DI\Container::instance()->bind(\MonkeysLegion\Queue\Contracts\QueueInterface::class, get_class($queue));
        \MonkeysLegion\DI\Container::instance()->set(get_class($queue), $queue);

        $loader = new Loader(QueueDashboardProvider::getViewsPath(), $cacheDir);
        $routes = new \MonkeysLegion\Router\RouteCollection();
        $router = new \MonkeysLegion\Router\Router($routes);
        $scanner = new \MonkeysLegion\Router\ControllerScanner($router);
        (new QueueDashboardProvider())->register($loader, $scanner);
        $parser = new Parser();
        $compiler = new Compiler($parser);
        $renderer = new Renderer($parser, $compiler, $loader, true, $cacheDir);

        return new DashboardController($queue, $renderer);
    }

    private function makeRequest(
        string $method = 'GET',
        string $uri = 'http://localhost/ml-queue/dashboard',
        array $query = [],
        array $body = [],
    ): ServerRequestInterface {
        $request = new ServerRequest(
            $method,
            new Uri($uri),
            Stream::createFromString('')
        );

        if ($query !== []) {
            $request = $request->withQueryParams($query);
        }

        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $request;
    }
}
