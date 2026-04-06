<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <meta name="description" content="Property Management.">
    <meta name="keywords" content="Property administration, Property management system, Chabrin Agencies Ltd Property Manager, retirement fund software, Property fund administration, Property solution, member management, contributions, benefits">

    <meta name="author" content="pixelstrap">
    <link rel="icon" href="<?php echo e(asset('assets/images/favicon.png')); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo e(asset('assets/images/favicon.png')); ?>" type="image/x-icon">
    <title>Property Manager</title>

    <!-- Google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">

    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@200;300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i,900&amp;display=swap" rel="stylesheet">

    <link rel="stylesheet" type="text/css" href="<?php echo e(asset('assets/css/font-awesome.css')); ?>">

    <!-- Themify icon-->
    <link rel="stylesheet" type="text/css" href="<?php echo e(asset('assets/css/vendors/themify.css')); ?>">

    <link rel="stylesheet" type="text/css" href="<?php echo e(asset('assets/css/vendors/scrollbar.css')); ?>">
    <link rel="stylesheet" type="text/css" href="<?php echo e(asset('assets/css/vendors/animate.css')); ?>">


    <link href="<?php echo e(asset('assets/datatables/datatables.min.css')); ?>" rel="stylesheet">

    <!-- Plugins css Ends-->
    <!-- Bootstrap css-->
    <link rel="stylesheet" type="text/css" href="<?php echo e(asset('assets/css/vendors/bootstrap.css')); ?>">
    <!-- App css-->
    <link rel="stylesheet" type="text/css" href="<?php echo e(asset('assets/css/style.css')); ?>">
    <link rel="stylesheet" type="text/css" href="<?php echo e(asset('assets/css/style.css')); ?>">
    <link id="color" rel="stylesheet" href="<?php echo e(asset('assets/css/color-1.css')); ?>" media="screen">
    <!-- Responsive css-->
    <link rel="stylesheet" type="text/css" href="<?php echo e(asset('assets/css/select2/select2.min.css')); ?>">

    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/toastr.min.css')); ?>">

    <?php echo $__env->yieldContent('styles'); ?>

