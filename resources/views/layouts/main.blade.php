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

    <!-- Google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@200;300;400;600;700;800;900&family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/font-awesome.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/themify.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/animate.css') }}">
    <link href="{{ asset('assets/datatables/datatables.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/toastr.min.css') }}">

    <!-- Core CSS -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/bootstrap.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css') }}">
    <link id="color" rel="stylesheet" href="{{ asset('assets/css/color-1.css') }}" media="screen">

    <!-- Icon font (loaded after theme CSS so font-family wins) -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

    <!-- Layout overrides -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/chabrin-layout.css') }}">

    @yield('styles')

</head>

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
                        <p><b><a href="{{ route('dashboard') }}" style="text-decoration: none; color: inherit;">CHABRIN LTD.</a></b></p>

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




                                @foreach($menus as $menu)
                                <li class="dropdown sidebar-list">
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

                                {{-- Super Module Groups --}}
                                <li>
                                    <div class="super-modules-grid">
                                        @foreach($superModuleMenus as $smId => $sm)
                                        <div class="super-module-item" data-super-module-id="{{ $smId }}">
                                            <a class="sidebar-link">
                                                <span style="color:#3598db !important" class="material-symbols-outlined sm-icon">{!! $sm['icon'] ?: 'folder' !!}</span>
                                                <span class="sm-label">{{ $sm['name'] }}</span>
                                            </a>
                                        </div>
                                        @endforeach
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
                    </nav>
                </div>
            </div>
            <!-- Page Sidebar Ends-->

            {{-- Super Module Flyout Drawer --}}
            <div class="super-module-flyout" id="superModuleFlyout">
                <div class="super-module-flyout-header">
                    <button class="flyout-back-btn" id="flyoutBackBtn" type="button">
                        <span class="material-symbols-outlined">arrow_back</span>
                    </button>
                    <span class="material-symbols-outlined super-module-flyout-icon" id="flyoutIcon" style="font-variation-settings: 'FILL' 1;"></span>
                    <h6 id="flyoutTitle"></h6>
                </div>
                <div class="super-module-flyout-body" id="flyoutBody">
                    @foreach($superModuleMenus as $smId => $sm)
                    <div class="super-module-flyout-content" data-super-module-id="{{ $smId }}" style="display:none;">
                        @foreach($sm['modules'] as $moduleId => $mod)
                        <div class="flyout-module-group">
                            <div class="flyout-module-title">
                                @if(!empty($mod['icon']))
                                <span class="material-symbols-outlined" style="color:#3598db; font-size:20px;">{!! $mod['icon'] !!}</span>
                                @endif
                                <span>{{ $mod['module_name'] }}</span>
                                @if(!empty($mod['submenus']))
                                <span class="material-symbols-outlined flyout-expand-icon">expand_more</span>
                                @endif
                            </div>
                            @if(!empty($mod['submenus']))
                            <ul class="flyout-submenu-list">
                                @foreach($mod['submenus'] as $submenu)
                                <li>
                                    <a href="{{ $submenu->sub_module_url }}">
                                        <span class="material-symbols-outlined" style="font-size:16px;">arrow_right</span>
                                        {{ $submenu->sub_module_name }}
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="super-module-flyout-overlay" id="superModuleFlyoutOverlay"></div>

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
    <!-- Core JS -->
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

    <script src="{{ asset('assets/js/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/toastr.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/js/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/js/scrollbar/simplebar.js') }}"></script>
    <script src="{{ asset('assets/js/scrollbar/custom.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('assets/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('assets/js/sidebar-pin.js') }}"></script>
    <script src="{{ asset('assets/js/header-slick.js') }}"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>

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

                // Back button (mobile) — close flyout
                var flyoutBackBtn = document.getElementById('flyoutBackBtn');
                if (flyoutBackBtn) {
                    flyoutBackBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeFlyout();
                    });
                }

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