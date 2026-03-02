<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="description" content="Property Management.">
    <meta name="keywords" content="Property administration, Property management system, Chabrin Agencies Ltd Property Manager, retirement fund software, Property fund administration, Property solution, member management, contributions, benefits">

    <meta name="author" content="pixelstrap">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon">
    <title>Property Manager</title>

    <!-- Google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">

    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@200;300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i,900&amp;display=swap" rel="stylesheet">

    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/font-awesome.css') }}">

    <!-- Themify icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/themify.css') }}">

    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/animate.css') }}">


    <link href="{{ asset('assets/datatables/datatables.min.css') }}" rel="stylesheet">

    <!-- Plugins css Ends-->
    <!-- Bootstrap css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/bootstrap.css') }}">
    <!-- App css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css') }}">
    <link id="color" rel="stylesheet" href="{{ asset('assets/css/color-1.css') }}" media="screen">
    <!-- Responsive css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/select2/select2.min.css') }}">

    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/toastr.min.css') }}">

    @yield('styles')

</head>
<style>
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

    /* Force toast text color to white to avoid theme overrides (Bootstrap/Toastr) */
    .toast,
    .toast *,
    .toast .toast-body,
    .toast-message,
    .toast .toast-message {
        color: #fff !important;
    }


    /* Make top bar thinner */
    .page-header {
        height: 40px !important;
        min-height: 40px !important;
        padding: 0 !important;
        /* border-bottom: 2px solid #3598db !important; */
        background-color: #fff !important;
    }

    .header-wrapper {
        height: 40px !important;
        align-items: center !important;
        padding: 0 15px !important;
    }

    .header-logo-wrapper {
        height: 40px !important;
        display: flex;
        align-items: center;
    }

    .nav-right {
        margin-left: auto !important;
        height: 40px !important;
        display: flex;
        align-items: center;
    }

    .nav-menus {
        height: 40px !important;
        display: flex;
        align-items: center;
        margin: 0 !important;
        gap: 15px;
    }

    .nav-menus li {
        display: flex;
        align-items: center;
        height: 40px;
    }

    /* Dark green icon styling */
    .nav-menus .material-symbols-outlined {
        color: #3598db !important;
        font-size: 20px !important;
    }

    .nav-menus .message .material-symbols-outlined {
        color: #3598db !important;
    }

    /* Improved profile layout */
    .profile-nav .profile-media {
        height: 40px !important;
        padding: 0 !important;
        gap: 8px !important;
    }

    .profile-nav .profile-avatar {
        width: 28px !important;
        height: 28px !important;
        font-size: 28px !important;
        color: #3598db !important;
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
        color: #3598db !important;
        margin: 0 !important;
        white-space: nowrap !important;
    }

    .profile-nav .user .material-symbols-outlined {
        color: #3598db !important;
        font-size: 16px !important;
    }

    /* Profile dropdown styling */
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
        color: #3598db !important;
    }

    .profile-dropdown li a span:last-child {
        font-size: 14px !important;
        font-weight: 500 !important;
    }

    /* Notification styling */
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

    /* Hide any second layer/page-title that might be causing double header */
    .page-wrapper .page-body-wrapper .page-title {
        display: none !important;
    }

    /* Ensure proper page layout without breaking cards */
    .page-wrapper .page-body-wrapper .page-body {
        margin-top: 20px !important;
        padding: 15px !important;
    }

    /* Hide only breadcrumb in header area, not in content */
    .page-header+.breadcrumb-wrapper,
    .page-header+.breadcrumb,
    .page-header+.page-breadcrumb {
        display: none !important;
    }

    /* Ensure cards display properly */
    .page-body .card {
        margin-bottom: 20px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    }

    /* Fix container spacing */
    .container-fluid {
        padding: 15px !important;
    }

    /* Make footer thinner */
    .footer {
        height: 40px !important;
        min-height: 40px !important;
        padding: 8px 0 !important;
        background-color: #f8f9fa !important;
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

    /* Sidebar title link styling */
    .logo-wrapper p a {
        color: inherit !important;
        text-decoration: none !important;
        transition: color 0.2s ease !important;
    }

    .logo-wrapper p a:hover {
        color: #3598db !important;
        text-decoration: none !important;
    }

    /* ============================================ */
    /* SIDEBAR AND HAMBURGER MENU RESPONSIVE FIXES */
    /* ============================================ */

    /* Desktop styles - hide mobile elements only */
    @media (min-width: 992px) {

        .header-logo-wrapper .toggle-sidebar,
        .sidebar-wrapper .logo-wrapper .toggle-sidebar,
        .mobile-hamburger {
            display: none !important;
        }

        .drawer-close-btn {
            display: none !important;
        }

        .bg-overlay,
        .drawer-overlay {
            display: none !important;
        }
    }

    /* Mobile drawer styles */
    @media (max-width: 991px) {

        /* Hamburger button as first item in nav-menus */
        .mobile-hamburger {
            display: flex !important;
            order: -1 !important;
            padding: 0 !important;
            margin-right: auto !important;
            position: absolute !important;
            left: 10px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
        }

        .mobile-hamburger .toggle-sidebar {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 50px !important;
            height: 50px !important;
            background-color: transparent !important;
            border: none !important;
            cursor: pointer !important;
            padding: 8px !important;
            border-radius: 8px !important;
            transition: background-color 0.2s ease !important;
        }

        .mobile-hamburger .toggle-sidebar:hover {
            background-color: rgba(53, 152, 219, 0.1) !important;
        }

        /* Hamburger icon (three lines) */
        .mobile-hamburger .toggle-sidebar .material-symbols-outlined {
            font-size: 32px !important;
            color: #3598db !important;
            font-weight: 500 !important;
        }

        /* Adjust header wrapper for hamburger positioning */
        .header-wrapper {
            position: relative !important;
        }

        /* Push logo and other items to make space for hamburger */
        .header-logo-wrapper {
            margin-left: 60px !important;
        }

        /* Ensure nav-menus uses flexbox */
        .nav-menus {
            display: flex !important;
            align-items: center !important;
        }

        /* Hide the toggle button inside sidebar on mobile */
        .sidebar-wrapper .logo-wrapper .toggle-sidebar {
            display: none !important;
        }

        /* Sidebar as drawer on mobile */
        .sidebar-wrapper {
            position: fixed !important;
            top: 0 !important;
            left: -280px !important;
            height: 100vh !important;
            width: 280px !important;
            z-index: 9999 !important;
            transition: left 0.3s ease !important;
            overflow-y: auto !important;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1) !important;
        }

        /* When drawer is open */
        .sidebar-wrapper:not(.close_icon) {
            left: 0 !important;
        }

        /* Overlay for drawer */
        .drawer-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
            z-index: 9998 !important;
            display: none !important;
        }

        .drawer-overlay.active {
            display: block !important;
        }

        /* Close button in drawer */
        .sidebar-wrapper .drawer-close-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: absolute !important;
            top: 15px !important;
            right: 15px !important;
            width: 36px !important;
            height: 36px !important;
            background-color: #3598db !important;
            color: white !important;
            border-radius: 50% !important;
            cursor: pointer !important;
            z-index: 10 !important;
            border: none !important;
            transition: all 0.3s ease !important;
        }

        .sidebar-wrapper .drawer-close-btn:hover {
            background-color: #2a7ab8 !important;
            transform: rotate(90deg) !important;
        }

        .sidebar-wrapper .drawer-close-btn .material-symbols-outlined {
            font-size: 20px !important;
            color: white !important;
        }

        /* Adjust page content on mobile */
        .page-wrapper.compact-wrapper .page-body-wrapper {
            margin-left: 0 !important;
        }

        /* Sidebar logo adjustment for close button space */
        .sidebar-wrapper .logo-wrapper {
            padding-right: 50px !important;
            position: relative !important;
        }
    }



    /* Kill the vertical line on submenu */
    .sidebar-submenu {
        border: none !important;
        border-left: none !important;
        border-inline-start: none !important;
    }

    /* Kill pseudo-elements if framework draws lines */
    .sidebar-submenu::before,
    .sidebar-submenu li::before,
    .sidebar-submenu li::after {
        display: none !important;
        content: none !important;
    }

    /* CSS for all tables, reduce font and padding to make rows condensed */
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

    /* Reduce DataTables pagination and info text size */
    .dataTables_info,
    .dataTables_length,
    .dataTables_filter {
        font-size: 12px !important;
    }

    .dataTables_paginate {
        font-size: 12px !important;
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
                    <div class="logo-wrapper"><a href="{{ url('/') }}"> <img class="img-fluid for-light" src="{{ asset('assets/images/logo/logo.png') }}" alt=""><img class="img-fluid for-dark" src="{{ asset('assets/images/logo/logo_dark.png') }}" alt=""></a></div>
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
                                    <span>{{ optional(Auth::user())->username }}</span>
                                    <span class="material-symbols-outlined">arrow_drop_down_circle</span>
                                </div>
                            </div>
                            <ul class="profile-dropdown onhover-show-div">
                                <li><a href="{{ route('user.profile') }}"><span class="material-symbols-outlined">person</span><span>Profile</span></a></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                                        @csrf
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
                        <p><b><a href="{{ route('dashboard') }}" style="text-decoration: none; color: inherit;">CHABRIN AGENCY</a></b></p>

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

                                <li>
                                    <br>
                                    <form style=" margin-bottom:5px !important;" class="navbar-search">
                                        <input placeholder="--Search Menu--" id="sidebarMenuSearch" autofocus style="border:1px solid white !important; border-radius:5px !important; height:30px !important; width: 100% !important;" type="text" name="search" placeholder="">
                                        <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
                                    </form>

                                </li>


                                @foreach($menus as $menu)
                                <li class="dropdown">
                                    <a class="sidebar-link sidebar-title">
                                        @if(!empty($menu['icon']))
                                        <span style="color:#3598db !important" class="material-symbols-outlined">{!! $menu['icon'] !!}</span>
                                        @endif
                                        <span>{{ $menu['module_name'] }}</span>
                                    </a>
                                    @if(!empty($menu['submenus']))
                                    <ul class="sidebar-submenu">
                                        @foreach($menu['submenus'] as $submenu)
                                        <li><a style="vertical-align:middle !important;" href="{{ $submenu->sub_module_url }}">
                                                <span style="vertical-align:middle !important;" class="material-symbols-outlined">
                                                    arrow_right
                                                </span>
                                                {{ $submenu->sub_module_name }}
                                            </a>
                                        </li>
                                        @endforeach
                                    </ul>
                                    @endif

                                </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
                    </nav>
                </div>
            </div>
            <!-- Page Sidebar Ends-->

            <!-- ######################## BODY START  ########################3 -->
            @yield('content')
            <!-- ####################### BODY END ################### -->

            <!-- footer start-->
            <footer class="footer">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6 p-0 footer-copyright">
                            <p class="mb-0">Copyright {{ date('Y') }} © Chabrin Agencies Ltd.</p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <!-- latest jquery-->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>

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


    <script src="{{ asset('assets/js/toastr.min.js') }}"></script>


    <!-- Bootstrap js-->
    <script src="{{ asset('assets/js/bootstrap/bootstrap.bundle.min.js') }}"></script>

    <!-- scrollbar js-->
    <script src="{{ asset('assets/js/scrollbar/simplebar.js') }}"></script>
    <script src="{{ asset('assets/js/scrollbar/custom.js') }}"></script>

    <!-- Sidebar jquery-->
    <script src="{{ asset('assets/js/config.js') }}"></script>

    <!-- Plugins JS start-->
    <script src="{{ asset('assets/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('assets/js/sidebar-pin.js') }}"></script>
    <script src="{{ asset('assets/js/header-slick.js') }}"></script>
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/js/select2/select2.min.js') }}"></script>


    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="{{ asset('assets/js/script.js') }}"></script>

    <script>
        (function() {
            function bindSidebarSearch() {
                var input = document.getElementById('sidebarMenuSearch');
                if (!input) {
                    console.log('sidebarMenuSearch input not found');
                    return;
                }

                console.log('Sidebar search binding successful');

                function doFilter() {
                    var searchTerm = (input.value || '').toLowerCase().trim();
                    console.log('Filtering with term:', searchTerm);

                    // Target only the actual menu items (li.sidebar-list), not the title or search form
                    var allMenuItems = document.querySelectorAll('ul.sidebar-links li.sidebar-list');
                    console.log('Found menu items:', allMenuItems.length);

                    allMenuItems.forEach(function(menuItem) {
                        var subMenu = menuItem.querySelector('ul.sidebar-submenu');
                        var subLinks = subMenu ? subMenu.querySelectorAll('li') : [];
                        var childMatched = false;

                        // Check submenu items
                        subLinks.forEach(function(subItem) {
                            var text = (subItem.textContent || '').toLowerCase().trim();
                            var matched = text.indexOf(searchTerm) !== -1;
                            subItem.style.display = (matched || searchTerm === '') ? '' : 'none';
                            if (matched) childMatched = true;
                        });

                        // Check parent menu title - look for the span containing the module name
                        var titleEl = menuItem.querySelector('a.sidebar-link span:last-child');
                        var titleText = titleEl ? (titleEl.textContent || '').toLowerCase().trim() : '';
                        var parentMatched = titleText.indexOf(searchTerm) !== -1;

                        if (searchTerm === '') {
                            // When search is empty, show all menu items but keep submenus collapsed
                            menuItem.style.display = '';
                            if (subMenu) {
                                subMenu.style.display = 'none'; // Collapse all submenus
                            }
                        } else {
                            // Show/hide based on matches
                            if (parentMatched || childMatched) {
                                menuItem.style.display = '';
                                if (subMenu && childMatched) {
                                    // Only expand submenu if there's a child match
                                    subMenu.style.display = 'block';
                                } else if (subMenu && parentMatched && !childMatched) {
                                    // If only parent matches but no children, keep submenu collapsed
                                    subMenu.style.display = 'none';
                                }
                            } else {
                                // No matches found, hide the entire menu item
                                menuItem.style.display = 'none';
                                if (subMenu) {
                                    subMenu.style.display = 'none';
                                }
                            }
                        }
                    });
                }

                // Bind events
                input.removeEventListener('input', doFilter);
                input.removeEventListener('keyup', doFilter);
                input.addEventListener('input', doFilter);
                input.addEventListener('keyup', doFilter);

                // Test initial binding
                console.log('Event listeners attached to sidebarMenuSearch');
            }

            // Ensure DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('DOMContentLoaded - binding sidebar search');
                    bindSidebarSearch();
                });
            } else {
                console.log('DOM already ready - binding sidebar search immediately');
                bindSidebarSearch();
            }
        })();

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

                        // Clear search
                        var searchInput = document.getElementById('sidebarMenuSearch');
                        if (searchInput) {
                            searchInput.value = '';
                            var event = new Event('input', {
                                bubbles: true
                            });
                            searchInput.dispatchEvent(event);
                        }
                    }
                }

                // Function to collapse menus on desktop
                function collapseMenusDesktop() {
                    if (window.innerWidth >= 992) {
                        // Collapse all open submenus
                        sidebarMenus.forEach(function(submenu) {
                            submenu.style.display = 'none';
                        });

                        // Clear the search input to reset the filter
                        var searchInput = document.getElementById('sidebarMenuSearch');
                        if (searchInput) {
                            searchInput.value = '';
                            var event = new Event('input', {
                                bubbles: true
                            });
                            searchInput.dispatchEvent(event);
                        }
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

    <!-- Notification System Script -->
    <script>
        (function() {
            let unreadCount = 0;

            // Load notifications on page load
            function loadNotifications() {
                fetch('{{ route("notifications.index") }}?unread_only=0', {
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
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
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
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
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
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
                
                fetch('{{ route("notifications.mark-all-read") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
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

    @yield('scripts')

    @yield('modals')
</body>

</html>