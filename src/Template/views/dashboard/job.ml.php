@extends('queue-dashboard::dashboard.layout')

@section('view_title')Job Details@endsection
@section('header_title'){{ $job['job'] ?? 'Job Details' }}@endsection
@section('header_meta')Channel: {{ $queue }}@endsection

@section('content')
<div class="mb-8">
    <a href="{{ $dashboard_prefix }}/queues?queue={{ urlencode($queue) }}" class="btn btn-outline">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Back to Channel
    </a>
</div>

<div class="flex" style="flex-wrap: wrap; gap: 2rem; align-items: start;">
    <!-- Main Payload Column -->
    <div style="flex: 1; min-width: 600px;">
        <div class="card" style="border-top: 4px solid var(--accent);">
            <div class="flex justify-between items-center mb-6">
                <h3 class="brand-name">Payload Content</h3>
                <span class="badge badge-neutral">JSON SCHEMA</span>
            </div>
            <pre>{{ json_encode($job['payload'] ?? [], JSON_PRETTY_PRINT) }}</pre>
        </div>

        @if (!empty($job['exception']))
        <div class="card" style="border-top: 4px solid var(--danger);">
            <div class="flex items-center gap-3 mb-6">
                <div style="padding: 0.5rem; background: rgba(244, 63, 94, 0.1); border-radius: 0.5rem; color: var(--danger);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h3 class="brand-name">Stack Trace</h3>
            </div>
            <div class="mb-4">
                <p class="font-semibold" style="word-break: break-all; color: var(--danger);">{{ $job['exception']['message'] ?? 'Critical Failure' }}</p>
                <p class="text-xs text-muted mt-2">{{ $job['exception']['file'] ?? 'unknown_file' }}:{{ $job['exception']['line'] ?? '0' }}</p>
            </div>
            <pre style="font-size: 0.85rem; color: var(--text-secondary); background: rgba(0,0,0,0.4);">{{ $job['exception']['trace'] ?? 'No trace available' }}</pre>
        </div>
        @endif
    </div>

    <!-- Metadata Column -->
    <div style="width: 380px;" class="flex flex-col gap-6">
        <div class="card">
            <h3 class="brand-name mb-6">Execution Status</h3>
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-muted uppercase tracking-widest">Job Identifier</span>
                    <p class="text-sm font-medium" style="word-break: break-all; font-family: 'JetBrains Mono';">{{ $id }}</p>
                </div>
                
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-muted uppercase tracking-widest">Current Attempts</span>
                    <div>
                        @if (($job['attempts'] ?? 0) === 0)
                            <span class="badge badge-neutral">First Attempt</span>
                        @else
                            <span class="badge badge-warning">{{ $job['attempts'] }} retries</span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-muted uppercase tracking-widest">Creation Time</span>
                    <p class="text-sm font-medium">
                        @if (isset($job['created_at']))
                            {{ date('Y-m-d H:i:s', (int)$job['created_at']) }}
                        @else
                            Recent Session
                        @endif
                    </p>
                </div>

                @if (isset($job['failed_at']))
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-muted uppercase tracking-widest">Failure Time</span>
                    <p class="text-sm font-medium" style="color: var(--danger);">
                        {{ date('Y-m-d H:i:s', (int)$job['failed_at']) }}
                    </p>
                </div>
                @endif
            </div>
        </div>

        <div class="card">
            <h3 class="brand-name mb-4">Risk Actions</h3>
            <p class="text-xs text-muted mb-6">Deleting a job will remove it permanently from the persistence layer.</p>
            <form method="post" action="{{ $dashboard_prefix }}/job/delete" data-confirm="Are you sure you want to permanently delete this job?" data-confirm-title="Confirm Deletion">
                @csrf
                <input type="hidden" name="id" value="{{ $id }}">
                <input type="hidden" name="queue" value="{{ $queue }}">
                <button type="submit" class="btn btn-danger w-full">
                    Purge Job Record
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
