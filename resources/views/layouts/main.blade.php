<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="description" content="Property Management.">
    <meta name="keywords" content="Property administration, Property management system, Chabrin Agencies Ltd Property Manager, retirement fund software, Property fund administration, Property solution, member management, contributions, benefits">

    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32x32.png') }}?v=2">
   <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/images/favicon-16x16.png') }}?v=2">
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

    <!-- Core CSS -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/bootstrap.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css') }}">
    <link id="color" rel="stylesheet" href="{{ asset('assets/css/color-1.css') }}" media="screen">

    <!-- Icon font (loaded after theme CSS so font-family wins) -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

    <!-- Layout overrides -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/chabrin-layout.css') }}?v={{ filemtime(public_path('assets/css/chabrin-layout.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/focused-navigation.css') }}?v={{ filemtime(public_path('assets/css/focused-navigation.css')) }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/notification-bell.css') }}?v={{ filemtime(public_path('assets/css/notification-bell.css')) }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/handover-employee-picker.css') }}?v={{ filemtime(public_path('assets/css/handover-employee-picker.css')) }}">

    <link rel="stylesheet" href="{{ asset('assets/css/header-refinements.css') }}?v={{ filemtime(public_path('assets/css/header-refinements.css')) }}">

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
       <div class="page-wrapper compact-wrapper" id="pageWrapper">

        <!-- Page Header Start-->

        <div class="page-header">

            <div class="header-wrapper row m-0">

                <div class="header-logo-wrapper col-auto p-0">

                    <div class="logo-wrapper"><a href="{{ url('/') }}"> <img class="img-fluid for-light" src="{{ asset('assets/images/logo/logo_light.png') }}" alt=""><img class="img-fluid for-dark" src="{{ asset('assets/images/logo/logo_dark.png') }}" alt=""></a></div>

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
                        
                       <li class="header-search-item">
                            <div class="header-search-wrapper" id="header_search_box">
                                <button type="button" class="header-search-submit" id="global_search_submit" aria-label="Search records"><span class="material-symbols-outlined search-icon" aria-hidden="true">search</span></button>
                                <input 
                                    type="text" 
                                    id="global_search_input" 
                                    class="header-search-control" 
                                    placeholder="Search records..." aria-label="Search clients, staff, properties and invoices"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                
                            </div>
                        </li>


                        @include('layouts.contract-notifications')
                        <li class="profile-nav dropdown pe-0 py-0">
                        <button type="button" class="d-flex align-items-center profile-trigger-btn" id="account-menu-trigger" data-bs-toggle="dropdown" aria-expanded="false" aria-controls="account-menu" aria-label="Open account menu">
                            {{-- Circular Avatar with Initials --}}
                            @php
                                $staff = Auth::user()?->linkedStaff();
                                $avatar = $staff && !empty($staff->photo) ? asset('storage/' . $staff->photo) : null;
                                $userInitial = strtoupper(substr(Auth::user()?->name ?? 'U', 0, 1));
                            @endphp
                         {{-- Top Bar Avatar Circle --}}
                            <div style="width: 36px; height: 36px; min-width: 36px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #0f2744; color: #ffffff; font-weight: 700; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            @if($avatar)
                                <img src="{{ $avatar }}" alt="{{ Auth::user()?->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <span>{{ $userInitial }}</span>
                            @endif
                        </div>
                            
                            <div class="profile-meta d-none d-md-block text-start">
                                <span class="profile-name">{{ Auth::user()?->name }}</span>
                                <span class="profile-role">{{ Auth::user()?->roles->map(fn ($role) => $role->display_name ?: ucwords(str_replace('_', ' ', $role->name)))->implode(', ') ?: 'Signed in' }}</span>
                            </div>
                            <span class="material-symbols-outlined profile-caret" aria-hidden="true">expand_more</span>
                        </button>

                        {{-- Upgraded Dropdown Card --}}
                        <div class="profile-dropdown dropdown-menu dropdown-menu-end modern-profile-menu" id="account-menu" aria-labelledby="account-menu-trigger">
                            {{-- Header inside menu with full details --}}
                            <div class="profile-menu-header">
                                <div class="header-avatar-circle lg" style="overflow: hidden; padding: 0;">
                                    @if($avatar)
                                        <img src="{{ $avatar }}" alt="{{ Auth::user()?->name }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                    @else
                                        {{ $userInitial }}
                                    @endif
                                </div>
                                <div class="profile-menu-info">
                                    <h6>{{ Auth::user()?->name }}</h6>
                                    <p>{{ Auth::user()?->email ?? 'Active Session' }}</p>
                                </div>
                            </div>

                                <div class="profile-menu-divider"></div>

                                <ul class="profile-menu-links">
                                    <li>
                                        <a href="{{ route('user.profile') }}" class="menu-item">
                                            <span class="material-symbols-outlined">account_circle</span>
                                            <span>My Account</span>
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}" id="logout-form" class="m-0">
                                            @csrf
                                            <button type="submit" class="menu-item logout-btn">
                                                <span class="material-symbols-outlined">logout</span>
                                                <span>Log Out</span>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
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
        <!-- Page Header Ends-->
        <!-- Page Body Start-->
        <div class="page-body-wrapper">
            <!-- Page Sidebar Start-->
            <div style="background-color:var(--sidebar-bg) !important;" class="sidebar-wrapper close_icon" data-layout="stroke-svg">
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
                    @include('layouts.focused-navigation')
                </div>
            </div>
            <!-- Page Sidebar Ends-->

            <!-- ######################## BODY START  ########################3 -->
            <div id="notification-toast-container" aria-live="polite" aria-atomic="false" style="position:fixed;top:85px;right:20px;z-index:10510;width:min(380px,90vw);"></div>
            <div id="global-alert-container" style="position:fixed;top:50px;right:20px;z-index:10500;max-width:400px;"></div>

            {{-- jQuery + the toastr-compatible alert shim load here, before page content, so every
                 page's inline scripts (however they're placed in the child view) can rely on $/jQuery
                 and toastr already being defined — avoids each view re-including its own copy of jQuery. --}}
            <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
            <script>
                function showAlert(message, type) {
                    type = type || 'success';
                    var alertClass = (type === 'error') ? 'danger' : type;
                    var labels = {
                        success: 'Success!',
                        danger: 'Error!',
                        warning: 'Warning!',
                        info: 'Info!'
                    };
                    var label = labels[alertClass] || '';
                    var $alert = $('<div class="alert alert-' + alertClass + ' alert-dismissible fade show" role="alert" style="font-size:13px;padding:8px 14px;">' +
                        '<strong>' + label + '</strong> ' + message +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" style="padding:6px 10px;"></button></div>');
                    $('#global-alert-container').append($alert);
                    setTimeout(function() {
                        $alert.alert('close');
                    }, 5000);
                }
                var toastr = {
                    success: function(msg) {
                        showAlert(msg, 'success');
                    },
                    error: function(msg) {
                        showAlert(msg, 'danger');
                    },
                    warning: function(msg) {
                        showAlert(msg, 'warning');
                    },
                    info: function(msg) {
                        showAlert(msg, 'info');
                    }
                };
            </script>

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
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/js/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/js/handover-employee-picker.js') }}?v={{ filemtime(public_path('assets/js/handover-employee-picker.js')) }}"></script>
    <script src="{{ asset('assets/js/scrollbar/simplebar.js') }}"></script>
    <script src="{{ asset('assets/js/scrollbar/custom.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('assets/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('assets/js/sidebar-pin.js') }}"></script>
    <script src="{{ asset('assets/js/header-slick.js') }}"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>

    {{-- Auto-surface session flash messages app-wide, via the toastr shim loaded earlier.
         Individual pages no longer need their own session('success')/session('error')
         alert block — this is the single place that renders them now. --}}
    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { toastr.success(@json(session('success'))); });</script>
    @endif
    @if(session('error'))
    <script>document.addEventListener('DOMContentLoaded', function() { toastr.error(@json(session('error'))); });</script>
    @endif

    <script>
        // Global search box: Enter key navigates to the search results page
        (function () {
        var searchInput = document.getElementById('global_search_input');
        var searchButton = document.getElementById('global_search_submit');
        if (!searchInput) return;

        function submitSearch() {
            var term = searchInput.value.trim();
            if (term.length < 2) {
                searchInput.focus();
                return;
            }
            window.location.href = '{{ route('search') }}?q=' + encodeURIComponent(term);
        }

            // Submit on Enter
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitSearch();
                }
            });

            // Click icon to search
            if (searchButton) {
                searchButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    submitSearch();
                });
            }

            // Global 'Ctrl + K' or 'Cmd + K' to quick-focus the search bar
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
        })();
    </script>

    <script>
        // Handle hamburger menu collapse with responsive behavior
        (function() {
            function setupResponsiveSidebar() {
                var headerToggleButton = document.querySelector('.mobile-hamburger .toggle-sidebar');
                var sidebarWrapper = document.querySelector('.sidebar-wrapper');

                if (!sidebarWrapper) return;

                // Mobile: hamburger toggles sidebar open/closed (simple slide)
                if (headerToggleButton) {
                    headerToggleButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        if (window.innerWidth < 992) {
                            sidebarWrapper.classList.toggle('close_icon');
                            // Remove theme's bg-overlay if any
                            document.querySelectorAll('.bg-overlay').forEach(function(el) {
                                el.remove();
                            });
                        }
                    }, true);
                }

                // Close sidebar when clicking outside it on mobile
                document.addEventListener('click', function(e) {
                    if (window.innerWidth < 992 && !sidebarWrapper.classList.contains('close_icon')) {
                        // If click is outside sidebar and outside hamburger and outside flyout
                        if (!e.target.closest('.sidebar-wrapper') &&
                            !e.target.closest('.mobile-hamburger') &&
                            !e.target.closest('.super-module-flyout')) {
                            sidebarWrapper.classList.add('close_icon');
                        }
                    }
                });

                // Handle window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 992) {
                        sidebarWrapper.classList.remove('close_icon');
                    } else {
                        if (!sidebarWrapper.classList.contains('close_icon')) {
                            sidebarWrapper.classList.add('close_icon');
                        }
                    }
                });

                // Initialize state
                if (window.innerWidth < 992) {
                    sidebarWrapper.classList.add('close_icon');
                } else {
                    sidebarWrapper.classList.remove('close_icon');
                    var pageHeader = document.querySelector('.page-header');
                    if (pageHeader) pageHeader.classList.remove('close_icon');
                }

                // Desktop: keep sidebar open, remove bg-overlay
                if (window.innerWidth >= 992) {
                    var desktopInterval = setInterval(function() {
                        if (sidebarWrapper.classList.contains('close_icon')) {
                            sidebarWrapper.classList.remove('close_icon');
                        }
                        var ph = document.querySelector('.page-header');
                        if (ph && ph.classList.contains('close_icon')) {
                            ph.classList.remove('close_icon');
                        }
                        var bg = document.querySelector('.bg-overlay');
                        if (bg) bg.remove();
                    }, 100);

                    window.addEventListener('resize', function() {
                        if (window.innerWidth < 992) clearInterval(desktopInterval);
                    });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupResponsiveSidebar);
            } else {
                setupResponsiveSidebar();
            }
        })();
    </script>

    <script src="{{ asset('assets/js/focused-navigation.js') }}?v={{ filemtime(public_path('assets/js/focused-navigation.js')) }}"></script>

    @yield('scripts')

    @if (($forcePasswordChange ?? false) && !request()->routeIs('user.profile'))
        @include('partials.forced-password-modal')
    @endif

    @yield('modals')
</body>

</html>