</head>
<style>
    /* ============================================ */
    /* MASTER LAYOUT — overrides theme completely   */
    /* ============================================ */
    :root {
        --sidebar-width: 160px;
        --header-height: 42px;
        --accent: #3598db;
        --accent-dark: #217dbb;
    }

    .material-symbols-outlined {
        font-size: 20px !important;
    }

    .card-header {
        padding: 1.25rem 1.5rem;
        box-shadow: 0 4px 12px -6px rgba(0, 0, 0, 0.15);
        border-bottom: none;
    }

    .employee-details-table td,
    .employee-details-table tr {
        padding: 4px 12px !important;
    }

    .spin {
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .toast,
    .toast *,
    .toast .toast-body,
    .toast-message,
    .toast .toast-message {
        color: #fff !important;
    }

    /* ---- TOP NAV ---- */
    .page-wrapper.compact-wrapper .page-header,
    .page-wrapper.compact-wrapper .page-header.close_icon,
    .page-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: var(--header-height) !important;
        min-height: var(--header-height) !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        background: #fff !important;
        border-bottom: 1px solid #e9ecef;
        z-index: 999 !important;
    }

    .header-wrapper {
        height: var(--header-height) !important;
        align-items: center !important;
        padding: 0 15px !important;
    }

    .header-logo-wrapper {
        height: var(--header-height) !important;
        display: flex;
        align-items: center;
    }

    .nav-right {
        margin-left: auto !important;
        height: var(--header-height) !important;
        display: flex;
        align-items: center;
    }

    .nav-menus {
        height: var(--header-height) !important;
        display: flex;
        align-items: center;
        margin: 0 !important;
        gap: 15px;
    }

    .nav-menus li {
        display: flex;
        align-items: center;
        height: var(--header-height);
    }

    .nav-menus .material-symbols-outlined {
        color: var(--accent) !important;
        font-size: 20px !important;
    }

    .nav-menus .message .material-symbols-outlined {
        color: var(--accent) !important;
    }

    /* Profile */
    .profile-nav .profile-media {
        height: var(--header-height) !important;
        padding: 0 !important;
        gap: 8px !important;
    }

    .profile-nav .profile-avatar {
        width: 28px !important;
        height: 28px !important;
        font-size: 28px !important;
        color: var(--accent) !important;
        border-radius: 50% !important;
    }

    .profile-nav .user {
        display: flex !important;
        align-items: center !important;
        gap: 4px !important;
        line-height: 1 !important;
        margin: 0 !important;
    }

    .profile-nav .user span:first-child {
        font-size: 12px !important;
        font-weight: 600 !important;
        color: var(--accent) !important;
        margin: 0 !important;
        white-space: nowrap !important;
    }

    .profile-nav .user .material-symbols-outlined {
        color: var(--accent) !important;
        font-size: 16px !important;
    }

    .profile-dropdown {
        min-width: 150px !important;
    }

    .profile-dropdown li {
        margin: 0 !important;
    }

    .profile-dropdown li a {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        padding: 8px 15px !important;
        text-decoration: none !important;
        color: #333 !important;
        transition: background-color 0.2s !important;
    }

    .profile-dropdown li a:hover {
        background-color: #f8f9fa !important;
    }

    .profile-dropdown li a .material-symbols-outlined {
        font-size: 18px !important;
        color: var(--accent) !important;
    }

    .profile-dropdown li a span:last-child {
        font-size: 14px !important;
        font-weight: 500 !important;
    }

    .message {
        position: relative !important;
        padding: 8px !important;
    }

    .message .badge-danger {
        background-color: #dc3545 !important;
        position: absolute !important;
        top: 5px !important;
        right: 5px !important;
        width: 8px !important;
        height: 8px !important;
    }

    /* ---- SIDEBAR ---- */
    .page-wrapper.compact-wrapper .sidebar-wrapper,
    .sidebar-wrapper {
        position: fixed !important;
        top: var(--header-height) !important;
        left: 0 !important;
        bottom: 0 !important;
        width: var(--sidebar-width) !important;
        height: auto !important;
        background: #f5fafe !important;
        border-right: 1px solid #e4eaf0 !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        z-index: 998 !important;
        padding: 0 !important;
        margin: 0 !important;
        transition: none !important;
    }

    /* Hide theme's compact-wrapper icon-only collapsed state */
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon {
        width: var(--sidebar-width) !important;
    }

    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon .sidebar-main,
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon nav,
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon .logo-wrapper,
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon .sidebar-links,
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon .sidebar-main-title,
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon #sidebar-menu {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon .sidebar-link span:not(.material-symbols-outlined),
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon .sm-label,
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon .sidebar-main-title h6 {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: auto !important;
        max-width: none !important;
    }

    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon .sidebar-link {
        text-align: left !important;
        justify-content: flex-start !important;
    }

    /* Kill any theme hover-expand behaviour on desktop */
    .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon:hover {
        width: var(--sidebar-width) !important;
    }

    .sidebar-wrapper .logo-wrapper {
        padding: 10px 12px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .sidebar-wrapper .logo-wrapper p {
        margin: 0 !important;
        font-size: 13px !important;
    }

    .logo-wrapper p a {
        color: inherit !important;
        text-decoration: none !important;
    }

    .logo-wrapper p a:hover {
        color: var(--accent) !important;
    }

    /* Hide theme bits we don't need in sidebar */
    .logo-icon-wrapper,
    .sidebar-wrapper .toggle-sidebar,
    .left-arrow,
    .right-arrow,
    .sidebar-wrapper .logo-wrapper img {
        display: none !important;
    }

    /* Sidebar menu items */
    .sidebar-links {
        padding: 0 !important;
        margin: 0 !important;
        list-style: none !important;
    }

    .sidebar-main-title {
        padding: 10px 14px 4px !important;
    }

    .sidebar-main-title h6 {
        font-size: 10px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.08em !important;
        color: #94a3b8 !important;
        margin: 0 !important;
    }

    .sidebar-list .sidebar-link {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        padding: 7px 14px !important;
        color: #334155 !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        text-decoration: none !important;
        cursor: pointer;
        transition: background 0.12s ease;
    }

    .sidebar-list .sidebar-link:hover {
        background: rgba(53, 152, 219, 0.06);
    }

    .sidebar-list .sidebar-link .material-symbols-outlined {
        color: var(--accent) !important;
        font-size: 18px !important;
    }

    .sidebar-submenu {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 0 4px 0 !important;
        border: none !important;
        border-left: none !important;
        border-inline-start: none !important;
    }

    .sidebar-submenu::before,
    .sidebar-submenu li::before,
    .sidebar-submenu li::after {
        display: none !important;
        content: none !important;
    }

    .sidebar-submenu li a {
        display: flex !important;
        align-items: center !important;
        gap: 4px !important;
        padding: 5px 14px 5px 28px !important;
        font-size: 12.5px !important;
        color: #64748b !important;
        text-decoration: none !important;
        transition: color 0.12s ease;
    }

    .sidebar-submenu li a:hover {
        color: var(--accent) !important;
    }

    .sidebar-submenu li a .material-symbols-outlined {
        font-size: 16px !important;
    }

    /* ---- PAGE BODY & CONTENT ---- */
    .page-wrapper {
        padding: 0 !important;
        margin: 0 !important;
    }

    .page-wrapper .page-body-wrapper .page-title {
        display: none !important;
    }

    .page-header+.breadcrumb-wrapper,
    .page-header+.breadcrumb,
    .page-header+.page-breadcrumb {
        display: none !important;
    }

    .page-wrapper.compact-wrapper .page-body-wrapper,
    .page-body-wrapper {
        display: block !important;
        margin-left: var(--sidebar-width) !important;
        margin-top: var(--header-height) !important;
        padding: 0 !important;
        width: calc(100% - var(--sidebar-width)) !important;
        max-width: calc(100% - var(--sidebar-width)) !important;
        min-height: calc(100vh - var(--header-height)) !important;
        float: none !important;
        flex: none !important;
    }

    /* Reset ALL theme margin/padding on .page-body — theme sets margin-left:253px */
    .page-wrapper .page-body-wrapper .page-body,
    .page-wrapper.compact-wrapper .page-body-wrapper .page-body {
        display: block !important;
        padding: 15px !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        transition: none !important;
    }

    .page-body .card {
        margin-bottom: 20px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    }

    /* Ensure container-fluid inside content doesn't add extra padding */
    .page-body>.container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
    }

    /* ---- FOOTER ---- */
    .footer {
        height: 36px !important;
        min-height: 36px !important;
        padding: 6px 0 !important;
        background: #f8f9fa !important;
        border-top: 1px solid #e9ecef !important;
    }

    .footer .container-fluid {
        padding: 0 15px !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
    }

    .footer .row {
        width: 100% !important;
        margin: 0 !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
    }

    .footer-copyright {
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
    }

    .footer-copyright p {
        margin: 0 !important;
        font-size: 12px !important;
        color: #6c757d !important;
        line-height: 1 !important;
    }

    /* ---- TABLES ---- */
    table.dataTable tbody td,
    table.dataTable tbody th {
        padding: 2px 4px !important;
        font-size: 14px !important;
    }

    table.dataTable thead th {
        padding: 8px 10px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
    }

    table.dataTable {
        font-size: 13px !important;
    }

    .dataTables_info,
    .dataTables_length,
    .dataTables_filter {
        font-size: 12px !important;
    }

    .dataTables_paginate {
        font-size: 12px !important;
    }

    /* ============================================ */
    /* SUPER MODULE ITEMS IN SIDEBAR                */
    /* ============================================ */
    .super-module-item .sidebar-link {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 10px 4px !important;
        text-align: center !important;
        gap: 4px !important;
        cursor: pointer;
        border-radius: 8px;
        transition: background 0.15s ease;
    }

    .super-module-item .sidebar-link .sm-icon {
        font-size: 38px !important;
        color: var(--accent) !important;
        line-height: 1;
        transition: transform 0.15s ease, color 0.15s ease;
    }

    .super-module-item .sidebar-link .sm-label {
        font-size: 11.5px !important;
        font-weight: 600;
        color: var(--accent);
        line-height: 1.2;
        letter-spacing: 0.02em;
        transition: color 0.15s ease;
    }

    .super-module-item:hover .sidebar-link {
        background: rgba(53, 152, 219, 0.08);
    }

    .super-module-item:hover .sidebar-link .sm-icon {
        transform: scale(1.12);
        color: var(--accent-dark) !important;
    }

    .super-module-item:hover .sidebar-link .sm-label {
        color: var(--accent-dark);
    }

    .super-module-item.active .sidebar-link {
        background: rgba(53, 152, 219, 0.1) !important;
    }

    .super-module-item.active .sidebar-link .sm-icon {
        color: var(--accent-dark) !important;
        transform: scale(1.12);
    }

    .super-module-item.active .sidebar-link .sm-label {
        color: var(--accent-dark);
    }

    .super-modules-grid {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 4px 8px;
    }

    .super-modules-grid .super-module-item {
        list-style: none;
    }

    /* ============================================ */
    /* FLYOUT DRAWER                                */
    /* ============================================ */
    .super-module-flyout {
        position: fixed;
        top: var(--header-height);
        left: var(--sidebar-width);
        width: 270px;
        bottom: 0;
        background: #ffffff;
        border-right: 1px solid #e8ecf1;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
        z-index: 10000;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.08s ease, visibility 0.08s ease;
    }

    .super-module-flyout.open {
        opacity: 1;
        visibility: visible;
    }

    .super-module-flyout-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: linear-gradient(135deg, #f5fafe, #e8f4fd);
        border-bottom: 1px solid #e0eaf3;
        flex-shrink: 0;
    }

    .super-module-flyout-icon {
        color: var(--accent);
        font-size: 26px;
    }

    .super-module-flyout-header h6 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: #2c3e50;
        flex: 1;
    }

    .super-module-flyout-body {
        flex: 1;
        overflow-y: auto;
        padding: 10px 0;
    }

    .flyout-module-group {
        margin-bottom: 2px;
    }

    .flyout-module-title {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        font-weight: 600;
        font-size: 13.5px;
        color: #2c3e50;
        background: #f8fafc;
        border-bottom: 1px solid #f0f3f6;
        cursor: pointer;
        user-select: none;
        transition: background 0.12s ease;
    }

    .flyout-module-title:hover {
        background: #eef6fc;
    }

    .flyout-module-title .flyout-expand-icon {
        margin-left: auto;
        font-size: 18px;
        color: #94a3b8;
        transition: transform 0.15s ease;
    }

    .flyout-module-group.expanded .flyout-module-title .flyout-expand-icon {
        transform: rotate(180deg);
        color: var(--accent);
    }

    .flyout-module-group.expanded .flyout-module-title {
        background: #eef6fc;
        color: var(--accent);
    }

    .flyout-submenu-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: none;
    }

    .flyout-module-group.expanded .flyout-submenu-list {
        display: block;
    }

    .flyout-submenu-list li a {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 7px 18px 7px 30px;
        color: #555e68;
        text-decoration: none;
        font-size: 13px;
        transition: all 0.12s ease;
        border-left: 3px solid transparent;
    }

    .flyout-submenu-list li a:hover {
        background: #eef6fc;
        color: var(--accent);
        border-left-color: var(--accent);
    }

    .super-module-flyout-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: transparent;
        z-index: 9999;
        display: none;
        pointer-events: none;
    }

    .super-module-flyout-overlay.active {
        display: block;
    }

    /* ============================================ */
    /* DESKTOP — hide mobile bits                   */
    /* ============================================ */
    @media (min-width: 992px) {

        .mobile-hamburger,
        .drawer-close-btn,
        .bg-overlay,
        .drawer-overlay {
            display: none !important;
        }

        .sidebar-wrapper .logo-wrapper .toggle-sidebar,
        .header-logo-wrapper .toggle-sidebar {
            display: none !important;
        }
    }

    /* ============================================ */
    /* MOBILE — sidebar as drawer                   */
    /* ============================================ */
    @media (max-width: 991px) {
        .sidebar-wrapper {
            top: 0 !important;
            left: -280px !important;
            width: 280px !important;
            height: 100vh !important;
            z-index: 9999 !important;
            transition: left 0.3s ease !important;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1) !important;
        }

        .sidebar-wrapper.sidebar-open {
            left: 0 !important;
        }

        .page-wrapper.compact-wrapper .sidebar-wrapper.close_icon {
            width: 280px !important;
            left: -280px !important;
        }

        .page-wrapper.compact-wrapper .sidebar-wrapper:not(.close_icon) {
            left: 0 !important;
        }

        .page-body-wrapper,
        .page-wrapper.compact-wrapper .page-body-wrapper {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .page-wrapper .page-body-wrapper .page-body,
        .page-wrapper.compact-wrapper .page-body-wrapper .page-body {
            margin: 0 !important;
        }

        .mobile-hamburger {
            display: flex !important;
            order: -1 !important;
            position: absolute !important;
            left: 10px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
        }

        .mobile-hamburger .toggle-sidebar {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 44px !important;
            height: 44px !important;
            background: transparent !important;
            border: none !important;
            cursor: pointer !important;
            border-radius: 8px !important;
        }

        .mobile-hamburger .toggle-sidebar:hover {
            background: rgba(53, 152, 219, 0.1) !important;
        }

        .mobile-hamburger .toggle-sidebar .material-symbols-outlined {
            font-size: 30px !important;
            color: var(--accent) !important;
        }

        .header-wrapper {
            position: relative !important;
        }

        .header-logo-wrapper {
            margin-left: 55px !important;
        }

        .sidebar-wrapper .logo-wrapper .toggle-sidebar {
            display: none !important;
        }

        .drawer-overlay {
            position: fixed !important;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5) !important;
            z-index: 9998 !important;
            display: none !important;
        }

        .drawer-overlay.active {
            display: block !important;
        }

        .drawer-close-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: absolute !important;
            top: 12px !important;
            right: 12px !important;
            width: 34px !important;
            height: 34px !important;
            background: var(--accent) !important;
            color: #fff !important;
            border-radius: 50% !important;
            cursor: pointer !important;
            z-index: 10 !important;
            border: none !important;
        }

        .drawer-close-btn:hover {
            background: var(--accent-dark) !important;
        }

        .drawer-close-btn .material-symbols-outlined {
            font-size: 18px !important;
            color: #fff !important;
        }

        .sidebar-wrapper .logo-wrapper {
            padding-right: 48px !important;
            position: relative !important;
        }

        .super-module-flyout {
            top: 0 !important;
            left: 0 !important;
            width: 85% !important;
            max-width: 300px !important;
            height: 100vh !important;
            bottom: auto !important;
        }

        .super-modules-grid {
            padding: 4px 6px;
        }
    }
