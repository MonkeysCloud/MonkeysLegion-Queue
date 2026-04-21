@extends('queue-dashboard::dashboard.layout')

@section('view_title')Delayed Scheduling@endsection
@section('header_title')Delayed Registry: {{ $selected_queue ?? 'default' }}@endsection
@section('header_meta')
    Waiting for {{ $delayed_count ?? 0 }} operations
@endsection

@section('content')
@php
    $delayed = $delayed_count ?? 0;
    $ready = $stats['ready'] ?? 0;
    $processing = $stats['processing'] ?? 0;
    $failed = $stats['failed'] ?? 0;
    
    $warningCardStyle = ($delayed > 0) ? 'border-bottom: 4px solid var(--warning); background: linear-gradient(to bottom right, var(--bg-secondary), rgba(245, 158, 11, 0.08))' : 'border-bottom: 4px solid var(--border)';
    $warningValueStyle = ($delayed > 0) ? 'color: var(--warning)' : '';
@endphp

<div class="card" style="border-top: 4px solid var(--warning);">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h3 class="brand-name">Schedule Intervention</h3>
            <p class="text-xs text-muted mt-1">Force processing allows you to bypass programmed delays and execute jobs immediately.</p>
        </div>
        
        <form method="post" action="{{ $dashboard_prefix }}/delayed/process">
            @csrf
            <input type="hidden" name="queue" value="{{ $selected_queue ?? 'default' }}">
            <button type="submit" class="btn btn-primary" style="background-color: var(--warning); border-color: var(--warning);" @if($delayed === 0) disabled @endif>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/></svg>
                Deploy Scheduled
            </button>
        </form>
    </div>

    @if ($delayed === 0)
    <div class="text-muted" style="text-align: center; padding: 6rem;">
        <div class="flex flex-col items-center gap-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.15;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <p class="font-medium">The postponement registry for "{{ $selected_queue ?? 'default' }}" is deep empty.</p>
        </div>
    </div>
    @else
    <div style="background-color: rgba(245, 158, 11, 0.03); border-left: 4px solid var(--warning); border-radius: 1rem; padding: 1.5rem; color: var(--text-primary); display: flex; gap: 1rem; align-items: center;">
        <div style="background: rgba(245, 158, 11, 0.1); padding: 0.75rem; border-radius: 0.75rem; color: var(--warning);">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div>
            <span class="text-base font-semibold">Active Scheduling</span>
            <p class="text-sm text-muted mt-1">There are currently {{ $delayed }} nodes awaiting their execution window.</p>
        </div>
    </div>
    @endif
</div>
@endsection
