<?php

declare(strict_types=1);

namespace MonkeysLegion\Queue\Dashboard;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Queue\Contracts\QueueInterface;
use MonkeysLegion\Queue\Providers\QueueDashboardProvider;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;

use Psr\Http\Message\ServerRequestInterface;

if (!defined('ML_QUEUE_DASHBOARD_PREFIX')) {
    define('ML_QUEUE_DASHBOARD_PREFIX', '/ml-queue/dashboard');
}

/**
 * DashboardController handles the web-based queue monitoring interface.
 */
#[RoutePrefix(ML_QUEUE_DASHBOARD_PREFIX)]
final class DashboardController
{
    public function __construct(
        private QueueInterface $queue,
        private Renderer $renderer,
    ) {}

    #[Route('GET', '/', name: 'queue.dashboard.index')]
    public function index(ServerRequestInterface $request): Response
    {
        return $this->render('dashboard.index', [
            'title' => 'Queue Dashboard',
            'active_tab' => 'overview',
            'message' => 'Hello, world!',
            'queues' => $this->queue->getQueues(),
            'failed_jobs' => $this->queue->countFailed(),
            'stats' => $this->buildStats(),
            'last_refresh' => date('c'),
        ]);
    }

    #[Route('GET', '/queues', name: 'queue.dashboard.queues')]
    public function queues(ServerRequestInterface $request): Response
    {
        $queueName = (string)($request->getQueryParams()['queue'] ?? 'default');
        $limit = $this->resolveLimit($request, 20);

        return $this->render('dashboard.queues', [
            'title' => 'Queue Dashboard - Queues',
            'active_tab' => 'queues',
            'selected_queue' => $queueName,
            'queues' => $this->queue->getQueues(),
            'jobs' => $this->queue->listQueue($queueName, $limit),
            'limit' => $limit,
            'stats' => $this->buildStats($queueName),
        ]);
    }

    #[Route('GET', '/delayed', name: 'queue.dashboard.delayed')]
    public function delayed(ServerRequestInterface $request): Response
    {
        $queueName = (string)($request->getQueryParams()['queue'] ?? 'default');
        $stats = $this->buildStats($queueName);

        return $this->render('dashboard.delayed', [
            'title' => 'Queue Dashboard - Delayed',
            'active_tab' => 'delayed',
            'selected_queue' => $queueName,
            'queues' => $this->queue->getQueues(),
            'delayed_count' => (int)($stats['delayed'] ?? 0),
            'stats' => $stats,
        ]);
    }

    #[Route('GET', '/failed', name: 'queue.dashboard.failed')]
    public function failed(ServerRequestInterface $request): Response
    {
        $limit = $this->resolveLimit($request, 20);

        return $this->render('dashboard.failed', [
            'title' => 'Queue Dashboard - Failed Jobs',
            'active_tab' => 'failed',
            'failed_jobs' => $this->queue->getFailed($limit),
            'failed_count' => $this->queue->countFailed(),
            'limit' => $limit,
            'stats' => $this->buildStats(),
        ]);
    }

    #[Route('GET', '/maintenance', name: 'queue.dashboard.maintenance')]
    public function maintenance(ServerRequestInterface $request): Response
    {
        return $this->render('dashboard.maintenance', [
            'title' => 'Queue Dashboard - Maintenance',
            'active_tab' => 'maintenance',
            'queues' => $this->queue->getQueues(),
            'failed_count' => $this->queue->countFailed(),
            'stats' => $this->buildStats(),
        ]);
    }

    #[Route('GET', '/job', name: 'queue.dashboard.job')]
    public function job(ServerRequestInterface $request): Response
    {
        $id = (string)($request->getQueryParams()['id'] ?? '');
        $queue = (string)($request->getQueryParams()['queue'] ?? 'default');

        $job = $this->queue->findJob($id, $queue);

        if (!$job) {
            return $this->redirect($this->getDashboardPrefix());
        }

        return $this->render('dashboard.job', [
            'title' => 'Queue Dashboard - Job Details',
            'active_tab' => 'queues',
            'job' => $job->getData(),
            'id' => $id,
            'queue' => $queue,
        ]);
    }

    #[Route('POST', '/job/delete', name: 'queue.dashboard.job.delete')]
    public function deleteJob(ServerRequestInterface $request): Response
    {
        $parsed = $request->getParsedBody();
        $id = is_array($parsed) && isset($parsed['id']) ? (string)$parsed['id'] : '';
        $queue = is_array($parsed) && isset($parsed['queue']) ? (string)$parsed['queue'] : 'default';

        if ($id !== '') {
            $this->queue->deleteJob($id, $queue);
        }

        return $this->redirect($request->getHeaderLine('Referer') ?: $this->getDashboardPrefix() . '/queues?queue=' . rawurlencode($queue));
    }

