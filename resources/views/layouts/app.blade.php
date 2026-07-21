<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta 
        name="viewport" 
        content="width=device-width, initial-scale=1"
    >
    <link rel="icon" href="{{ asset('dist/img/favicon16.ico') }}" type="image/x-icon">

    <title>
        @yield('title', 'Dashboard') | Tesys
    </title>

    <!-- GOOGLE FONTS: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- FONT AWESOME -->
    <link 
        rel="stylesheet"
        href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}"
    >

    <!-- DATATABLE -->
    <link 
        rel="stylesheet"
        href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}"
    >

    <link 
        rel="stylesheet"
        href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}"
    >

    <!-- ADMIN LTE -->
    <link 
        rel="stylesheet"
        href="{{ asset('dist/css/adminlte.min.css') }}"
    >

    <!-- TOM SELECT -->
    <link 
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css"
    >

    <link 
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css"
    >

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- OPTIONAL: Flatpickr Indonesia Locale -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

    <!-- SWEET ALERT -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ===================================================== -->
    <!-- CUSTOM STYLE -->
    <!-- ===================================================== -->

    <style>

        /*
        |--------------------------------------------------------------------------
        | DESIGN TOKENS
        |--------------------------------------------------------------------------
        */

        :root {
            --navy:        #0D1B2A;
            --navy-mid:    #162030;
            --navy-lt:     #1D2D3F;
            --amber:       #F5A623;
            --amber-dk:    #C97E0A;
            --amber-tint:  rgba(245, 166, 35, 0.08);
            --amber-border:rgba(245, 166, 35, 0.25);
            --sidebar-w:   250px;
            --text-muted:  #8A9BB0;
            --border-soft: rgba(255,255,255,0.07);
            --body-bg:     #F0F2F5;
            --card-bg:     #FFFFFF;
            --radius-sm:   6px;
            --radius-md:   10px;
            --radius-lg:   14px;
        }

        /*
        |--------------------------------------------------------------------------
        | GLOBAL
        |--------------------------------------------------------------------------
        */

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
            font-size: 13.5px !important;
            color: #2D3748;
            background: var(--body-bg) !important;
        }

        .content-wrapper {
            background: var(--body-bg) !important;
        }

        /*
        |--------------------------------------------------------------------------
        | NAVBAR
        |--------------------------------------------------------------------------
        */

        .main-header.navbar {
            background: #FFFFFF !important;
            border-bottom: 1px solid #E8ECF0 !important;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06) !important;
            height: 56px;
            min-height: 56px;
            padding: 0 20px;
        }

        /* Hamburger icon */
        .main-header .nav-link[data-widget="pushmenu"] {
            color: #5A6A7E !important;
            width: 40px;
            height: 40px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            transition: background 0.15s;
            padding: 0 !important;
        }

        .main-header .nav-link[data-widget="pushmenu"]:hover {
            background: #F0F2F5 !important;
            color: #1A1A2E !important;
        }

        /* User dropdown trigger */
        .main-header .navbar-nav .nav-link {
            color: #374151 !important;
            font-size: 13px !important;
            font-weight: 500;
            padding: 6px 10px !important;
            border-radius: var(--radius-sm);
            transition: background 0.15s;
            display: flex;
            align-items: center;
        }

        .main-header .navbar-nav .nav-link:hover {
            background: #F0F2F5 !important;
        }

        .main-header .navbar-nav .nav-link .fa-user {
            color: var(--amber);
            background: var(--amber-tint);
            border: 1px solid var(--amber-border);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            margin-right: 2px;
        }

        /* Dropdown */
        .main-header .dropdown-menu {
            border: 1px solid #E8ECF0;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            border-radius: var(--radius-md);
            padding: 6px;
            margin-top: 6px !important;
        }

        .main-header .dropdown-header {
            padding: 10px 12px 8px;
            font-size: 13px;
            font-weight: 600;
            color: #1A202C;
        }

        .main-header .dropdown-header small {
            font-size: 12px;
            font-weight: 400;
        }

        .main-header .dropdown-divider {
            margin: 4px 0;
            border-color: #F0F2F5;
        }

        .main-header .px-3.py-2 {
            padding: 6px 12px !important;
        }

        .main-header .dropdown-item {
            border-radius: var(--radius-sm);
            font-size: 13px;
            padding: 8px 12px;
            transition: background 0.15s;
            color: #374151;
        }

        .main-header .dropdown-item:hover {
            background: #FEF3F2;
        }

        .main-header .dropdown-item.text-danger {
            color: #DC2626 !important;
            font-weight: 500;
        }

        /*
        |--------------------------------------------------------------------------
        | SIDEBAR SHELL
        |--------------------------------------------------------------------------
        */

        .main-sidebar {
            background: var(--navy) !important;
            box-shadow: 2px 0 20px rgba(0,0,0,0.18) !important;
            /* Width NOT set here — AdminLTE controls it */
        }

        /*
        |--------------------------------------------------------------------------
        | BRAND / LOGO
        |--------------------------------------------------------------------------
        */

        .brand-link {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            height: 56px !important;
            background: var(--navy) !important;
            border-bottom: 1px solid var(--border-soft) !important;
            padding: 0 16px !important;
            overflow: hidden;
            white-space: nowrap;
        }

        .brand-logo-full {
            width: 148px;
            height: auto;
            display: block;
            flex-shrink: 0;
        }

        .brand-logo-mini {
            width: 30px;
            height: 30px;
            display: none;
            flex-shrink: 0;
        }

        .brand-link .brand-image {
            float: none !important;
            margin: 0 !important;
            opacity: 1 !important;
        }

        /* Logo swap on collapse */
        .sidebar-collapse .brand-logo-full { display: none !important; }
        .sidebar-collapse .brand-logo-mini { display: block !important; }

        /* Sidebar inner */
        .sidebar {
            padding-top: 4px;
            padding-bottom: 16px;
            overflow-x: hidden;
        }

        /*
        |--------------------------------------------------------------------------
        | FLAT NAV SYSTEM (tsys-*)
        | No treeview / accordion. Section labels + flat links.
        |--------------------------------------------------------------------------
        */

        .tsys-nav {
            list-style: none;
            padding: 0;
            margin: 0 0 4px 0;
        }

        /* Section header label */
        .tsys-section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.13em;
            text-transform: uppercase;
            color: #4A5E72;
            padding: 18px 20px 6px;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.15s;
        }

        /* Nav link */
        .tsys-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            margin: 1px 8px;
            border-radius: var(--radius-sm);
            text-decoration: none !important;
            color: #A0B0C2 !important;
            font-size: 13px;
            font-weight: 400;
            white-space: nowrap;
            transition: background 0.15s, color 0.15s;
            position: relative;
        }

        .tsys-link:hover {
            background: rgba(255,255,255,0.07);
            color: #FFFFFF !important;
        }

        .tsys-link:hover .tsys-icon {
            color: #FFFFFF;
        }

        /* Active state */
        .tsys-link.tsys-active {
            background: var(--amber-tint);
            color: var(--amber) !important;
            font-weight: 600;
            border: 1px solid var(--amber-border);
        }

        .tsys-link.tsys-active .tsys-icon {
            color: var(--amber);
        }

        /* Active left accent bar */
        .tsys-link.tsys-active::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 7px;
            bottom: 7px;
            width: 3px;
            background: var(--amber);
            border-radius: 0 3px 3px 0;
        }

        /* Icon */
        .tsys-icon {
            font-size: 14px;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
            color: #5A7080;
            transition: color 0.15s;
        }

        /* Label text */
        .tsys-label {
            overflow: hidden;
            text-overflow: ellipsis;
            transition: opacity 0.15s, width 0.15s;
        }

        /*
        |--------------------------------------------------------------------------
        | COLLAPSED STATE
        | Body gets .sidebar-collapse when hamburger pressed.
        | Sidebar shrinks to 4.6rem (AdminLTE default for sidebar-mini).
        |--------------------------------------------------------------------------
        */

        /* Hide section labels */
        .sidebar-mini.sidebar-collapse .tsys-section-label {
            opacity: 0;
            height: 0;
            padding: 0;
            overflow: hidden;
        }

        /* Center icon, hide label */
        .sidebar-mini.sidebar-collapse .tsys-link {
            justify-content: center;
            padding: 10px 0;
            margin: 2px 6px;
            gap: 0;
        }

        .sidebar-mini.sidebar-collapse .tsys-label {
            display: none;
        }

        .sidebar-mini.sidebar-collapse .tsys-icon {
            font-size: 16px;
            width: 20px;
        }

        /* Hide active left-bar when collapsed */
        .sidebar-mini.sidebar-collapse .tsys-link.tsys-active::before {
            display: none;
        }

        /* Tooltip on hover — native browser title attr handles the label */
        .sidebar-mini.sidebar-collapse .tsys-link:hover {
            background: rgba(255,255,255,0.09);
        }

        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .card {
            border: 0 !important;
            border-radius: var(--radius-md) !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.04) !important;
            background: var(--card-bg);
        }

        .card-header {
            border-top-left-radius: var(--radius-md) !important;
            border-top-right-radius: var(--radius-md) !important;
            border-bottom: 1px solid #F0F2F5 !important;
            padding: 14px 20px !important;
            background: var(--card-bg) !important;
            font-weight: 600;
            font-size: 14px;
            color: #1A202C;
        }

        .card-header.with-border {
            border-bottom: 1px solid #E8ECF0 !important;
        }

        .card-primary.card-outline {
            border-top: 3px solid var(--amber) !important;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.04) !important;
        }

        .card-body {
            padding: 20px !important;
        }

        /* Small-box summary cards */
        .small-box {
            border-radius: var(--radius-md) !important;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08) !important;
            overflow: hidden;
        }

        .small-box > .inner {
            padding: 16px 20px 12px;
        }

        .small-box h3 {
            font-size: 28px !important;
            font-weight: 700 !important;
        }

        .small-box p {
            font-size: 13px !important;
            font-weight: 500 !important;
        }

        .small-box .icon {
            top: 12px !important;
        }

        /*
        |--------------------------------------------------------------------------
        | BUTTON
        |--------------------------------------------------------------------------
        */

        .btn {
            border-radius: var(--radius-sm) !important;
            font-size: 13px !important;
            font-weight: 500;
            padding: 6px 14px !important;
            transition: all 0.15s;
        }

        .btn-primary {
            background: #2563EB !important;
            border-color: #2563EB !important;
        }

        .btn-primary:hover {
            background: #1D4ED8 !important;
            border-color: #1D4ED8 !important;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3) !important;
        }

        .btn-sm {
            padding: 4px 10px !important;
            font-size: 12px !important;
        }

        .btn-warning {
            background: var(--amber) !important;
            border-color: var(--amber) !important;
            color: #1A202C !important;
        }

        .btn-warning:hover {
            background: var(--amber-dk) !important;
            border-color: var(--amber-dk) !important;
            color: #1A202C !important;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        table.dataTable tbody td {
            vertical-align: middle;
            font-size: 13px;
            color: #374151;
            border-color: #F3F4F6 !important;
        }

        .table th {
            white-space: nowrap;
            font-size: 11.5px !important;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6B7280 !important;
            background: #F9FAFB !important;
            border-bottom: 2px solid #E5E7EB !important;
            padding: 10px 12px !important;
        }

        .table td {
            padding: 10px 12px !important;
        }

        table.dataTable.no-footer {
            border-bottom: 1px solid #E5E7EB !important;
        }

        /*
        |--------------------------------------------------------------------------
        | DATATABLE SEARCH
        |--------------------------------------------------------------------------
        */

        .dataTables_filter {
            width: 100%;
            margin-bottom: 0 !important;
        }

        .dataTables_filter label {
            width: 100%;
            margin-bottom: 0 !important;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 400 !important;
            font-size: 13px !important;
            color: #6B7280;
        }

        .dataTables_filter input {
            width: 180px !important;
            height: 32px !important;
            border-radius: var(--radius-sm) !important;
            border: 1px solid #E5E7EB !important;
            padding: 0 10px !important;
            font-size: 13px !important;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .dataTables_filter input:focus {
            border-color: #2563EB !important;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1) !important;
            outline: none !important;
        }

        /*
        |--------------------------------------------------------------------------
        | DATATABLE LENGTH
        |--------------------------------------------------------------------------
        */

        .dataTables_length {
            margin: 0 !important;
        }

        .dataTables_length label {
            display: flex !important;
            align-items: center;
            gap: 6px;
            margin: 0 !important;
            font-weight: 400 !important;
            font-size: 13px !important;
            color: #6B7280;
        }

        .dataTables_length select {
            width: 70px !important;
            margin: 0 !important;
            height: 32px !important;
            border-radius: var(--radius-sm) !important;
            border: 1px solid #E5E7EB !important;
            font-size: 13px !important;
        }

        /* DataTable pagination */
        .dataTables_paginate .paginate_button {
            border-radius: var(--radius-sm) !important;
            font-size: 13px !important;
        }

        /* ── Active page ── */
        .dataTables_paginate .paginate_button.current,
        .dataTables_paginate .paginate_button.current:hover,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover,
        .dataTables_wrapper .pagination .page-item.active .page-link,
        .dataTables_wrapper .page-item.active .page-link {
            background-color: var(--amber) !important;
            background-image: none !important;
            border-color: var(--amber-dk) !important;
            color: var(--navy) !important;
            font-weight: 700 !important;
            box-shadow: none !important;
        }

        /* ── Non-active page numbers, Previous, Next ── */
        .dataTables_wrapper .pagination .page-link,
        .dataTables_wrapper .page-link {
            color: var(--amber) !important;
            border-color: #dee2e6 !important;
            transition: background 0.15s, color 0.15s, box-shadow 0.15s;
        }

        /* ── Hover (non-active) ── */
        .dataTables_wrapper .pagination .page-item:not(.active) .page-link:hover,
        .dataTables_wrapper .page-item:not(.active) .page-link:hover {
            background-color: var(--amber-tint) !important;
            border-color: var(--amber-border) !important;
            color: var(--amber-dk) !important;
        }

        /* ── Focus / click glow — orange instead of blue ── */
        .dataTables_wrapper .pagination .page-link:focus,
        .dataTables_wrapper .page-link:focus {
            box-shadow: 0 0 0 3px rgba(245, 166, 35, 0.30) !important;
            outline: none !important;
        }

        /* ── Disabled (Previous on page 1, Next on last page) ── */
        .dataTables_wrapper .pagination .page-item.disabled .page-link {
            color: #C0C8D0 !important;
        }

        /*
        |--------------------------------------------------------------------------
        | CUSTOM TOOLBAR
        |--------------------------------------------------------------------------
        */

        .custom-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            width: 100%;
        }

        .custom-toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .custom-toolbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
        }

        /*
        |--------------------------------------------------------------------------
        | FORM CONTROLS
        |--------------------------------------------------------------------------
        */

        .form-control {
            border-radius: var(--radius-sm) !important;
            border: 1px solid #E5E7EB !important;
            font-size: 13px !important;
            color: #1A202C;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-control:focus {
            border-color: #2563EB !important;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1) !important;
        }

        .form-group label {
            font-size: 12.5px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        /*
        |--------------------------------------------------------------------------
        | BADGE
        |--------------------------------------------------------------------------
        */

        .badge {
            font-weight: 600 !important;
            font-size: 11px !important;
            padding: 4px 8px !important;
            border-radius: 20px !important;
            letter-spacing: 0.02em;
        }

        /*
        |--------------------------------------------------------------------------
        | TOM SELECT
        |--------------------------------------------------------------------------
        */

        .ts-wrapper {
            width: 380px !important;
        }

        .ts-wrapper.multi .ts-control {
            min-height: 32px !important;
            height: 32px !important;
            overflow-x: auto;
            overflow-y: hidden;
            flex-wrap: nowrap;
            padding: 2px 6px;
            border-radius: var(--radius-sm) !important;
            border: 1px solid #E5E7EB !important;
        }

        .ts-wrapper.multi .ts-control > div {
            background: #2563EB !important;
            color: #fff !important;
            border-radius: 4px !important;
            padding: 2px 6px !important;
            font-size: 12px !important;
            white-space: nowrap;
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ts-wrapper .ts-control input {
            font-size: 13px !important;
        }

        /*
        |--------------------------------------------------------------------------
        | SWEET ALERT
        |--------------------------------------------------------------------------
        */

        .swal2-popup {
            border-radius: var(--radius-lg) !important;
            font-family: 'Inter', sans-serif !important;
            font-size: 14px !important;
        }

        .swal2-title {
            font-size: 18px !important;
            font-weight: 700 !important;
        }

        .swal2-confirm {
            border-radius: var(--radius-sm) !important;
        }

        .swal2-cancel {
            border-radius: var(--radius-sm) !important;
        }

        /*
        |--------------------------------------------------------------------------
        | PAGE HEADER
        |--------------------------------------------------------------------------
        */

        .content-header {
            padding: 16px 24px 8px !important;
        }

        .content-header h1 {
            font-size: 20px !important;
            font-weight: 700 !important;
            color: #1A202C;
        }

        .breadcrumb {
            background: transparent !important;
            font-size: 12px !important;
            padding: 0 !important;
        }

        .breadcrumb-item.active {
            color: #9CA3AF !important;
        }

        .breadcrumb-item a {
            color: #2563EB;
            text-decoration: none;
        }

        /* Tooltip on hover when collapsed — positioned via JS (see bottom of file) */
        #tsys-tooltip {
            position: fixed;
            left: calc(4.6rem + 6px);
            background: var(--navy-mid);
            color: #E8F0F8;
            font-size: 12.5px;
            font-weight: 500;
            padding: 7px 14px;
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            white-space: nowrap;
            border-left: 2px solid var(--amber);
            box-shadow: 4px 4px 16px rgba(0,0,0,0.35);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.12s;
            z-index: 9999;
            transform: translateY(-50%);
        }

        #tsys-tooltip.visible { opacity: 1; }

        /* Section divider — visible only in collapsed mode */
        .tsys-divider {
            display: none;
        }

        .sidebar-mini.sidebar-collapse .tsys-divider {
            display: block;
            height: 1px;
            background: rgba(255,255,255,0.18);
            margin: 10px 14px;
        }

        /*
        |--------------------------------------------------------------------------
        | BLOCK AdminLTE's built-in hover-expand on sidebar-mini
        | Default: .sidebar-mini .main-sidebar:hover expands to full width.
        | We override this — tooltip handles label visibility instead.
        |--------------------------------------------------------------------------
        */

        .sidebar-mini.sidebar-collapse .main-sidebar,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover {
            width: 4.6rem !important;
            overflow: hidden !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .brand-link {
            width: 4.6rem !important;
            padding: 0 !important;
            justify-content: center !important;
            overflow: hidden !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .brand-logo-full { display: none !important; }
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .brand-logo-mini { display: block !important; }
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .tsys-label { display: none !important; }
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .tsys-section-label {
            opacity: 0 !important;
            height: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .tsys-link {
            justify-content: center !important;
            padding: 10px 0 !important;
            gap: 0 !important;
        }
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .tsys-divider {
            display: block !important;
        }

        /*
        |--------------------------------------------------------------------------
        | SCROLLBAR (webkit)
        |--------------------------------------------------------------------------
        */

        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.10); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    </style>

    <!-- STACK STYLE -->
    @stack('styles')
</head>

<body class="hold-transition sidebar-mini layout-fixed">

<div class="wrapper">

    <!-- Sidebar tooltip (collapsed mode) -->
    <div id="tsys-tooltip"></div>

    <!-- ===================================================== -->
    <!-- NAVBAR -->
    <!-- ===================================================== -->

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">

        <ul class="navbar-nav">

            <li class="nav-item">

                <a 
                    class="nav-link"
                    data-widget="pushmenu"
                    href="#"
                    role="button"
                >
                    <i class="fas fa-bars"></i>
                </a>

            </li>

        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto align-items-center">

            <!-- User Dropdown -->
            <li class="nav-item dropdown">
                
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-user"></i>
                    <span class="ml-2" style="font-weight:600; font-size:13px;">{{ Auth::user()->name }}</span>
                    <i class="fas fa-caret-down ml-2" style="font-size:11px; color:#9CA3AF;"></i>
                </a>

                <div class="dropdown-menu dropdown-menu-right" style="min-width: 280px;">
                    
                    <!-- User Info -->
                    <div class="dropdown-header">
                        <strong>{{ Auth::user()->name }}</strong>
                        <small class="d-block text-muted mt-1">{{ Auth::user()->email }}</small>
                    </div>

                    <div class="dropdown-divider"></div>

                    <!-- Role Info -->
                    <div class="px-3 py-2">
                        <small class="text-muted d-block">
                            <i class="fas fa-user-tag mr-1"></i>
                            <strong>Role</strong>
                        </small>
                        <small class="d-block mt-1" style="font-weight:600; color:#1A202C;">
                            {{ Str::upper(Auth::user()->role->name ?? '-') }}
                        </small>
                    </div>

                    <!-- Access Group Info -->
                    <div class="px-3 py-2">
                        <small class="text-muted d-block">
                            <i class="fas fa-users mr-1"></i>
                            <strong>Access Group</strong>
                        </small>
                        <small class="d-block mt-1" style="font-weight:600; color:#1A202C;">
                            {{ Auth::user()->accessGroup->name ?? '-' }}
                        </small>
                    </div>

                    <div class="dropdown-divider"></div>

                    <!-- Logout -->
                    <form action="{{ route('logout') }}" method="POST" id="logout-form">
                        @csrf
                        <button type="button" class="dropdown-item text-danger" onclick="confirmLogout()">
                            <i class="fas fa-sign-out-alt mr-2"></i>
                            Logout
                        </button>
                    </form>

                </div>

            </li>

        </ul>

    </nav>

    <!-- ===================================================== -->
    <!-- SIDEBAR -->
    <!-- ===================================================== -->

    <aside class="main-sidebar elevation-0">

        <!-- BRAND -->
        <a href="{{ url('/') }}" class="brand-link">

            <!-- Logo Full -->
            <img
                src="{{ asset('dist/img/TesysLogo.png') }}"
                class="brand-logo-full"
                alt="Terex System"
            >

            <!-- Logo Icon -->
            <img
                src="{{ asset('dist/img/ts_icon.png') }}"
                class="brand-logo-mini"
                alt="Terex System"
            >

        </a>

        <!-- SIDEBAR -->
        <div class="sidebar os-host os-theme-light os-host-overflow os-host-overflow-y os-host-resize-disabled os-host-scrollbar-horizontal-hidden os-host-transition">

            <nav class="mt-2 pb-3">

                @php
                    $role = Auth::user()->role;
                    $can  = fn(string $key): bool => $role && $role->hasMenu($key);

                    $sdmActive            = request()->is('service-delivery-summary*', 'project-monitoring*')
                        || (request()->is('budget-plan*', 'budget-request*') && request('from') === 'delivery');
                    $financeProjectActive = request()->is('project*');
                    $fmActive             = request()->is('finance-summary*', 'reports*', 'invoice-cash-receipt*', 'project*')
                        || (request()->is('budget-plan*', 'budget-request*') && request('from') !== 'delivery');
                @endphp

                {{-- ── DASHBOARD ── --}}
                @if($can('dashboard'))
                <ul class="tsys-nav">
                    <li class="tsys-section-label">Dashboard</li>
                    <li>
                        <a href="{{ url('/') }}" class="tsys-link {{ request()->is('/') ? 'tsys-active' : '' }}" title="Dashboard">
                            <i class="fas fa-tachometer-alt tsys-icon"></i>
                            <span class="tsys-label">Dashboard</span>
                        </a>
                    </li>
                </ul>
                @endif

                {{-- ── MASTER ── --}}
                @if($can('customer') || $can('project') || $can('milestone') || $can('beneficiaries') || $can('account') || $can('master-pic') || $can('source-fund') || $can('cost-center') || $can('employee') || $can('user-access'))
                <li class="tsys-divider"></li>
                <ul class="tsys-nav">
                    <li class="tsys-section-label">Master Data</li>
                    @if($can('customer'))
                    <li>
                        <a href="/customer" class="tsys-link {{ request()->is('customer*') ? 'tsys-active' : '' }}" title="Customer">
                            <i class="fas fa-building tsys-icon"></i>
                            <span class="tsys-label">Customer</span>
                        </a>
                    </li>
                    @endif
                    @if($can('project'))
                    <li>
                        <a href="/cluster" class="tsys-link {{ request()->is('cluster*') ? 'tsys-active' : '' }}" title="Project">
                            <i class="fas fa-project-diagram tsys-icon"></i>
                            <span class="tsys-label">Project</span>
                        </a>
                    </li>
                    @endif
                    @if($can('milestone'))
                    <li>
                        <a href="/stage" class="tsys-link {{ request()->is('stage*') ? 'tsys-active' : '' }}" title="Milestone">
                            <i class="fas fa-stream tsys-icon"></i>
                            <span class="tsys-label">Milestone</span>
                        </a>
                    </li>
                    @endif
                    @if($can('beneficiaries'))
                    <li>
                        <a href="/beneficiaries" class="tsys-link {{ request()->is('beneficiaries*') ? 'tsys-active' : '' }}" title="Beneficiaries">
                            <i class="fas fa-user-friends tsys-icon"></i>
                            <span class="tsys-label">Beneficiaries</span>
                        </a>
                    </li>
                    @endif
                    @if($can('account'))
                    <li>
                        <a href="/account" class="tsys-link {{ request()->is('account*') ? 'tsys-active' : '' }}" title="Account">
                            <i class="fas fa-list tsys-icon"></i>
                            <span class="tsys-label">Account</span>
                        </a>
                    </li>
                    @endif
                    @if($can('master-pic'))
                    <li>
                        <a href="/master-pic" class="tsys-link {{ request()->is('master-pic*') ? 'tsys-active' : '' }}" title="PIC">
                            <i class="fas fa-id-badge tsys-icon"></i>
                            <span class="tsys-label">PIC</span>
                        </a>
                    </li>
                    @endif
                    @if($can('source-fund'))
                    <li>
                        <a href="/source-fund" class="tsys-link {{ request()->is('source-fund*') ? 'tsys-active' : '' }}" title="Source Fund">
                            <i class="fas fa-piggy-bank tsys-icon"></i>
                            <span class="tsys-label">Source Fund</span>
                        </a>
                    </li>
                    @endif
                    @if($can('cost-center'))
                    <li>
                        <a href="/cost-center" class="tsys-link {{ request()->is('cost-center*') ? 'tsys-active' : '' }}" title="Cost Center">
                            <i class="fas fa-sitemap tsys-icon"></i>
                            <span class="tsys-label">Cost Center</span>
                        </a>
                    </li>
                    @endif
                    @if($can('employee'))
                    <li>
                        <a href="/employee" class="tsys-link {{ request()->is('employee*') ? 'tsys-active' : '' }}" title="Karyawan">
                            <i class="fas fa-users tsys-icon"></i>
                            <span class="tsys-label">Employee</span>
                        </a>
                    </li>
                    @endif
                    @if($can('user-access'))
                    <li>
                        <a href="/user" class="tsys-link {{ request()->segment(1) == 'user' || request()->segment(1) == 'accessgroup' || request()->segment(1) == 'role' ? 'tsys-active' : '' }}" title="User &amp; Access">
                            <i class="fas fa-users tsys-icon"></i>
                            <span class="tsys-label">User &amp; Access</span>
                        </a>
                    </li>
                    @endif
                </ul>
                @endif

                {{-- ── FINANCE MANAGEMENT ── --}}
                @if($can('finance-summary') || $can('list-po') || $can('finance-budgeting') || $can('finance-cost-request') || $can('invoice-cash-receipt') || $can('reports'))
                <li class="tsys-divider"></li>
                <ul class="tsys-nav">
                    <li class="tsys-section-label">Finance</li>
                    @if($can('finance-summary'))
                    <li>
                        <a href="/finance-summary" class="tsys-link {{ request()->is('finance-summary*') ? 'tsys-active' : '' }}" title="Summary">
                            <i class="fas fa-chart-pie tsys-icon"></i>
                            <span class="tsys-label">Summary</span>
                        </a>
                    </li>
                    @endif
                    @if($can('list-po'))
                    <li>
                        <a href="/project" class="tsys-link {{ $financeProjectActive ? 'tsys-active' : '' }}" title="List PO">
                            <i class="fas fa-file-contract tsys-icon"></i>
                            <span class="tsys-label">Purchase Order</span>
                        </a>
                    </li>
                    @endif
                    @if($can('finance-budgeting'))
                    <li>
                        <a href="/budget-plan?from=finance" class="tsys-link {{ request()->is('budget-plan*') && $fmActive ? 'tsys-active' : '' }}" title="Budgeting">
                            <i class="fas fa-money-check-alt tsys-icon"></i>
                            <span class="tsys-label">Budgeting</span>
                        </a>
                    </li>
                    @endif
                    @if($can('finance-cost-request'))
                    <li>
                        <a href="/budget-request?from=finance" class="tsys-link {{ request()->is('budget-request*') && $fmActive ? 'tsys-active' : '' }}" title="Cost Request">
                            <i class="fas fa-file-invoice-dollar tsys-icon"></i>
                            <span class="tsys-label">Cost Request</span>
                        </a>
                    </li>
                    @endif
                    @if($can('finance-daily-transaction'))
                    <li>
                        <a href="/daily-transaction" class="tsys-link {{ request()->is('daily-transaction*') ? 'tsys-active' : '' }}" title="Daily Transaction">
                            <i class="fas fa-layer-group tsys-icon"></i>
                            <span class="tsys-label">Treasury</span>
                        </a>
                    </li>
                    @endif
                    @if($can('invoice-cash-receipt'))
                    <li>
                        <a href="/invoice-cash-receipt" class="tsys-link {{ request()->is('invoice-cash-receipt*') ? 'tsys-active' : '' }}" title="Invoice &amp; Cash Receipt">
                            <i class="fas fa-receipt tsys-icon"></i>
                            <span class="tsys-label">Invoice &amp; Cash Receipt</span>
                        </a>
                    </li>
                    @endif
                    @if($can('reports'))
                    <li>
                        <a href="/reports" class="tsys-link {{ request()->is('reports*') ? 'tsys-active' : '' }}" title="Reports">
                            <i class="fas fa-chart-bar tsys-icon"></i>
                            <span class="tsys-label">Reports</span>
                        </a>
                    </li>
                    @endif
                </ul>
                @endif

                {{-- ── PAYROLL MANAGEMENT ── --}}
                @if($can('payroll-run'))
                <li class="tsys-divider"></li>
                <ul class="tsys-nav">
                    <li class="tsys-section-label">Payroll</li>
                    <li>
                        <a href="/payroll-run" class="tsys-link {{ request()->is('payroll-run*') ? 'tsys-active' : '' }}" title="Payroll Run">
                            <i class="fas fa-file-invoice-dollar tsys-icon"></i>
                            <span class="tsys-label">Payroll Run</span>
                        </a>
                    </li>
                </ul>
                @endif

                {{-- ── PROJECT MANAGEMENT ── --}}
                @if($can('project-summary') || $can('project-budgeting') || $can('project-cost-request') || $can('project-monitoring'))
                <li class="tsys-divider"></li>
                <ul class="tsys-nav">
                    <li class="tsys-section-label">Project</li>
                    @if($can('project-summary'))
                    <li>
                        <a href="/service-delivery-summary" class="tsys-link {{ request()->is('service-delivery-summary*') ? 'tsys-active' : '' }}" title="Summary">
                            <i class="fas fa-chart-pie tsys-icon"></i>
                            <span class="tsys-label">Summary</span>
                        </a>
                    </li>
                    @endif
                    @if($can('project-monitoring'))
                    <li>
                        <a href="/project-monitoring" class="tsys-link {{ request()->is('project-monitoring*') ? 'tsys-active' : '' }}" title="Project Monitoring">
                            <i class="fas fa-binoculars tsys-icon"></i>
                            <span class="tsys-label">Project Monitoring</span>
                        </a>
                    </li>
                    @endif
                </ul>
                @endif

            </nav>

        </div>

    </aside>

    <!-- ===================================================== -->
    <!-- CONTENT -->
    <!-- ===================================================== -->

    <div class="content-wrapper">

        <section class="content pt-3">

            <div class="container-fluid">

                @yield('content')

            </div>

        </section>

    </div>

</div>

<!-- ===================================================== -->
<!-- JQUERY -->
<!-- ===================================================== -->

<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>

<!-- BOOTSTRAP -->
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

<!-- DATATABLE -->
<script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>

<script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>

<script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>

<script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>

<!-- ADMIN LTE -->
<script src="{{ asset('dist/js/adminlte.min.js') }}"></script>

<!-- TOM SELECT -->
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<!-- ===================================================== -->
<!-- SWEET ALERT SUCCESS -->
<!-- ===================================================== -->

@if(session('success'))
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: "{{ session('success') }}",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
@endif

<!-- ===================================================== -->
<!-- SWEET ALERT ERROR -->
<!-- ===================================================== -->

@if(session('error'))
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'error',
    title: "{{ session('error') }}",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>

<!-- LOGOUT CONFIRMATION -->
<script>
function confirmLogout() {
    Swal.fire({
        title: 'Logout?',
        text: 'Apakah Anda yakin ingin keluar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Logout',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('logout-form').submit();
        }
    });
}
</script>

<!-- SIDEBAR TOOLTIP (collapsed mode) -->
<script>
(function () {
    var tooltip = document.getElementById('tsys-tooltip');
    if (!tooltip) return;

    function isCollapsed() {
        return document.body.classList.contains('sidebar-collapse');
    }

    document.querySelectorAll('.tsys-link').forEach(function (link) {
        link.addEventListener('mouseenter', function (e) {
            if (!isCollapsed()) return;
            var label = link.getAttribute('title');
            if (!label) return;
            tooltip.textContent = label;
            var rect = link.getBoundingClientRect();
            tooltip.style.top = (rect.top + rect.height / 2) + 'px';
            tooltip.classList.add('visible');
        });

        link.addEventListener('mouseleave', function () {
            tooltip.classList.remove('visible');
        });
    });
})();
</script>

<!-- PAGE SCRIPT -->
@stack('scripts')

</body>
</html>