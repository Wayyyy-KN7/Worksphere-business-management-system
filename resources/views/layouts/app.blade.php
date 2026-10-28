<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Worksphere')</title>
    <link rel="icon" href="{{ !empty($companySettings?->logo_path) ? asset('storage/' . $companySettings->logo_path) : asset('images/worksphere-mark.svg') }}">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--page-bg);
            color: var(--navy);
        }

        .btn-primary {
            --bs-btn-bg: var(--primary);
            --bs-btn-border-color: var(--primary);
            --bs-btn-hover-bg: var(--navy);
            --bs-btn-hover-border-color: var(--navy);
            --bs-btn-active-bg: var(--navy);
            --bs-btn-active-border-color: var(--navy);
            --bs-btn-focus-shadow-rgb: 15, 118, 110;
        }

        .btn-outline-primary {
            --bs-btn-color: var(--primary);
            --bs-btn-border-color: var(--primary);
            --bs-btn-hover-bg: var(--primary);
            --bs-btn-hover-border-color: var(--primary);
            --bs-btn-active-bg: var(--navy);
            --bs-btn-active-border-color: var(--navy);
            --bs-btn-focus-shadow-rgb: 15, 118, 110;
        }

        .btn-success {
            --bs-btn-color: #FFFFFF;
            --bs-btn-bg: var(--primary);
            --bs-btn-border-color: var(--primary);
            --bs-btn-hover-bg: var(--navy);
            --bs-btn-hover-border-color: var(--navy);
            --bs-btn-active-bg: var(--navy);
            --bs-btn-active-border-color: var(--navy);
        }

        .form-control:focus,
        .form-select:focus,
        .form-check-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.14);
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .text-primary { color: var(--primary) !important; }
        .border-primary { border-color: var(--primary) !important; }
        .text-success { color: var(--success) !important; }
        .border-success { border-color: var(--success) !important; }

        .badge-active {
            color: var(--navy);
            background: #DCFCE7;
            border: 1px solid #BBF7D0;
        }

        .badge-inactive {
            color: var(--navy);
            background: var(--page-bg);
            border: 1px solid var(--border);
        }

        a {
            text-decoration: none;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--sidebar-bg);
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
            border-right: 1px solid var(--border);
        }

        .sidebar-brand {
            padding: 18px 18px 16px;
            color: var(--navy);
            font-size: 1.05rem;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
            background: #FFFFFF;
        }

        .sidebar-brand-logo {
            width: 32px;
            height: 32px;
            object-fit: contain;
            margin-right: 9px;
            vertical-align: middle;
        }

        .sidebar-brand small {
            display: block;
            font-size: 0.68rem;
            color: var(--muted);
            font-weight: 400;
            margin-top: 4px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 12px 0 18px;
            margin: 0;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.875rem;
            line-height: 1.2;
            transition: all 0.2s ease;
            border-radius: 6px;
            margin: 2px 8px;
        }

        .sidebar-menu li a:hover {
            color: #FFFFFF;
            background: rgba(96, 165, 250, 0.16);
        }

        .sidebar-menu li a.active {
            color: #FFFFFF;
            background: #2563EB;
            border: 1px solid #2563EB;
        }

        .sidebar-menu li a i {
            font-size: 1rem;
            width: 18px;
            text-align: center;
            opacity: 0.95;
            color: #BFDBFE;
            background: transparent;
        }

        .sidebar-menu li a.active i {
            color: #FFFFFF;
        }

        .sidebar-section {
            padding: 14px 18px 8px;
            font-size: 0.64rem;
            text-transform: uppercase;
            letter-spacing: 0;
            color: #87939B;
            font-weight: 700;
        }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: var(--page-bg);
        }

        /* ── STAT CARDS ── */
        .stat-card {
            background: var(--panel-bg);
            border-radius: 14px;
            padding: 18px 18px 16px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(20, 54, 74, 0.12);
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(20, 54, 74, 0.12);
        }

        .stat-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: var(--muted);
            font-weight: 600;
        }

        .stat-value {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--navy);
            margin-top: 8px;
            line-height: 1.2;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        /* ── TOP NAV ── */
        .top-nav {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(10px);
            padding: 12px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .top-nav .nav-left,
        .top-nav .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .top-nav .nav-search {
            min-width: 260px;
            background: var(--page-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-nav .nav-search input {
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            color: var(--navy);
            font-size: 0.9rem;
        }

        .top-nav .nav-action-btn {
            border: 1px solid var(--border);
            background: var(--panel-bg);
            color: var(--navy);
            border-radius: 8px;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 0.82rem;
        }

        .top-nav .nav-action-btn.primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .top-nav .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .top-nav .user-avatar {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            border-radius: 50%;
            background: #DBEAFE;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            font-weight: 700;
            font-size: 0.8rem;
            text-decoration: none;
        }

        .top-nav .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ── PAGE CONTENT ── */
        .page-content {
            padding: 20px 22px 28px;
        }

        .page-header {
            margin-bottom: 20px;
        }

        .page-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--navy);
            margin: 0;
        }

        .page-header p {
            color: var(--muted);
            margin: 6px 0 0;
            font-size: 0.9rem;
        }

        .pagination {
            margin: 0;
            gap: 6px;
        }

        .page-item .page-link {
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--navy);
            background: var(--panel-bg);
            min-width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--panel-bg);
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2);
        }

        .page-item.disabled .page-link {
            color: var(--muted);
            background: var(--page-bg);
            pointer-events: none;
        }

        .page-item .page-link:hover {
            color: var(--primary);
            border-color: rgba(37, 99, 235, 0.35);
            background: var(--page-bg);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .top-nav {
                flex-wrap: wrap;
                gap: 12px;
            }
            .top-nav .nav-search {
                min-width: 0;
                width: 100%;
            }
        }

        :root {
            --sidebar-width: 248px;
            --primary-navy: #1E3A8A;
            --secondary-blue: #2563EB;
            --primary: var(--secondary-blue);
            --primary-dark: var(--primary-navy);
            --accent-cyan: #0EA5E9;
            --success: #22C55E;
            --warning: #F97316;
            --danger: #EF4444;
            --purple: #8B5CF6;
            --sidebar-bg: #EFF6FF;
            --sidebar-text: #334155;
            --sidebar-active: #2563EB;
            --page-bg: #F8FAFC;
            --panel-bg: #FFFFFF;
            --border: #E2E8F0;
            --muted: #64748B;
            --navy: #0F172A;
            --shadow: 0 4px 14px rgba(15, 23, 42, 0.055);
            --radius-sm: 8px;
            --radius-md: 12px;
            --surface-subtle: #F1F5F9;
            --bs-primary: #2563EB;
            --bs-primary-rgb: 37, 99, 235;
            --bs-success: #22C55E;
            --bs-success-rgb: 34, 197, 94;
            --bs-info: #0EA5E9;
            --bs-info-rgb: 14, 165, 233;
            --bs-link-color: #2563EB;
            --bs-link-hover-color: #1E3A8A;
        }

        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--navy);
            letter-spacing: 0;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--primary);
            transition: color 160ms ease;
        }

        a:hover { color: var(--primary-dark); }

        .sidebar {
            border-right: 1px solid var(--border);
            box-shadow: 1px 0 8px rgba(30, 58, 138, 0.06);
            transition: transform 180ms ease;
            background: var(--sidebar-bg);
            border-right-color: #DBEAFE;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sidebar::-webkit-scrollbar { display: none; width: 0; height: 0; }

        .sidebar-brand {
            min-height: 72px;
            padding: 18px 20px;
            color: var(--primary-navy);
            font-size: 1rem;
            border-bottom-color: #DBEAFE;
            background: var(--sidebar-bg);
        }

        .sidebar-brand > i { color: var(--primary); }
        .sidebar-brand small { color: var(--muted); }

        .sidebar-menu { padding: 12px 0 20px; }

        .sidebar-menu li a {
            min-height: 40px;
            padding: 8px 12px;
            margin: 2px 10px;
            border-radius: var(--radius-md);
            color: #334155;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 160ms ease, background-color 160ms ease, transform 160ms ease;
        }

        .sidebar-menu li a:hover {
            color: var(--primary-navy);
            background: #DBEAFE;
        }

        .sidebar-menu li a.active {
            color: #FFFFFF;
            background: #2563EB;
            font-weight: 650;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        .sidebar-menu li a i { color: #2563EB; }
        .sidebar-menu li a.active i { color: #FFFFFF; }

        .sidebar-section {
            padding: 16px 20px 7px;
            color: #64748B;
            font-size: 0.66rem;
            letter-spacing: 0.08em;
            font-weight: 700;
        }

        .main-content { background: var(--page-bg); }

        .top-nav {
            min-height: 64px;
            padding: 10px 28px;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: none;
            border-bottom-color: var(--border);
        }

        .top-nav .nav-left,
        .top-nav .nav-right { gap: 14px; }
        .top-nav .nav-search { border-radius: 6px; }

        .top-nav .user-avatar { background: #DBEAFE; color: var(--primary-dark); }

        .page-content { padding: 22px 24px 32px; }

        .page-header { margin-bottom: 22px; }

        .page-header h2 {
            color: var(--navy);
            font-size: 1.5rem;
            font-weight: 650;
            letter-spacing: 0;
        }

        .page-header p { color: var(--muted); font-size: 0.9rem; }

        .card,
        .stat-card,
        .sales-chart-panel {
            border: 1px solid var(--border) !important;
            border-radius: var(--radius-md) !important;
            background: var(--panel-bg);
            box-shadow: var(--shadow) !important;
        }

        .card-body { padding: 1rem; }

        .stat-card {
            padding: 18px;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .stat-card:hover {
            transform: none;
            border-color: #CBD8DD !important;
            box-shadow: 0 3px 8px rgba(20, 38, 52, 0.07) !important;
        }

        .stat-label {
            color: var(--muted);
            font-size: 0.73rem;
            letter-spacing: 0.045em;
        }

        .stat-value {
            color: var(--navy);
            font-size: 1.65rem;
            font-weight: 650;
        }

        .stat-icon { border-radius: 7px; }
        .stat-icon.bg-primary { color: var(--primary) !important; background: rgba(15, 118, 110, 0.1) !important; }
        .stat-icon.bg-success { color: #28654F !important; background: rgba(40, 122, 97, 0.1) !important; }
        .stat-icon.bg-info { color: #456775 !important; background: rgba(82, 122, 136, 0.12) !important; }
        .stat-icon.bg-warning { color: #805A17 !important; background: rgba(183, 121, 31, 0.12) !important; }
        .stat-icon.bg-danger { color: #8B4141 !important; background: rgba(185, 87, 87, 0.1) !important; }

        .btn {
            min-height: 36px;
            padding: 7px 12px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 550;
            transition: color 160ms ease, background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
        }

        .btn-sm { min-height: 30px; padding: 4px 9px; font-size: 0.78rem; }
        .btn-lg { min-height: 40px; }
        .btn-primary,
        .btn-success { --bs-btn-hover-bg: #1E3A8A; --bs-btn-hover-border-color: #1E3A8A; }
        .btn-secondary {
            --bs-btn-color: #42515B;
            --bs-btn-bg: #FFFFFF;
            --bs-btn-border-color: #D5DEE3;
            --bs-btn-hover-color: var(--navy);
            --bs-btn-hover-bg: var(--surface-subtle);
            --bs-btn-hover-border-color: #C4D0D6;
            --bs-btn-active-color: var(--navy);
            --bs-btn-active-bg: #EEF2F4;
            --bs-btn-active-border-color: #C4D0D6;
        }
        .btn:focus-visible { box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.18); }

        .form-label {
            margin-bottom: 6px;
            color: #46545E;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .form-control,
        .form-select {
            min-height: 38px;
            padding: 7px 10px;
            border-color: #D5DEE3;
            border-radius: var(--radius-md);
            background-color: #FFFFFF;
            color: var(--navy);
            font-size: 0.9rem;
            box-shadow: none;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .form-control:hover:not(:disabled):not([readonly]),
        .form-select:hover:not(:disabled) { border-color: #BCCBD1; }

        .form-select { cursor: pointer; }
        .form-select option { color: var(--navy); background-color: #FFFFFF; }

        .form-control:disabled,
        .form-select:disabled { background-color: #F1F4F5; color: #78858D; }

        textarea.form-control { min-height: 88px; line-height: 1.5; resize: vertical; }

        input[type="file"].form-control { padding: 5px; }
        input[type="file"].form-control::file-selector-button {
            margin: -1px 9px -1px -1px;
            padding: 6px 10px;
            border: 0;
            border-right: 1px solid var(--border);
            border-radius: 4px;
            background: #F3F6F7;
            color: #46545E;
            font: inherit;
        }

        .form-control::placeholder { color: #9AA5AC; }

        .form-control:focus,
        .form-select:focus,
        .form-check-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.13);
        }

        .form-check-input { border-color: #BCC8CE; }

        .table-responsive {
            border-radius: 6px;
            scrollbar-color: #BCC8CE transparent;
        }

        .table {
            --bs-table-color: #35434D;
            --bs-table-border-color: #E8EDF0;
            margin-bottom: 0;
            font-size: 0.84rem;
        }

        .table > :not(caption) > * > * { padding: 0.58rem 0.72rem; }

        .table thead th {
            color: #66757F;
            background: var(--surface-subtle);
            font-size: 0.72rem;
            font-weight: 650;
            letter-spacing: 0.035em;
            white-space: nowrap;
        }

        .table tbody tr { transition: background-color 150ms ease; }
        .table-hover > tbody > tr:hover > * { --bs-table-bg-state: #F8FAFA; }

        .badge {
            padding: 0.35em 0.62em;
            border: 1px solid transparent;
            border-radius: 5px;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0;
        }

        .badge.bg-primary,
        .badge-active { color: #145D57 !important; background: #E7F3F1 !important; border-color: #C8E3DE; }
        .badge-approved { color: #28654F !important; background: #EAF4EE !important; border-color: #D2E7D8; }
        .badge.bg-success { color: #28654F !important; background: #EAF4EE !important; border-color: #D2E7D8; }
        .badge.bg-warning,
        .badge-pending { color: #805A17 !important; background: #FBF3E2 !important; border-color: #F0DFB8; }
        .badge.bg-danger,
        .badge-rejected { color: #8B4141 !important; background: #F8EBEB !important; border-color: #EED4D4; }
        .badge.bg-info { color: #456775 !important; background: #EAF1F4 !important; border-color: #D4E2E8; }
        .badge.bg-secondary,
        .badge-inactive { color: #5F6C74 !important; background: #F0F3F4 !important; border-color: #DDE4E7; }

        .dropdown-menu {
            padding: 6px;
            border: 1px solid #DFE6E9;
            border-radius: 7px;
            box-shadow: 0 10px 28px rgba(20, 38, 52, 0.14);
        }

        .dropdown-item {
            min-height: 36px;
            display: flex;
            align-items: center;
            border-radius: 5px;
            color: #3F4E58;
            font-size: 0.875rem;
            transition: color 150ms ease, background-color 150ms ease;
        }

        .dropdown-item:hover,
        .dropdown-item:focus { color: var(--primary-dark); background: #EFF6F5; }

        .table-actions { display: inline-block; white-space: nowrap; }
        .table-actions-trigger {
            width: 32px;
            min-height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-color: #D5DEE3;
            background: #FFFFFF;
            color: #4D5B64;
            font-size: 1rem;
        }
        .table-actions-trigger i { font-size: 1rem; }
        .table-actions .dropdown-menu { min-width: 168px; box-shadow: 0 6px 18px rgba(27, 43, 52, 0.1); }
        .table-actions .dropdown-item { gap: 9px; }
        .table-actions .dropdown-item i { width: 16px; color: #73818A; text-align: center; }
        .table-actions .dropdown-item.text-danger i { color: inherit; }
        .table-actions .dropdown-item form { width: 100%; }
        .table-actions .dropdown-item button { width: 100%; text-align: left; }

        .modal-content {
            border: 1px solid #DFE6E9;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 28px rgba(27, 43, 52, 0.14);
        }

        .modal-header,
        .modal-footer { border-color: #E8EDF0; }
        .modal-title { color: var(--navy); font-size: 1.05rem; font-weight: 650; }

        .alert { border-radius: 6px; font-size: 0.9rem; }

        .pagination { gap: 4px; flex-wrap: wrap; align-items: center; }

        .page-item .page-link {
            min-width: 36px;
            height: 36px;
            border-radius: 6px;
            border-color: transparent;
            box-shadow: none;
            transition: color 150ms ease, background-color 150ms ease, border-color 150ms ease;
        }

        .page-item.active .page-link {
            color: #FFFFFF;
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: none;
        }

        .page-item:not(.active):not(.disabled) .page-link:hover {
            color: var(--primary-dark);
            background: #EFF6FF;
            border-color: #BFDBFE;
        }

        .page-item.disabled .page-link { color: #9AA5AC; background: transparent; }

        .page-link:focus-visible {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.16);
        }

        @media (max-width: 768px) {
            .sidebar { box-shadow: 8px 0 24px rgba(20, 38, 52, 0.14); }
            .top-nav { padding: 10px 16px; }
            .page-content { padding: 20px 16px 28px; }
            .page-header h2 { font-size: 1.35rem; }
        }

        @media (max-width: 480px) {
            .page-content { padding-right: 12px; padding-left: 12px; }
            .stat-card { padding: 14px; }
            .stat-value { font-size: 1.45rem; }
        }

        .sidebar-section,
        .stat-label,
        .table thead th { letter-spacing: 0; }

        .sidebar-menu li a:hover { color: var(--primary-navy); background: #DBEAFE; }
        .sidebar-menu li a i { background: transparent; }
        .sidebar-menu li a.active { color: #FFFFFF; }
        .sidebar-menu li a.active i { color: #FFFFFF; }
        .sidebar-brand { color: var(--primary-navy); background: var(--sidebar-bg); }
        .sidebar-brand small { color: var(--muted); }
        .sidebar-menu li a { color: #334155; border-radius: 10px; }
        .sidebar-menu li a i { color: var(--primary); }
        .sidebar-section { color: #64748B; }
        .sidebar-menu li a.active { background: var(--secondary-blue); }
        .page-header { margin-bottom: 20px; gap: 12px; flex-wrap: wrap; }
        .card-header:not([class*="bg-"]) {
            padding: 0.8rem 1rem;
            color: var(--navy);
            background: linear-gradient(100deg, #EFF6FF, #FFFFFF 72%);
            border-bottom-color: var(--border);
            font-weight: 600;
        }
        .stat-card {
            position: relative;
            overflow: hidden;
            padding: 18px;
            border-radius: 14px !important;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.1) !important;
        }
        .stat-icon { border-radius: 12px; }
        .stat-icon.bg-primary { color: #1D4ED8 !important; background: #DBEAFE !important; }
        .stat-icon.bg-success { color: #15803D !important; background: #DCFCE7 !important; }
        .stat-icon.bg-info { color: #0369A1 !important; background: #E0F2FE !important; }
        .stat-icon.bg-warning { color: #C2410C !important; background: #FFEDD5 !important; }
        .stat-icon.bg-danger { color: #B91C1C !important; background: #FEE2E2 !important; }
        .stat-card:has(.stat-icon.bg-primary) { background: linear-gradient(135deg, #EFF6FF, #FFFFFF 72%); }
        .stat-card:has(.stat-icon.bg-info) { background: linear-gradient(135deg, #E0F2FE, #FFFFFF 72%); }
        .stat-card:has(.stat-icon.bg-success) { background: linear-gradient(135deg, #F0FDF4, #FFFFFF 72%); }
        .stat-card:has(.stat-icon.bg-warning) { background: linear-gradient(135deg, #FFF7ED, #FFFFFF 72%); }
        .stat-card:has(.stat-icon.bg-danger) { background: linear-gradient(135deg, #FEF2F2, #FFFFFF 72%); }
        .badge { border-radius: 999px; }
        .badge.bg-primary { color: #1D4ED8 !important; background: #DBEAFE !important; border-color: #BFDBFE; }
        .badge.bg-success { color: #15803D !important; background: #DCFCE7 !important; border-color: #BBF7D0; }
        .badge.bg-info { color: #0369A1 !important; background: #E0F2FE !important; border-color: #BAE6FD; }
        .badge.bg-warning { color: #C2410C !important; background: #FFEDD5 !important; border-color: #FED7AA; }
        .badge.bg-danger { color: #B91C1C !important; background: #FEE2E2 !important; border-color: #FECACA; }
        .badge.bg-secondary { color: #475569 !important; background: #F1F5F9 !important; border-color: #E2E8F0; }
        .badge-active { color: #15803D !important; background: #DCFCE7 !important; border-color: #BBF7D0; }
        .btn-success {
            --bs-btn-color: #FFFFFF;
            --bs-btn-bg: #22C55E;
            --bs-btn-border-color: #22C55E;
            --bs-btn-hover-bg: #16A34A;
            --bs-btn-hover-border-color: #16A34A;
            --bs-btn-active-bg: #15803D;
            --bs-btn-active-border-color: #15803D;
        }
        .btn-danger {
            --bs-btn-bg: #EF4444;
            --bs-btn-border-color: #EF4444;
            --bs-btn-hover-bg: #DC2626;
            --bs-btn-hover-border-color: #DC2626;
        }
        .btn-warning {
            --bs-btn-bg: #F97316;
            --bs-btn-border-color: #F97316;
            --bs-btn-color: #FFFFFF;
            --bs-btn-hover-bg: #EA580C;
            --bs-btn-hover-border-color: #EA580C;
            --bs-btn-hover-color: #FFFFFF;
        }
        .form-control, .form-select {
            min-height: 42px;
            border-color: var(--border);
            border-radius: 10px;
        }
        .form-control:focus, .form-select:focus, .form-check-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
        }
        .table-responsive { border-radius: 12px; }
        .table { --bs-table-color: #334155; --bs-table-border-color: #E2E8F0; }
        .table thead th { color: #475569; background: #F1F5F9; }
        .table-hover > tbody > tr:hover > * { --bs-table-bg-state: #F8FAFC; }
        .dropdown-menu, .modal-content { border-color: var(--border); border-radius: 14px; }
        .dropdown-item:hover, .dropdown-item:focus { color: var(--primary-dark); background: #EFF6FF; }
        .page-item.active .page-link { background: var(--primary); border-color: var(--primary); }
        .page-item:not(.active):not(.disabled) .page-link:hover { color: var(--primary-dark); background: #EFF6FF; border-color: #BFDBFE; }
        .top-nav .user-avatar { background: #DBEAFE; color: var(--primary-dark); }
        .top-nav .nav-action-btn.primary { color: #FFFFFF; }
        @media (max-width: 768px) {
            .sidebar { box-shadow: 14px 0 32px rgba(15, 23, 42, 0.2); }
            .page-content { padding: 18px 16px 28px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: 0.01ms !important; }
        }
    </style>

    @stack('styles')
</head>
<body>

    @auth
    @php
        $topbarNotificationQuery = auth()->user()->isAdmin()
            ? \App\Models\Notification::query()
            : auth()->user()->notifications();
        $topbarNotifications = $topbarNotificationQuery->latest()->limit(5)->get();
        $unreadNotificationCount = auth()->user()->unreadNotificationsCount();
    @endphp
    <!-- ── SIDEBAR ── -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            @if(!empty($companySettings?->logo_path))
                <img class="sidebar-brand-logo" src="{{ asset('storage/' . $companySettings->logo_path) }}" alt="">
            @else
                <img class="sidebar-brand-logo" src="{{ asset('images/worksphere-mark.svg') }}" alt="">
            @endif
            Worksphere
            <small>Company Management System</small>
        </div>

        <ul class="sidebar-menu">
            <li>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>

            {{-- ALL USERS: MY WORKSPACE --}}
            <li class="sidebar-section">MY WORKSPACE</li>
            <li>
                <a href="{{ route('employee.attendance.index') }}" class="{{ request()->routeIs('employee.attendance.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i> My Attendance
                </a>
            </li>
            @unless(auth()->user()->isAdmin())
                <li>
                    <a href="{{ route('employee.leave.index') }}" class="{{ request()->routeIs('employee.leave.*') ? 'active' : '' }}">
                        <i class="bi bi-calendar-x"></i> My Leave
                    </a>
                </li>
            @endunless
            {{-- ADMIN & HR & MANAGER WORKSPACE --}}
            @if(auth()->user()->isAdmin() || auth()->user()->isHR() || auth()->user()->isManager())
            <li class="sidebar-section">TEAM / MANAGEMENT</li>
            @if(auth()->user()->isAdmin() || auth()->user()->isHR())
            <li>
                <a href="{{ route('hr.employees.index') }}" class="{{ request()->routeIs('hr.employees.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Employees
                </a>
            </li>
            @endif
            @if(auth()->user()->isManager() && !auth()->user()->isHR())
                @if(!auth()->user()->isSales() && !auth()->user()->isMarketing())
                <li>
                    <a href="{{ route('crm.follow-ups.index') }}" class="{{ request()->routeIs('crm.follow-ups.*') ? 'active' : '' }}">
                        <i class="bi bi-list-check"></i> Team Follow-Ups
                    </a>
                </li>
                @endif
            @endif
            @if(auth()->user()->isAdmin() || auth()->user()->isHR())
                @if(auth()->user()->canViewCompanyAttendance())
                <li>
                    <a href="{{ route('hr.attendance.index') }}" class="{{ request()->routeIs('hr.attendance.index') ? 'active' : '' }}">
                        <i class="bi bi-calendar-check"></i> Attendance
                    </a>
                </li>
                <li>
                    <a href="{{ route('hr.attendance.history') }}" class="{{ request()->routeIs('hr.attendance.history') ? 'active' : '' }}">
                        <i class="bi bi-clock-history"></i> Attendance History
                    </a>
                </li>
                <li>
                    <a href="{{ route('hr.attendance.report') }}" class="{{ request()->routeIs('hr.attendance.report') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-bar-graph"></i> Reports
                    </a>
                </li>
                @endif
                <li>
                    <a href="{{ route('hr.leaves.index') }}" class="{{ request()->routeIs('hr.leaves.*') ? 'active' : '' }}">
                        <i class="bi bi-calendar-x"></i> Leave Management
                    </a>
                </li>
            @endif
            @if(auth()->user()->isManager())
                <li>
                    <a href="{{ route('manager.leave.index') }}" class="{{ request()->routeIs('manager.leave.index') || request()->routeIs('manager.leave.show') ? 'active' : '' }}">
                        <i class="bi bi-check2-square"></i> Leave Approvals
                    </a>
                </li>
                <li>
                    <a href="{{ route('manager.leave.calendar') }}" class="{{ request()->routeIs('manager.leave.calendar') ? 'active' : '' }}">
                        <i class="bi bi-calendar3"></i> Leave Calendar
                    </a>
                </li>
            @endif
            @endif

            {{-- CRM: SALES & MARKETING & ADMIN --}}
            @if(auth()->user()->isSales() || auth()->user()->isMarketing() || auth()->user()->isAdmin())
            <li class="sidebar-section">CRM</li>
            <li>
                <a href="{{ route('crm.customers.index') }}" class="{{ request()->routeIs('crm.customers.*') ? 'active' : '' }}">
                    <i class="bi bi-person-rolodex"></i> Customers
                </a>
            </li>
            <li>
                <a href="{{ route('crm.opportunities.index') }}" class="{{ request()->routeIs('crm.opportunities.*') ? 'active' : '' }}">
                    <i class="bi bi-funnel"></i> Opportunities
                </a>
            </li>
            <li>
                <a href="{{ route('crm.follow-ups.index') }}" class="{{ request()->routeIs('crm.follow-ups.*') ? 'active' : '' }}">
                    <i class="bi bi-arrow-left-right"></i> Follow-ups
                </a>
            </li>
            @if(auth()->user()->isSales())
            <li>
                <a href="{{ route('crm.reminders.index') }}" class="{{ request()->routeIs('crm.reminders.*') ? 'active' : '' }}">
                    <i class="bi bi-bell"></i> Reminders
                </a>
            </li>
            @endif
            @endif

            {{-- SALES TEAM & ADMIN: Sales Module --}}
            @if(auth()->user()->isAdmin() || auth()->user()->role === 'SALES_MANAGER' || auth()->user()->role === 'SALES_EMPLOYEE' || auth()->user()->role === 'SALES' || (method_exists(auth()->user(), 'isSales') && auth()->user()->isSales()))
            <li>
                <a href="{{ route('crm.sales.index') }}" class="{{ request()->routeIs('crm.sales.*') ? 'active' : '' }}">
                    <i class="bi bi-cart-check"></i> Sales
                </a>
            </li>
            @endif

            {{-- DOCUMENT MANAGEMENT --}}
            <li class="sidebar-section">DOCUMENTS</li>
            <li>
                <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.index') && !request()->has('status') ? 'active' : '' }}">
                    <i class="bi bi-folder2-open"></i> All Documents
                </a>
            </li>
            <li>
                <a href="{{ route('documents.my') }}" class="{{ request()->routeIs('documents.my') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-person"></i> My Documents
                </a>
            </li>
            <li>
                <a href="{{ route('documents.department') }}" class="{{ request()->routeIs('documents.department') ? 'active' : '' }}">
                    <i class="bi bi-building"></i> Department Docs
                </a>
            </li>
            <li>
                <a href="{{ route('documents.shared') }}" class="{{ request()->routeIs('documents.shared') ? 'active' : '' }}">
                    <i class="bi bi-share"></i> Shared with Me
                </a>
            </li>
            <li>
                <a href="{{ route('documents.create') }}" class="{{ request()->routeIs('documents.create') ? 'active' : '' }}">
                    <i class="bi bi-upload"></i> Upload Document
                </a>
            </li>
            @if(in_array(auth()->user()->role, ['ADMIN', 'HR_MANAGER', 'SALES_MANAGER', 'MARKETING_MANAGER', 'IT_MANAGER']))
            <li>
                <a href="{{ route('documents.approvals') }}" class="{{ request()->routeIs('documents.approvals') ? 'active' : '' }}">
                    <i class="bi bi-check-circle"></i> Approvals
                </a>
            </li>
            @endif

            {{-- ADMIN ONLY --}}
            @if(auth()->user()->isAdmin())
            <li class="sidebar-section">ADMINISTRATION</li>
            <li>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Users
                </a>
            </li>
            <li>
                <a href="{{ route('admin.departments.index') }}" class="{{ request()->routeIs('admin.departments.*') ? 'active' : '' }}">
                    <i class="bi bi-building"></i> Departments
                </a>
            </li>
            <li>
                <a href="{{ route('admin.audit-logs.index') }}" class="{{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i> Audit Logs
                </a>
            </li>
            @endif

            {{-- COMPANY --}}
            <li class="sidebar-section">COMPANY</li>
            <li>
                <a href="{{ route('company.structure') }}" class="{{ request()->routeIs('company.structure') ? 'active' : '' }}">
                    <i class="bi bi-diagram-3"></i> Company Structure
                </a>
            </li>
            @if(auth()->user()->isAdmin())
            <li>
                <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
            @endif

            <li class="sidebar-section">GENERAL</li>
            <li>
                <a href="{{ route('documents.company') }}" class="{{ request()->routeIs('documents.company') || request()->routeIs('documents.public') ? 'active' : '' }}">
                    <i class="bi bi-globe"></i> Company Documents
                </a>
            </li>
            <li>
                <a href="{{ route('profile.show') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <i class="bi bi-person-circle"></i> My Profile
                </a>
            </li>
            <li>
                <a href="{{ route('notifications.index') }}" class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <i class="bi bi-bell"></i> Notifications
                    @if($unreadNotificationCount > 0)<span class="badge bg-danger ms-auto">{{ $unreadNotificationCount }}</span>@endif
                </a>
            </li>

        </ul>
    </nav>

    <!-- ── MAIN CONTENT ── -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div class="nav-left">
                <button class="btn btn-sm btn-outline-secondary d-md-none" aria-label="Toggle navigation" aria-controls="sidebar" onclick="document.getElementById('sidebar').classList.toggle('show')">
                    <i class="bi bi-list"></i>
                </button>
                <span class="text-muted d-none d-md-inline">@yield('breadcrumb', 'Dashboard')</span>
            </div>

            <div class="nav-right">
                <div class="user-info d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-link text-dark p-0 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                            <i class="bi bi-bell fs-5"></i>
                            @if($unreadNotificationCount > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    {{ $unreadNotificationCount }}
                                </span>
                            @endif
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2" style="width: min(360px, calc(100vw - 24px));">
                            <li class="d-flex justify-content-between align-items-center px-2 pb-2">
                                <strong>Notifications</strong>
                                @if($unreadNotificationCount > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-link p-0" type="submit">Mark all read</button></form>
                                @endif
                            </li>
                            @forelse($topbarNotifications as $notification)
                                <li>
                                    <a class="dropdown-item rounded py-2 {{ $notification->is_read ? '' : 'bg-primary bg-opacity-10' }}" href="{{ route('notifications.open', $notification) }}">
                                        <div class="d-flex justify-content-between gap-2">
                                            <span class="small fw-semibold">{{ $notification->title }}</span>
                                            @unless($notification->is_read)<span class="badge bg-primary">New</span>@endunless
                                        </div>
                                        <div class="text-muted small text-wrap">{{ \Illuminate\Support\Str::limit($notification->message, 90) }}</div>
                                        <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                    </a>
                                </li>
                            @empty
                                <li><span class="dropdown-item text-muted">No notifications yet.</span></li>
                            @endforelse
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="{{ route('notifications.index') }}">View all notifications</a></li>
                        </ul>
                    </div>

                    <a href="{{ route('profile.show') }}" class="user-avatar" aria-label="View profile">
                        @if(auth()->user()->profile_photo_path)
                               <img src="{{ asset('storage/' . auth()->user()->profile_photo_path) }}" alt="">
                        @else
                            <span>{{ auth()->user()->initials }}</span>
                        @endif
                    </a>
                    <div class="dropdown">
                        <a href="#" class="text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown">
                            {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.show') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="status" aria-live="polite" data-auto-dismiss="2000">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
    @endauth

    @guest
        @yield('content')
    @endguest

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const portaledTableMenus = new WeakMap();

            document.addEventListener('shown.bs.dropdown', function (event) {
                const dropdown = event.target.closest('.table-actions');
                const menu = dropdown && dropdown.querySelector('.dropdown-menu');
                if (!dropdown || !menu) {
                    return;
                }

                const triggerBounds = event.target.getBoundingClientRect();
                const menuBounds = menu.getBoundingClientRect();
                const gap = 6;
                const spaceBelow = window.innerHeight - triggerBounds.bottom;
                const top = spaceBelow >= menuBounds.height + gap
                    ? triggerBounds.bottom + gap
                    : Math.max(gap, triggerBounds.top - menuBounds.height - gap);
                const left = Math.min(
                    Math.max(gap, triggerBounds.right - menuBounds.width),
                    window.innerWidth - menuBounds.width - gap
                );

                portaledTableMenus.set(dropdown, {
                    menu: menu,
                    parent: menu.parentNode,
                    nextSibling: menu.nextSibling,
                });
                document.body.appendChild(menu);
                Object.assign(menu.style, {
                    position: 'fixed',
                    top: top + 'px',
                    left: left + 'px',
                    right: 'auto',
                    bottom: 'auto',
                    margin: '0',
                    transform: 'none',
                    zIndex: '1080',
                });
            });

            document.addEventListener('hidden.bs.dropdown', function (event) {
                const dropdown = event.target.closest('.table-actions');
                const originalPosition = dropdown && portaledTableMenus.get(dropdown);
                if (!originalPosition) {
                    return;
                }

                const menu = originalPosition.menu;
                originalPosition.parent.insertBefore(menu, originalPosition.nextSibling);
                menu.removeAttribute('style');
                portaledTableMenus.delete(dropdown);
            });

            const sidebar = document.getElementById('sidebar');
            const sidebarScrollKey = 'crm-sidebar-scroll-top';
            const pageScrollKey = 'crm-page-scroll-top';

            const savedSidebarScroll = sessionStorage.getItem(sidebarScrollKey);
            if (sidebar && savedSidebarScroll !== null) {
                sidebar.scrollTop = Number(savedSidebarScroll) || 0;
            }

            const savedPageScroll = sessionStorage.getItem(pageScrollKey);
            if (savedPageScroll !== null) {
                const y = Number(savedPageScroll) || 0;
                window.scrollTo({ top: y, behavior: 'auto' });
            }

            sessionStorage.removeItem(sidebarScrollKey);
            sessionStorage.removeItem(pageScrollKey);

            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }

            document.querySelectorAll('.sidebar a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (sidebar) {
                        sessionStorage.setItem(sidebarScrollKey, String(sidebar.scrollTop || 0));
                        sessionStorage.setItem(pageScrollKey, String(window.scrollY || 0));
                        sidebar.classList.remove('show');
                    }
                });
            });

            document.querySelectorAll('[data-auto-dismiss]').forEach(function (alert) {
                const delay = Number(alert.dataset.autoDismiss) || 2000;
                window.setTimeout(function () {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }, delay);
            });
        });
    </script>
    @stack('scripts')
</body>
</html>