    #[Route('POST', '/failed/retry', name: 'queue.dashboard.failed.retry')]
    public function retryFailed(ServerRequestInterface $request): Response
    {
        $limit = $this->resolveLimitFromBody($request, 100);
        $this->queue->retryFailed($limit);

        return $this->redirect($this->getDashboardPrefix() . '/failed');
    }

    #[Route('POST', '/failed/delete', name: 'queue.dashboard.failed.delete')]
    public function deleteFailed(ServerRequestInterface $request): Response
    {
        $parsed = $request->getParsedBody();
        $ids = is_array($parsed) ? ($parsed['job_ids'] ?? []) : [];
        $normalized = is_array($ids) ? array_values(array_filter(array_map('strval', $ids))) : [];
        if ($normalized !== []) {
            $this->queue->removeFailedJobs($normalized);
        }

        return $this->redirect($this->getDashboardPrefix() . '/failed');
    }

    #[Route('POST', '/failed/clear', name: 'queue.dashboard.failed.clear')]
    public function clearFailed(ServerRequestInterface $request): Response
    {
        $this->queue->clearFailed();
        return $this->redirect($this->getDashboardPrefix() . '/failed');
    }

    #[Route('POST', '/queue/clear', name: 'queue.dashboard.queue.clear')]
    public function clearQueue(ServerRequestInterface $request): Response
    {
        $parsed = $request->getParsedBody();
        $queueName = is_array($parsed) && isset($parsed['queue']) ? (string)$parsed['queue'] : 'default';
        $this->queue->clear($queueName);

        return $this->redirect($this->getDashboardPrefix() . '/maintenance');
    }

    #[Route('POST', '/queue/purge', name: 'queue.dashboard.queue.purge')]
    public function purge(ServerRequestInterface $request): Response
    {
        $this->queue->purge();
        return $this->redirect($this->getDashboardPrefix() . '/maintenance');
    }

    #[Route('POST', '/delayed/process', name: 'queue.dashboard.delayed.process')]
    public function processDelayed(ServerRequestInterface $request): Response
    {
        $parsed = $request->getParsedBody();
        $queueName = is_array($parsed) && isset($parsed['queue']) ? (string)$parsed['queue'] : 'default';
        $this->queue->processDelayedJobs($queueName);

        return $this->redirect($this->getDashboardPrefix() . '/delayed?queue=' . rawurlencode($queueName));
    }

    /**
     * Returns the configured dashboard view path reference.
     */
    public function dashboardViewPath(): string
    {
        return QueueDashboardProvider::getDashboardView();
    }

    /**
     * @return array{ready:int,processing:int,delayed:int,failed:int}
     */
    private function buildStats(string $queue = 'default'): array
    {
        $stats = $this->queue->getStats($queue);

        return [
            'ready' => (int)($stats['ready'] ?? 0),
            'processing' => (int)($stats['processing'] ?? 0),
            'delayed' => (int)($stats['delayed'] ?? 0),
            'failed' => (int)($stats['failed'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function render(string $view, array $data): Response
    {
        $prefix = $this->getDashboardPrefix();
        $data['dashboard_prefix'] = $prefix;

        $body = Stream::createFromString((string)$this->renderer->render(
            QueueDashboardProvider::VIEW_NAMESPACE . '::' . $view,
            $data
        ));

        return new Response($body, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function getDashboardPrefix(): string
    {
        $settings = $this->queue->getSettings();
        $prefix = $settings['path'] ?? 'ml-queue';

        return '/' . ltrim($prefix, '/') . '/dashboard';
    }

    private function redirect(string $location): Response
    {
        return new Response(Stream::createFromString(''), 302, ['Location' => $location]);
    }

    private function resolveLimit(ServerRequestInterface $request, int $default): int
    {
        $value = (string)($request->getQueryParams()['limit'] ?? $default);
        return $this->normalizeLimit($value, $default);
    }

    private function resolveLimitFromBody(ServerRequestInterface $request, int $default): int
    {
        $parsed = $request->getParsedBody();
        $value = is_array($parsed) ? (string)($parsed['limit'] ?? $default) : (string)$default;
        return $this->normalizeLimit($value, $default);
    }

    private function normalizeLimit(string $value, int $default): int
    {
        if (!ctype_digit($value)) {
            return $default;
        }

        $limit = (int)$value;
        if ($limit < 1) {
            return 1;
        }

        if ($limit > 200) {
            return 200;
        }

        return $limit;
    }
}
