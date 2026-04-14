@extends('queue-dashboard::dashboard.layout')

@section('view_title')Overview@endsection
@section('header_title')Queue Performance Cluster@endsection
@section('header_meta')
    @php
        $now = date('H:i:s');
    @endphp
    Internal Pulse: {{ $now }}
@endsection

@section('content')
@php
    $ready = $stats['ready'] ?? 0;
    $processing = $stats['processing'] ?? 0;
    $delayed = $stats['delayed'] ?? 0;
    $failed = $stats['failed'] ?? 0;
    
    // Explicitly handle colors in PHP to avoid directive leaks
    $failedCardStyle = ($failed > 0) ? 'border-bottom: 4px solid var(--danger); background: linear-gradient(to bottom right, var(--bg-secondary), rgba(244, 63, 94, 0.08))' : 'border-bottom: 4px solid var(--border)';
    $failedValueStyle = ($failed > 0) ? 'color: var(--danger)' : '';
@endphp

<!-- Performance Metrics -->
<div class="stats-grid">
    <div class="stat-card" style="border-bottom: 4px solid var(--accent); background: linear-gradient(to bottom right, var(--bg-secondary), rgba(99, 102, 241, 0.05));">
        <span class="stat-label">Active / Ready</span>
        <span class="stat-value">{{ $ready }}</span>
    </div>
    
    <div class="stat-card" style="border-bottom: 4px solid var(--success); background: linear-gradient(to bottom right, var(--bg-secondary), rgba(16, 185, 129, 0.05));">
        <span class="stat-label">Processing</span>
        <span class="stat-value">{{ $processing }}</span>
    </div>
    
    <div class="stat-card" style="border-bottom: 4px solid var(--warning); background: linear-gradient(to bottom right, var(--bg-secondary), rgba(245, 158, 11, 0.05));">
        <span class="stat-label">Scheduled / Delayed</span>
        <span class="stat-value">{{ $delayed }}</span>
    </div>
    
    <div class="stat-card" style="{{ $failedCardStyle }}">
        <span class="stat-label">Failure Archive</span>
        <span class="stat-value" style="{{ $failedValueStyle }}">{{ $failed }}</span>
    </div>
</div>

<!-- Operational Hierarchy -->
<div class="card" style="border-top: 4px solid var(--accent);">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h3 class="brand-name">Connected Channels</h3>
            <p class="text-xs text-muted mt-1">Registry of ingestion worker pools and their current state.</p>
        </div>
        <span class="badge badge-neutral" style="padding: 0.6rem 1.25rem; font-size: 0.85rem;">{{ is_array($queues) ? count($queues) - 1 : 0 }} Defined Pools</span>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Channel Architecture</th>
                    <th>Connectivity</th>
                    <th style="text-align: right;">Management</th>
                </tr>
            </thead>
            <tbody>
                @if (isset($queues))
                    @foreach ($queues as $queue)
                        @if ((string)$queue !== 'failed')
                        <tr>
                            <td>
                                <div class="flex items-center gap-4">
                                    <div style="width: 10px; height: 10px; background: var(--success); border-radius: 50%; box-shadow: 0 0 10px var(--success);"></div>
                                    <span class="font-semibold" style="font-size: 1.1rem;">{{ (string)$queue }}</span>
                                </div>
                            </td>
                            <td><span class="badge badge-success">Pool Verified</span></td>
                            <td style="text-align: right;">
                                <a href="{{ $dashboard_prefix }}/queues?queue={{ urlencode((string)$queue) }}" class="btn btn-outline" style="padding: 0.6rem 1.5rem;">Inspect Node</a>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>

<!-- Alerts Management -->
@if ($failed > 0)
<div class="card" style="border-left: 8px solid var(--danger); background: linear-gradient(to right, rgba(244, 63, 94, 0.05), transparent); border-radius: 1.25rem;">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-5">
            <div style="padding: 1rem; background: rgba(244, 63, 94, 0.1); border-radius: 0.75rem; color: var(--danger);">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <h4 style="color: var(--danger); font-weight: 800; font-size: 1.3rem;">Action Required: Found Failures</h4>
                <p class="text-base text-muted mt-1">Found <strong>{{ $failed }}</strong> incidents that halted execution. Recovery protocols recommended.</p>
            </div>
        </div>
        <a href="{{ $dashboard_prefix }}/failed" class="btn btn-danger" style="padding: 1rem 3rem; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(244, 63, 94, 0.3);">Analyze Failures</a>
    </div>
</div>
@endif
@endsection
