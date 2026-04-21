@extends('queue-dashboard::dashboard.layout')

@section('view_title')Failure Archive@endsection
@section('header_title')Archive Monitor@endsection
@section('header_meta')Total Failures: {{ $failed_count ?? 0 }}@endsection

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h3 class="brand-name">Incident Management</h3>
        <p class="text-xs text-muted mt-1">Review and recover jobs that failed during execution.</p>
    </div>
    <div class="flex gap-4">
        <form method="post" action="{{ $dashboard_prefix }}/failed/retry">
            @csrf
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                Resume All
            </button>
        </form>
        <form method="post" action="{{ $dashboard_prefix }}/failed/clear" data-confirm="Are you sure you want to permanently wipe the entire failure archive?" data-confirm-title="Wipe Archive">
            @csrf
            <button type="submit" class="btn btn-danger" style="padding: 0.75rem 2rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                Wipe Archive
            </button>
        </form>
    </div>
</div>

<div class="card" style="border-top: 4px solid var(--danger);">
    <div class="table-container">
        <form id="bulk-actions-form" method="post" action="{{ $dashboard_prefix }}/failed/delete" data-confirm="Are you sure you want to delete the selected fail incidents? This action is irreversible." data-confirm-title="Delete Selected Incidents">
        @csrf
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;"><input type="checkbox" id="select-all" style="width: 20px; height: 20px; cursor: pointer;"></th>
                    <th>Job Origin</th>
                    <th>Execution Error</th>
                    <th style="text-align: right;">Observation</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($failed_jobs as $job)
                <tr>
                    <td><input type="checkbox" name="job_ids[]" value="{{ $job['id'] ?? '' }}" class="job-checkbox" style="width: 18px; height: 18px; cursor: pointer;"></td>
                    <td>
                        <div class="flex flex-col gap-1">
                            <span class="font-semibold" style="font-size: 1.1rem;">{{ $job['job'] ?? 'Unknown Source' }}</span>
                            <span class="text-xs text-muted">ID: {{ substr($job['id'] ?? '', 0, 16) }}...</span>
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-col gap-1">
                            <p class="text-sm font-medium" style="color: var(--danger); max-width: 500px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                @if (is_array($job['exception'] ?? null))
                                    {{ $job['exception']['message'] ?? 'Critical Fault' }}
                                @else
                                    {{ $job['exception'] ?? 'Undefined Exception' }}
                                @endif
                            </p>
                            <span class="text-xs text-muted">Failed at: @if(isset($job['failed_at'])) {{ date('H:i:s', (int)$job['failed_at']) }} @else Just now @endif</span>
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <a href="{{ $dashboard_prefix }}/job?id={{ $job['id'] }}&queue=failed" class="btn btn-outline" style="padding: 0.5rem 1.5rem; font-size: 0.9rem;">Diagnose</a>
                    </td>
                </tr>
                @endforeach
                
                @if (empty($failed_jobs))
                <tr>
                    <td colspan="4" style="text-align: center; padding: 6rem;">
                        <div class="flex flex-col items-center gap-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.15; color: var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <p class="text-muted font-medium">Failure archive is currently empty.</p>
                        </div>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
        </form>
    </div>
    
    @if (!empty($failed_jobs))
    <div class="mt-6 flex gap-4">
        <button type="submit" form="bulk-actions-form" class="btn btn-outline btn-danger" style="background: transparent; padding: 0.75rem 2rem;">Delete Selected Incidents</button>
    </div>
    @endif
</div>

@push('scripts')
<script>
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.job-checkbox');
    
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        });
    }
</script>
@endpush
@endsection
