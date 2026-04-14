<nav style="margin-bottom: 16px;">
    <a class="<?= ($active_tab ?? '') === 'overview' ? 'active' : '' ?>" href="{{ $dashboard_prefix }}">Overview</a>
    <a class="<?= ($active_tab ?? '') === 'queues' ? 'active' : '' ?>" href="{{ $dashboard_prefix }}/queues">Queues</a>
    <a class="<?= ($active_tab ?? '') === 'delayed' ? 'active' : '' ?>" href="{{ $dashboard_prefix }}/delayed">Delayed</a>
    <a class="<?= ($active_tab ?? '') === 'failed' ? 'active' : '' ?>" href="{{ $dashboard_prefix }}/failed">Failed</a>
    <a class="<?= ($active_tab ?? '') === 'maintenance' ? 'active' : '' ?>" href="{{ $dashboard_prefix }}/maintenance">Maintenance</a>
</nav>

