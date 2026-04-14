@extends('queue-dashboard::dashboard.layout')

@section('view_title')System Maintenance@endsection
@section('header_title')Critical Operations Hub@endsection
@section('header_meta')Total Archive Size: {{ $failed_count ?? 0 }} incidents@endsection

@section('content')
<div class="card" style="border-top: 4px solid var(--danger);">
    <div class="flex items-center gap-5 mb-10">
        <div style="background-color: rgba(244, 63, 94, 0.1); padding: 1rem; border-radius: 1rem; color: var(--danger);">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
            <h3 class="brand-name" style="font-size: 1.5rem;">System Danger Zone</h3>
            <p class="text-base text-muted mt-1">Foundational tools for managing and purging high-volume queue data. Actions here are final.</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2.5rem;">
        <!-- Clear Specific Queue -->
        <div class="card" style="margin-bottom: 0; background-color: rgba(0,0,0,0.1); border-style: dashed;">
            <h4 class="mb-2" style="font-size: 1.25rem;">Channel Purge</h4>
            <p class="text-sm text-muted mb-8">This will immediately remove all 'ready' jobs from the selected ingestion channel.</p>
            
            <div class="flex flex-col gap-6">
            <form method="post" action="{{ $dashboard_prefix }}/queue/clear">
                @csrf
                <div class="flex flex-col gap-3">
                    <label class="text-xs font-bold text-muted uppercase tracking-widest">Select Operational Node</label>
                    <select name="queue" class="btn btn-outline" style="width: 100%; text-align: left; padding: 1rem;">
                        @foreach (($queues ?? []) as $queue)
                            <option value="{{ (string)$queue }}">{{ (string)$queue }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-danger mt-6 w-full" style="padding: 1rem;" onclick="return confirm('Wipe all ready jobs from this channel?')">Execute Purge</button>
            </form>
            </div>
        </div>

        <!-- Purge All -->
        <div class="card" style="margin-bottom: 0; background-color: rgba(244, 63, 94, 0.05); border-color: rgba(244, 63, 94, 0.2); display: flex; flex-direction: column;">
            <h4 class="mb-2" style="font-size: 1.25rem; color: var(--danger);">Cluster Core Purge</h4>
            <p class="text-sm text-muted mb-8">Total system wipes ALL jobs across ALL worker channels, including failed and scheduled records.</p>
            
            <div style="margin-top: auto;">
                <form method="post" action="{{ $dashboard_prefix }}/queue/purge" style="width: 100%;">
                    @csrf
                    <button type="submit" class="btn btn-danger w-full" style="padding: 1.25rem; background-color: var(--danger); color: white; border: none; box-shadow: 0 4px 20px rgba(244, 63, 94, 0.4);" onclick="return confirm('INITIATE TOTAL SYSTEM PURGE? This operation is irreversible!')">
                        PURGE ENTIRE CLUSTER
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
