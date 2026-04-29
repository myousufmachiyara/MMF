<aside id="sidebar-left" class="sidebar-left">
  <div class="sidebar-header">
    <div class="sidebar-title" style="display: flex; justify-content: space-between;">
      <a href="{{ route('dashboard') }}" class="logo">
        <img src="/assets/img/billtrix-logo-1.png" class="sidebar-logo" alt="BillTrix Logo" />
      </a>
      <div class="d-md-none toggle-sidebar-left col-1" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
        <i class="fas fa-times" aria-label="Toggle sidebar"></i>
      </div>
    </div>
    <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
      <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
    </div>
  </div>

  <div class="nano">
    <div class="nano-content">
      <nav id="menu" class="nav-main" role="navigation">
        <ul class="nav nav-main">

          <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}">
              <i class="fa fa-home" aria-hidden="true"></i>
              <span>Dashboard</span>
            </a>
          </li>

          {{-- User Management --}}
          @if(auth()->user()->can('user_roles.index') || auth()->user()->can('users.index'))
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-user-shield"></i> <span>Users</span></a>
            <ul class="nav nav-children">
              @can('user_roles.index')
              <li><a class="nav-link" href="{{ route('roles.index') }}">Roles & Permissions</a></li>
              @endcan
              @can('users.index')
              <li><a class="nav-link" href="{{ route('users.index') }}">All Users</a></li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Accounts --}}
          @if(auth()->user()->can('coa.index') || auth()->user()->can('shoa.index'))
          <li class="nav-parent">
            <a class="nav-link" href="#"><i class="fa fa-book"></i> <span>Accounts</span></a>
            <ul class="nav nav-children">
              @can('coa.index')
              <li><a class="nav-link" href="{{ route('coa.index') }}">Chart of Accounts</a></li>
              @endcan
              @can('shoa.index')
              <li><a class="nav-link" href="{{ route('shoa.index') }}">Sub Heads</a></li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Products --}}
          @if(auth()->user()->can('product_categories.index') || auth()->user()->can('attributes.index') || auth()->user()->can('products.index'))
            <li class="nav-parent">
                <a class="nav-link" href="#"><i class="fa fa-layer-group"></i> <span>Products</span></a>
                <ul class="nav nav-children">
                    @can('product_categories.index')
                        <li><a class="nav-link" href="{{ route('product_categories.index') }}">Categories</a></li>
                    @endcan
                    @can('product_subcategories.index')
                        <li><a class="nav-link" href="{{ route('product_subcategories.index') }}">Sub Categories</a></li>
                    @endcan
                    @can('attributes.index')
                        <li><a class="nav-link" href="{{ route('attributes.index') }}">Attributes</a></li>
                    @endcan
                    @can('products.index')
                        <li><a class="nav-link" href="{{ route('products.index') }}">All Products</a></li>
                    @endcan
                </ul>
            </li>
          @endif

          {{-- Purchase --}}
          @if(
            auth()->user()->can('purchase_orders.index')   ||
            auth()->user()->can('purchase_invoices.index') ||
            auth()->user()->can('purchase_return.index')
          )
          <li class="nav-parent {{ request()->routeIs('purchase_orders.*') || request()->routeIs('purchase_invoices.*') || request()->routeIs('purchase_return.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-shopping-cart"></i> <span>Purchase</span></a>
            <ul class="nav nav-children">
              @can('purchase_orders.index')
                <li class="{{ request()->routeIs('purchase_orders.*') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('purchase_orders.index') }}">Orders</a>
                </li>
              @endcan
              @can('purchase_invoices.index')
                <li class="{{ request()->routeIs('purchase_invoices.*') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('purchase_invoices.index') }}">Invoices</a>
                </li>
              @endcan
              @can('purchase_return.index')
                <li class="{{ request()->routeIs('purchase_return.*') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('purchase_return.index') }}">Returns</a>
                </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Sale --}}
          @if(auth()->user()->can('sale_invoices.index') || auth()->user()->can('sale_return.index'))
          <li class="nav-parent {{ request()->routeIs('sale_invoices.*') || request()->routeIs('sale_return.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-cash-register"></i> <span>Sale</span></a>
            <ul class="nav nav-children">
              @can('sale_invoices.index')
              <li class="{{ request()->routeIs('sale_invoices.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('sale_invoices.index') }}">Invoices</a>
              </li>
              @endcan
              @can('sale_return.index')
              <li class="{{ request()->routeIs('sale_return.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('sale_return.index') }}">Returns</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Vouchers --}}
          @if(auth()->user()->can('vouchers.index'))
            <li class="nav-parent {{ request()->routeIs('vouchers.*') ? 'nav-expanded nav-active' : '' }}">
                <a class="nav-link" href="#">
                    <i class="fa fa-money-check"></i>
                    <span>Vouchers</span>
                </a>
                <ul class="nav nav-children">
                    <li class="{{ request()->is('vouchers/journal*') ? 'active' : '' }}">
                      <a class="nav-link" href="{{ route('vouchers.index', 'journal') }}">Journal Vouchers</a>
                    </li>
                    <li class="{{ request()->is('vouchers/payment*') ? 'active' : '' }}">
                      <a class="nav-link" href="{{ route('vouchers.index', 'payment') }}">Payment Vouchers</a>
                    </li>
                    <li class="{{ request()->is('vouchers/receipt*') ? 'active' : '' }}">
                      <a class="nav-link" href="{{ route('vouchers.index', 'receipt') }}">Receipt Vouchers</a>
                    </li>
                </ul>
            </li>
          @endif

          {{-- Reports --}}
          @if(
            auth()->user()->can('reports.inventory') ||
            auth()->user()->can('reports.purchase')  ||
            auth()->user()->can('reports.sales')     ||
            auth()->user()->can('reports.accounts')
          )
          <li class="nav-parent {{ request()->routeIs('reports.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#">
              <i class="fa fa-chart-bar"></i>
              <span>Reports</span>
            </a>
            <ul class="nav nav-children">
              @can('reports.inventory')
                <li class="{{ request()->routeIs('reports.inventory') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('reports.inventory') }}">Inventory</a>
                </li>
              @endcan
              @can('reports.purchase')
                <li class="{{ request()->routeIs('reports.purchase') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('reports.purchase') }}">Purchase</a>
                </li>
              @endcan
              @can('reports.sales')
                <li class="{{ request()->routeIs('reports.sale') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('reports.sale') }}">Sales</a>
                </li>
              @endcan
              @can('reports.accounts')
                <li class="{{ request()->routeIs('reports.accounts') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('reports.accounts') }}">Accounts</a>
                </li>
              @endcan
            </ul>
          </li>
          @endif

        </ul>
      </nav>
    </div>

    <script>
      if (typeof localStorage !== 'undefined') {
        if (localStorage.getItem('sidebar-left-position') !== null) {
          var sidebarLeft = document.querySelector('#sidebar-left .nano-content');
          sidebarLeft.scrollTop = localStorage.getItem('sidebar-left-position');
        }
      }
    </script>
  </div>
</aside>