</style>

<body>
    <!-- loader starts-->
    <div class="loader-wrapper">
        <div class="theme-loader">
            <div class="loader-p"></div>
        </div>
    </div>
    <!-- loader ends-->
    <!-- tap on top starts-->
    <div class="tap-top"><i data-feather="chevrons-up"></i></div>
    <!-- tap on tap ends-->

    <!-- Mobile drawer overlay -->
    <div class="drawer-overlay"></div>

    <!-- page-wrapper Start-->
    <div class="page-wrapper compact-wrapper" id="pageWrapper">
        <!-- Page Header Start-->
        <div class="page-header">
            <div class="header-wrapper row m-0">
                <div class="header-logo-wrapper col-auto p-0">
                    <div class="logo-wrapper"><a href="<?php echo e(url('/')); ?>"> <img class="img-fluid for-light" src="<?php echo e(asset('assets/images/logo/logo.png')); ?>" alt=""><img class="img-fluid for-dark" src="<?php echo e(asset('assets/images/logo/logo_dark.png')); ?>" alt=""></a></div>
                </div>
                <div class="nav-right col-xl-8 col-lg-12 col-auto pull-right right-header p-0">
                    <ul class="nav-menus">
                        <li class="mobile-hamburger">
                            <div class="toggle-sidebar">
                                <span class="material-symbols-outlined">
                                    menu
                                </span>
                            </div>
                        </li>
                        <li class="serchinput">
                            <div class="serchbox">
                                <svg>
                                    <use href="../assets/svg/icon-sprite.svg#search"></use>
                                </svg>
                            </div>
                            <div class="form-group search-form">
                                <input type="text" placeholder="Search here...">
                            </div>
                        </li>


                        <li class="onhover-dropdown">
                            <div class="message position-relative" id="notificationBell" style="cursor: pointer;">
                                <span class="material-symbols-outlined">
                                    notifications_active
                                </span>
                                <span class="rounded-pill badge-danger" id="notificationBadge" style="display: none;"></span>
                            </div>
                            <div class="onhover-show-div message-dropdown" id="notificationDropdown">
                                <h6 class="f-18 mb-0 dropdown-title d-flex justify-content-between align-items-center">
                                    Notifications
                                    <button class="btn btn-link btn-sm p-0" id="markAllReadBtn" style="display: none;">Mark all read</button>
                                </h6>
                                <ul id="notificationList">
                                    <li class="text-center p-3">
                                        <span class="text-muted">Loading notifications...</span>
                                    </li>
                                </ul>
                            </div>
                        </li>


                        <li class="profile-nav onhover-dropdown pe-0 py-0">
                            <div class="d-flex align-items-center profile-media">
                                <span class="material-symbols-outlined profile-avatar">account_circle</span>
                                <div class="user">
                                    <span><?php echo e(optional(Auth::user())->username); ?></span>
                                    <span class="material-symbols-outlined">arrow_drop_down_circle</span>
                                </div>
                            </div>
                            <ul class="profile-dropdown onhover-show-div">
                                <li><a href="<?php echo e(route('user.profile')); ?>"><span class="material-symbols-outlined">person</span><span>Profile</span></a></li>
                                <li>
                                    <form method="POST" action="<?php echo e(route('logout')); ?>" style="display: inline;">
                                        <?php echo csrf_field(); ?>
                                        <a href="#" onclick="event.preventDefault(); this.closest('form').submit();" style="display: flex; align-items: center; text-decoration: none; color: inherit;">
                                            <span class="material-symbols-outlined">logout</span><span>Log Out</span>
                                        </a>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
                <script class="result-template" type="text/x-handlebars-template">
                    <div class="ProfileCard u-cf">              
            <div class="ProfileCard-avatar"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-airplay m-0"><path d="M5 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1"></path><polygon points="12 15 17 21 7 21 12 15"></polygon></svg></div>
            <div class="ProfileCard-details">
            <div class="ProfileCard-realName">name</div>
            </div>
            </div>
          </script>
                <script class="empty-template"
                    type="text/x-handlebars-template">
                    <div class="EmptyMessage">
                    Your search turned up 0 results. 
                    This most likely means the backend is down, 
                    yikes!</div>
                </script>
            </div>
        </div>
        <!-- Page Header Ends                              -->
        <!-- Page Body Start-->
        <div class="page-body-wrapper">
            <!-- Page Sidebar Start-->
            <div style="background-color:#f5fafe !important;" class="sidebar-wrapper close_icon" data-layout="stroke-svg">
                <div>
                    <!-- Mobile drawer close button (only visible on small screens) -->
                    <button class="drawer-close-btn" type="button">
                        <span class="material-symbols-outlined">close</span>
                    </button>

                    <div class="logo-wrapper">
                        <p><b><a href="<?php echo e(route('dashboard')); ?>" style="text-decoration: none; color: inherit;">CHABRIN AGENCY</a></b></p>

                        <div class="toggle-sidebar">
                            <span class="material-symbols-outlined">
                                menu
                            </span>
                        </div>
                    </div>
                    <div class="logo-icon-wrapper">
                    </div>
                    <nav class="sidebar-main">
                        <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
                        <div id="sidebar-menu">
                            <ul class="sidebar-links" id="simple-bar">

                                <li class="sidebar-main-title">
                                    <div>
                                        <h6 class="lan-8">Applications</h6>
                                    </div>
                                </li>




                                <?php $__currentLoopData = $menus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $menu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="dropdown sidebar-list">
                                    <a class="sidebar-link sidebar-title">
                                        <?php if(!empty($menu['icon'])): ?>
                                        <span style="color:#3598db !important" class="material-symbols-outlined"><?php echo $menu['icon']; ?></span>
                                        <?php endif; ?>
                                        <span><?php echo e($menu['module_name']); ?></span>
                                    </a>
                                    <?php if(!empty($menu['submenus'])): ?>
                                    <ul class="sidebar-submenu">
                                        <?php $__currentLoopData = $menu['submenus']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submenu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <li><a style="vertical-align:middle !important;" href="<?php echo e($submenu->sub_module_url); ?>">
                                                <span style="vertical-align:middle !important;" class="material-symbols-outlined">
                                                    arrow_right
                                                </span>
                                                <?php echo e($submenu->sub_module_name); ?>

                                            </a>
                                        </li>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </ul>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                                
                                <li>
                                    <div class="super-modules-grid">
                                        <?php $__currentLoopData = $superModuleMenus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $smId => $sm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="super-module-item" data-super-module-id="<?php echo e($smId); ?>">
                                            <a class="sidebar-link">
                                                <span class="material-symbols-outlined sm-icon"><?php echo $sm['icon'] ?: 'folder'; ?></span>
                                                <span class="sm-label"><?php echo e($sm['name']); ?></span>
                                            </a>
                                        </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
                    </nav>
                </div>
            </div>
            <!-- Page Sidebar Ends-->

            
            <div class="super-module-flyout" id="superModuleFlyout">
                <div class="super-module-flyout-header">
                    <span class="material-symbols-outlined super-module-flyout-icon" id="flyoutIcon"></span>
                    <h6 id="flyoutTitle"></h6>
                </div>
                <div class="super-module-flyout-body" id="flyoutBody">
                    <?php $__currentLoopData = $superModuleMenus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $smId => $sm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="super-module-flyout-content" data-super-module-id="<?php echo e($smId); ?>" style="display:none;">
                        <?php $__currentLoopData = $sm['modules']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $moduleId => $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flyout-module-group">
                            <div class="flyout-module-title">
                                <?php if(!empty($mod['icon'])): ?>
                                <span class="material-symbols-outlined" style="color:#3598db; font-size:20px;"><?php echo $mod['icon']; ?></span>
                                <?php endif; ?>
                                <span><?php echo e($mod['module_name']); ?></span>
                                <?php if(!empty($mod['submenus'])): ?>
                                <span class="material-symbols-outlined flyout-expand-icon">expand_more</span>
                                <?php endif; ?>
                            </div>
                            <?php if(!empty($mod['submenus'])): ?>
                            <ul class="flyout-submenu-list">
                                <?php $__currentLoopData = $mod['submenus']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submenu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li>
                                    <a href="<?php echo e($submenu->sub_module_url); ?>">
                                        <span class="material-symbols-outlined" style="font-size:16px;">arrow_right</span>
                                        <?php echo e($submenu->sub_module_name); ?>

                                    </a>
                                </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <div class="super-module-flyout-overlay" id="superModuleFlyoutOverlay"></div>

            <!-- ######################## BODY START  ########################3 -->
            <?php echo $__env->yieldContent('content'); ?>
            <!-- ####################### BODY END ################### -->

            <!-- footer start-->
            <footer class="footer">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6 p-0 footer-copyright">
                            <p class="mb-0">Copyright <?php echo e(date('Y')); ?> © Chabrin Agencies Ltd.</p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <!-- latest jquery-->
    <script src="<?php echo e(asset('assets/js/jquery.min.js')); ?>"></script>

    <script>
        // Ensure all AJAX requests include the CSRF token header
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $(document).ready(function() {
            // Apply to all tables with id basic-1 (for all settings views)
            $('#basic-1').each(function() {
                if (!$.fn.dataTable.isDataTable(this)) {
                    $(this).DataTable({
                        paging: true,
                        info: true,
                        lengthChange: true,
                        lengthMenu: [
                            [10, 25, 50, 100, -1],
                            [10, 25, 50, 100, "All"]
                        ]
                    });
                }
            });
        });
    </script>


    <script src="<?php echo e(asset('assets/js/toastr.min.js')); ?>"></script>


    <!-- Bootstrap js-->
    <script src="<?php echo e(asset('assets/js/bootstrap/bootstrap.bundle.min.js')); ?>"></script>

    <!-- scrollbar js-->
    <script src="<?php echo e(asset('assets/js/scrollbar/simplebar.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/scrollbar/custom.js')); ?>"></script>

    <!-- Sidebar jquery-->
    <script src="<?php echo e(asset('assets/js/config.js')); ?>"></script>

    <!-- Plugins JS start-->
    <script src="<?php echo e(asset('assets/js/sidebar-menu.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/sidebar-pin.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/header-slick.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/datatables/datatables.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/select2/select2.min.js')); ?>"></script>


    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="<?php echo e(asset('assets/js/script.js')); ?>"></script>

    <script>
        // Handle hamburger menu collapse with responsive behavior
        (function() {
            function setupResponsiveSidebar() {
                var headerToggleButton = document.querySelector('.mobile-hamburger .toggle-sidebar');
                var sidebarToggleButton = document.querySelector('.sidebar-wrapper .logo-wrapper .toggle-sidebar');
                var drawerCloseButton = document.querySelector('.drawer-close-btn');
                var sidebarWrapper = document.querySelector('.sidebar-wrapper');
                var drawerOverlay = document.querySelector('.drawer-overlay');
                var sidebarMenus = document.querySelectorAll('ul.sidebar-submenu');

                if (!sidebarWrapper) {
                    console.log('Sidebar wrapper not found');
                    return;
                }

                console.log('Responsive sidebar setup successful');

                // Function to open drawer (mobile only)
                function openDrawer() {
                    if (window.innerWidth < 992) {
                        sidebarWrapper.classList.remove('close_icon');
                        drawerOverlay.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                }

                // Function to close drawer (mobile only)
                function closeDrawer() {
                    if (window.innerWidth < 992) {
                        sidebarWrapper.classList.add('close_icon');
                        drawerOverlay.classList.remove('active');
                        document.body.style.overflow = '';

                        // Collapse all submenus
                        sidebarMenus.forEach(function(submenu) {
                            submenu.style.display = 'none';
                        });
                    }
                }

                // Function to collapse menus on desktop
                function collapseMenusDesktop() {
                    if (window.innerWidth >= 992) {
                        // Collapse all open submenus
                        sidebarMenus.forEach(function(submenu) {
                            submenu.style.display = 'none';
                        });
                    }
                }

                // Header hamburger button (mobile only)
                if (headerToggleButton) {
                    headerToggleButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openDrawer();
                    });
                }

                // Sidebar toggle button (desktop only - collapses menus)
                if (sidebarToggleButton) {
                    sidebarToggleButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        collapseMenusDesktop();
                    });
                }

                // Drawer close button (mobile only)
                if (drawerCloseButton) {
                    drawerCloseButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeDrawer();
                    });
                }

                // Overlay click closes drawer (mobile only)
                if (drawerOverlay) {
                    drawerOverlay.addEventListener('click', function() {
                        closeDrawer();
                    });
                }

                // Handle window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 992) {
                        // On desktop, ensure drawer is visible and remove mobile states
                        sidebarWrapper.classList.remove('close_icon');
                        drawerOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    } else {
                        // On mobile, ensure drawer starts closed
                        if (!sidebarWrapper.classList.contains('close_icon')) {
                            closeDrawer();
                        }
                    }
                });

                // Initialize proper state on load
                if (window.innerWidth < 992) {
                    // Mobile: ensure drawer starts closed
                    if (!sidebarWrapper.classList.contains('close_icon')) {
                        sidebarWrapper.classList.add('close_icon');
                    }
                    drawerOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                } else {
                    // Desktop: remove close_icon to show sidebar normally
                    sidebarWrapper.classList.remove('close_icon');
                    var pageHeader = document.querySelector('.page-header');
                    if (pageHeader) {
                        pageHeader.classList.remove('close_icon');
                    }
                    drawerOverlay.classList.remove('active');
                }

                // Monitor and prevent close_icon on desktop only
                if (window.innerWidth >= 992) {
                    var desktopInterval = setInterval(function() {
                        if (sidebarWrapper.classList.contains('close_icon')) {
                            sidebarWrapper.classList.remove('close_icon');
                        }
                        var pageHeader = document.querySelector('.page-header');
                        if (pageHeader && pageHeader.classList.contains('close_icon')) {
                            pageHeader.classList.remove('close_icon');
                        }
                        var bgOverlay = document.querySelector('.bg-overlay');
                        if (bgOverlay) {
                            bgOverlay.remove();
                        }
                    }, 100);

                    // Clear interval on resize to mobile
                    window.addEventListener('resize', function() {
                        if (window.innerWidth < 992) {
                            clearInterval(desktopInterval);
                        }
                    });
                }

                // Remove bg-overlay on desktop
                document.addEventListener('click', function(e) {
                    if (window.innerWidth >= 992) {
                        if (e.target.classList.contains('bg-overlay')) {
                            e.stopPropagation();
                            e.preventDefault();
                            e.target.remove();
                            return false;
                        }
                    }
                }, true);
            }

            // Setup after DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupResponsiveSidebar);
            } else {
                setupResponsiveSidebar();
            }
        })();
    </script>

    <!-- Super Module Flyout Script -->
    <script>
        (function() {
            function setupSuperModuleFlyout() {
                var flyout = document.getElementById('superModuleFlyout');
                var flyoutOverlay = document.getElementById('superModuleFlyoutOverlay');
                var flyoutTitle = document.getElementById('flyoutTitle');
                var flyoutIcon = document.getElementById('flyoutIcon');
                var flyoutBody = document.getElementById('flyoutBody');
                var superModuleItems = document.querySelectorAll('.super-module-item');
                var activeSuperModuleId = null;
                var closeTimer = null;
                var CLOSE_DELAY = 200; // ms delay before closing

                if (!flyout || !superModuleItems.length) return;

                function cancelClose() {
                    if (closeTimer) {
                        clearTimeout(closeTimer);
                        closeTimer = null;
                    }
                }

                function scheduleClose() {
                    cancelClose();
                    closeTimer = setTimeout(function() {
                        closeFlyout();
                    }, CLOSE_DELAY);
                }

                function openFlyout(smId) {
                    cancelClose();

                    // Hide all flyout content sections
                    var allContent = flyoutBody.querySelectorAll('.super-module-flyout-content');
                    allContent.forEach(function(c) {
                        c.style.display = 'none';
                    });

                    // Show the selected one
                    var target = flyoutBody.querySelector('.super-module-flyout-content[data-super-module-id="' + smId + '"]');
                    if (target) target.style.display = 'block';

                    // Update header
                    var triggerItem = document.querySelector('.super-module-item[data-super-module-id="' + smId + '"]');
                    if (triggerItem) {
                        var nameSpan = triggerItem.querySelector('.sm-label');
                        var iconSpan = triggerItem.querySelector('.sm-icon');
                        flyoutTitle.textContent = nameSpan ? nameSpan.textContent.trim() : '';
                        flyoutIcon.textContent = iconSpan ? iconSpan.textContent.trim() : 'folder';
                    }

                    // Mark active
                    superModuleItems.forEach(function(el) {
                        el.classList.remove('active');
                    });
                    if (triggerItem) triggerItem.classList.add('active');

                    flyout.classList.add('open');
                    flyoutOverlay.classList.add('active');
                    activeSuperModuleId = smId;
                }

                function closeFlyout() {
                    cancelClose();
                    flyout.classList.remove('open');
                    flyoutOverlay.classList.remove('active');
                    superModuleItems.forEach(function(el) {
                        el.classList.remove('active');
                    });
                    activeSuperModuleId = null;
                }

                // Hover on super module items (desktop) + click/tap (mobile)
                superModuleItems.forEach(function(item) {
                    item.addEventListener('mouseenter', function() {
                        if (window.innerWidth >= 992) {
                            var smId = item.getAttribute('data-super-module-id');
                            openFlyout(smId);
                        }
                    });
                    item.addEventListener('mouseleave', function() {
                        if (window.innerWidth >= 992) {
                            scheduleClose();
                        }
                    });
                    // Click/tap for mobile (and fallback for desktop)
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var smId = item.getAttribute('data-super-module-id');
                        if (activeSuperModuleId === smId) {
                            closeFlyout();
                        } else {
                            openFlyout(smId);
                        }
                    });
                });

                // Keep flyout open while hovering over it
                flyout.addEventListener('mouseenter', function() {
                    cancelClose();
                });
                flyout.addEventListener('mouseleave', function() {
                    scheduleClose();
                });

                // Close on Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && activeSuperModuleId !== null) {
                        closeFlyout();
                    }
                });

                // Close flyout when clicking a submenu link inside
                flyout.querySelectorAll('.flyout-submenu-list a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        closeFlyout();
                    });
                });

                // Accordion: click module title to expand/collapse, only one open at a time
                flyout.querySelectorAll('.flyout-module-title').forEach(function(title) {
                    title.addEventListener('click', function(e) {
                        e.stopPropagation();
                        var group = title.closest('.flyout-module-group');
                        if (!group) return;
                        var isExpanded = group.classList.contains('expanded');

                        // Collapse all groups in the same flyout content section
                        var parentContent = group.closest('.super-module-flyout-content');
                        if (parentContent) {
                            parentContent.querySelectorAll('.flyout-module-group.expanded').forEach(function(g) {
                                g.classList.remove('expanded');
                            });
                        }

                        // Toggle the clicked one (if it was closed, open it)
                        if (!isExpanded) {
                            group.classList.add('expanded');
                        }
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupSuperModuleFlyout);
            } else {
                setupSuperModuleFlyout();
            }
        })();
    </script>

    <!-- Notification System Script -->
    <script>
        (function() {
            let unreadCount = 0;

            // Load notifications on page load
            function loadNotifications() {
                fetch('<?php echo e(route("notifications.index")); ?>?unread_only=0', {
                        headers: {
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateNotificationUI(data.notifications.data);
                            updateUnreadCount(data.unread_count);
                        }
                    })
                    .catch(error => console.error('Error loading notifications:', error));
            }

            // Update notification list in dropdown
            function updateNotificationUI(notifications) {
                const notificationList = document.getElementById('notificationList');

                if (!notifications || notifications.length === 0) {
                    notificationList.innerHTML = '<li class="text-center p-3"><span class="text-muted">No notifications</span></li>';
                    return;
                }

                notificationList.innerHTML = notifications.slice(0, 10).map(notification => {
                    const isUnread = !notification.read_at;
                    const createdAt = new Date(notification.created_at).toLocaleString();

                    return `
                        <li class="notification-item ${isUnread ? 'unread' : ''}" data-id="${notification.id}" style="background: ${isUnread ? '#f8f9fa' : 'transparent'};">
                            <div class="d-flex align-items-start p-2">
                                <div class="flex-grow-1" style="cursor: pointer;" onclick="handleNotificationClick(${notification.id}, '${notification.action_url}')">
                                    <h6 class="mb-1" style="font-size: 14px;">${notification.title}</h6>
                                    <p class="mb-1 text-muted" style="font-size: 12px;">${notification.message || ''}</p>
                                    <small class="text-muted" style="font-size: 11px;">${createdAt}</small>
                                </div>
                                <div class="notification-right">
                                    <button class="btn btn-link btn-sm p-0" onclick="deleteNotification(${notification.id}, event)" title="Delete">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                                    </button>
                                </div>
                            </div>
                        </li>
                    `;
                }).join('');
            }

            // Update unread count badge
            function updateUnreadCount(count) {
                unreadCount = count;
                const badge = document.getElementById('notificationBadge');
                const markAllBtn = document.getElementById('markAllReadBtn');

                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = 'inline-block';
                    markAllBtn.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                    markAllBtn.style.display = 'none';
                }
            }

            // Handle notification click
            window.handleNotificationClick = function(notificationId, actionUrl) {
                fetch(`/notifications/${notificationId}/read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateUnreadCount(data.unread_count);
                            loadNotifications();

                            if (actionUrl && actionUrl !== 'null') {
                                window.location.href = actionUrl;
                            }
                        }
                    })
                    .catch(error => console.error('Error marking notification as read:', error));
            };

            // Delete notification
            window.deleteNotification = function(notificationId, event) {
                event.stopPropagation();

                if (!confirm('Delete this notification?')) return;

                fetch(`/notifications/${notificationId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateUnreadCount(data.unread_count);
                            loadNotifications();
                            toastr.success('Notification deleted');
                        }
                    })
                    .catch(error => console.error('Error deleting notification:', error));
            };

            // Mark all as read
            document.getElementById('markAllReadBtn').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                fetch('<?php echo e(route("notifications.mark-all-read")); ?>', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateUnreadCount(0);
                            loadNotifications();
                            toastr.success('All notifications marked as read');
                        }
                    })
                    .catch(error => console.error('Error marking all as read:', error));
            });

            // Load notifications on page load
            loadNotifications();

            // Refresh notifications every 60 seconds
            setInterval(loadNotifications, 60000);
        })();
    </script>

    <?php echo $__env->yieldContent('scripts'); ?>

    <?php echo $__env->yieldContent('modals'); ?>
</body>

</html><?php /**PATH C:\CHIPS\Chab-CHIPS\resources\views/layouts/main.blade.php ENDPATH**/ ?>