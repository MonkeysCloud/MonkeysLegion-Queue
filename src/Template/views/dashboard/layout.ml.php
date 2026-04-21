<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('view_title') | MonkeysLegion</title>

    <!-- Fonts: Using Outfit for a more modern/pro feel -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            /* High-End Slate & Indigo Palette */
            --bg-primary: #020617;
            /* Darker Slate for Depth */
            --bg-secondary: #0f172a;
            --bg-tertiary: #1e293b;
            --bg-header: rgba(15, 23, 42, 0.85);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent: #6366f1;
            /* Indigo */
            --accent-hover: #818cf8;
            --border: #1e293b;
            --danger: #f43f5e;
            /* Rose */
            --success: #10b981;
            /* Emerald */
            --warning: #f59e0b;
            /* Amber */
            --sidebar-width: 280px;
            /* Wider Sidebar */
        }

        [data-theme="light"] {
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f1f5f9;
            --bg-header: rgba(255, 255, 255, 0.8);
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --border: #cbd5e1;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--bg-tertiary);
            border-radius: 10px;
            border: 2px solid var(--bg-primary);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            display: flex;
            min-height: 100vh;
            font-size: 16px;
            /* Base font size larger */
        }

        /* Sidebar */
        aside {
            width: var(--sidebar-width);
            background-color: var(--bg-secondary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 40;
        }

        .sidebar-header {
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--accent), #ec4899);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.4rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .brand-name {
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: -0.03em;
        }

        .sidebar-nav {
            padding: 1.5rem 1rem;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.25rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 0.75rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1rem;
            font-weight: 500;
        }

        .nav-item:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            transform: translateX(4px);
        }

        .nav-item.active {
            background-color: var(--accent);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
        }

        /* Main Content */
        main {
            flex: 1;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
        }

        header {
            height: 70px;
            background-color: var(--bg-header);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2.5rem;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .content-area {
            padding: 2.5rem;
            width: 100%;
            margin: 0;
            animation: fadeIn 0.4s ease-out;
        }

        /* Standardized Components */
        .table-container {
            width: 100%;
            overflow-x: auto;
            background-color: var(--bg-secondary);
            border-radius: 0.75rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 1rem 1.5rem;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background-color: rgba(255, 255, 255, 0.01);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-danger {
            background-color: rgba(244, 63, 94, 0.1);
            color: var(--danger);
            border: 1px solid rgba(244, 63, 94, 0.2);
        }

        .badge-neutral {
            background-color: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        select.btn {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 0.65rem auto;
            padding-right: 2.5rem;
            min-width: 160px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Components */
        .card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
            font-size: 1rem;
            gap: 0.75rem;
            text-decoration: none;
            font-family: inherit;
        }

        .btn-primary {
            background-color: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--accent-hover);
            transform: scale(1.02);
        }

        .btn-outline {
            background-color: transparent;
            border-color: var(--border);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-danger {
            background-color: rgba(244, 63, 94, 0.1);
            border-color: rgba(244, 63, 94, 0.2);
            color: var(--danger);
        }

        .btn-danger:hover {
            background-color: var(--danger);
            color: white;
        }

        /* Utilities */
        .flex {
            display: flex;
        }

        .flex-col {
            flex-direction: column;
        }

        .items-center {
            align-items: center;
        }

        .justify-between {
            justify-content: space-between;
        }

        .gap-1 {
            gap: 0.25rem;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .gap-4 {
            gap: 1rem;
        }

        .gap-6 {
            gap: 1.5rem;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mb-6 {
            margin-bottom: 1.5rem;
        }

        .mt-4 {
            margin-top: 1rem;
        }

        .mt-6 {
            margin-top: 1.5rem;
        }

        .text-sm {
            font-size: 0.9375rem;
        }

        .text-xs {
            font-size: 0.8125rem;
        }

        .text-muted {
            color: var(--text-muted);
        }

        .font-semibold {
            font-weight: 600;
        }

        .font-medium {
            font-weight: 500;
        }

        .w-full {
            width: 100%;
        }

        /* Code Blocks */
        pre {
            background-color: #020617;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.5rem;
            overflow-x: auto;
            color: #94a3b8;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.95rem;
            line-height: 1.7;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(2, 6, 23, 0.8);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-box {
            background: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 1.25rem;
            padding: 2.5rem;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform: scale(0.95) translateY(10px);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .modal-overlay.active .modal-box {
            transform: scale(1) translateY(0);
        }

        .modal-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
        }

        .modal-content-text {
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            line-height: 1.6;
            font-size: 1.05rem;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 80px;
            }

            .brand-name {
                display: none;
            }

            .nav-item span {
                display: none;
            }

            main {
                margin-left: 80px;
            }
        }
    </style>
    @stack('styles')
</head>

<body>
    <aside id="sidebar">
        <nav class="sidebar-nav">
            <a href="{{ $dashboard_prefix }}" class="nav-item <?= ($active_tab ?? '') === 'overview' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="7" height="9" x="3" y="3" rx="1" />
                    <rect width="7" height="5" x="14" y="3" rx="1" />
                    <rect width="7" height="9" x="14" y="12" rx="1" />
                    <rect width="7" height="5" x="3" y="16" rx="1" />
                </svg>
                <span>Overview</span>
            </a>
            <a href="{{ $dashboard_prefix }}/queues" class="nav-item <?= ($active_tab ?? '') === 'queues' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 15h10" />
                    <path d="M7 9h10" />
                    <path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4" />
                    <path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v7" />
                    <path d="M2 13h20" />
                </svg>
                <span>Queues</span>
            </a>
            <a href="{{ $dashboard_prefix }}/delayed" class="nav-item <?= ($active_tab ?? '') === 'delayed' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
                <span>Delayed</span>
            </a>
            <a href="{{ $dashboard_prefix }}/failed" class="nav-item <?= ($active_tab ?? '') === 'failed' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                <span>Failed Jobs</span>
            </a>
            <a href="{{ $dashboard_prefix }}/maintenance" class="nav-item <?= ($active_tab ?? '') === 'maintenance' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a2 2 0 0 1-2.26 2.26l-3.21 3.21a2 2 0 0 1-2.83 0l-1.41-1.41a2 2 0 0 1 0-2.83l3.21-3.21a2 2 0 0 1 2.26-2.26Z" />
                    <path d="m11.1 12.1-3.3 3.3a1 1 0 0 1-1.4 0l-1.6-1.6a1 1 0 0 1 0-1.4l3.3-3.3" />
                    <path d="M12 22H5a2 2 0 0 1-2-2V7" />
                    <path d="M7 2h10" />
                </svg>
                <span>Maintenance</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <button class="nav-item w-full" id="theme-toggle" style="background: none; border: none; cursor: pointer; justify-content: center;">
                <svg id="theme-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2" />
                    <path d="M12 20v2" />
                    <path d="m4.93 4.93 1.41 1.41" />
                    <path d="m17.66 17.66 1.41 1.41" />
                    <path d="M2 12h2" />
                    <path d="M20 12h2" />
                    <path d="m6.34 17.66-1.41 1.41" />
                    <path d="m19.07 4.93-1.41 1.41" />
                </svg>
            </button>
        </div>
    </aside>

    <main>
        <header>
            <h2 class="text-sm font-semibold" style="color: var(--text-secondary)">@yield('header_title')</h2>
            <span class="text-xs text-muted">@yield('header_meta')</span>
        </header>

        <div class="content-area">
            @yield('content')
        </div>
    </main>

    <!-- Modal Template -->
    <div class="modal-overlay" id="confirm-modal">
        <div class="modal-box">
            <h3 class="modal-title" id="confirm-title">Confirm Action</h3>
            <p class="modal-content-text" id="confirm-message">Are you sure you want to proceed?</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" id="confirm-cancel" style="padding: 0.75rem 1.5rem;">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-execute" style="padding: 0.75rem 2rem;">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('theme') || 'dark';
        html.setAttribute('data-theme', savedTheme);
        updateIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateIcon(newTheme);
        });

        function updateIcon(theme) {
            if (theme === 'light') {
                themeIcon.innerHTML = '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>';
            } else {
                themeIcon.innerHTML = '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>';
            }
        }

        /* Modal JS logic */
        let confirmTargetForm = null;

        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.hasAttribute('data-confirm')) {
                e.preventDefault();
                const msg = form.getAttribute('data-confirm');
                const title = form.getAttribute('data-confirm-title') || 'Action Required';
                
                document.getElementById('confirm-title').textContent = title;
                document.getElementById('confirm-message').textContent = msg;
                
                confirmTargetForm = form;
                const modal = document.getElementById('confirm-modal');
                modal.style.display = 'flex';
                void modal.offsetWidth; // Force reflow
                modal.classList.add('active');
            }
        });

        document.getElementById('confirm-cancel')?.addEventListener('click', () => {
            const modal = document.getElementById('confirm-modal');
            modal.classList.remove('active');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
            confirmTargetForm = null;
        });

        document.getElementById('confirm-execute')?.addEventListener('click', () => {
            if (confirmTargetForm) {
                confirmTargetForm.removeAttribute('data-confirm');
                confirmTargetForm.submit();
            }
            const modal = document.getElementById('confirm-modal');
            modal.classList.remove('active');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
        });
    </script>
    @stack('scripts')
</body>

</html>