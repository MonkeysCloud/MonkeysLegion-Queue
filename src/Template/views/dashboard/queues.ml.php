@extends('queue-dashboard::dashboard.layout')

@section('view_title')Worker Channels@endsection
@section('header_title')Queue: {{ $selected_queue ?? 'default' }}@endsection
@section('header_meta')Tracking {{ count($jobs ?? []) }} items@endsection

@section('content')
@php
    $ready = $stats['ready'] ?? 0;
    $processing = $stats['processing'] ?? 0;
    $delayed = $stats['delayed'] ?? 0;
    $failed = $stats['failed'] ?? 0;
@endphp

<div class="card" style="border-top: 4px solid var(--accent);">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h3 class="brand-name">Channel Monitor</h3>
            <p class="text-xs text-muted mt-1">Live view of the current processing pipeline.</p>
        </div>
        <form action="{{ $dashboard_prefix }}/queues" method="get" class="flex gap-2">
            @csrf
            <select name="queue" class="btn btn-outline">
                @foreach ($queues as $q)
                    @if ((string)$q !== 'failed')
                        <option value="{{ (string)$q }}" @if($selected_queue === (string)$q) selected @endif>
                            {{ (string)$q }}
                        </option>
                    @endif
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">Switch</button>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Job Signature</th>
                    <th>Attempts</th>
                    <th>Ingested At</th>
                    <th style="text-align: right;">Observation</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jobs as $job)
                <tr>
                    <td>
                        <span class="font-semibold" style="word-break: break-all; font-size: 1.1rem;">{{ $job['job'] ?? 'Internal Task' }}</span>
                    </td>
                    <td>
                        <span class="badge badge-neutral">{{ $job['attempts'] ?? 0 }} Logs</span>
                    </td>
                    <td>
                        <span class="text-xs text-muted">{{ date('Y-m-d H:i:s', (int)($job['created_at'] ?? time())) }}</span>
                    </td>
                    <td style="text-align: right;">
                        <a href="{{ $dashboard_prefix }}/job?id={{ $job['id'] }}&queue={{ urlencode($selected_queue) }}" class="btn btn-outline" style="padding: 0.53rem 1.25rem; font-size: 0.9rem;">Inspect</a>
                    </td>
                </tr>
                @endforeach
                
                @if (empty($jobs))
                <tr>
                    <td colspan="4" style="text-align: center; padding: 6rem;">
                        <div class="flex flex-col items-center gap-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.15;"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                            <p class="text-muted font-medium">Channel "{{ $selected_queue }}" is currently balanced.</p>
                        </div>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
