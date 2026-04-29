<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,
    SubHeadOfAccController,
    COAController,
    SaleInvoiceController,
    PurchaseInvoiceController,
    PurchaseOrderController,
    PurchaseReturnController,
    ProductController,
    UserController,
    RoleController,
    AttributeController,
    ProductCategoryController,
    VoucherController,
    InventoryReportController,
    PurchaseReportController,
    SalesReportController,
    AccountsReportController,
    SaleReturnController,
    PermissionController,
    ProductSubcategoryController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ── User helpers ──────────────────────────────────────────────
    Route::put('/users/{id}/change-password',  [UserController::class, 'changePassword']) ->name('users.changePassword');
    Route::put('/users/{id}/toggle-active',    [UserController::class, 'toggleActive'])   ->name('users.toggleActive');
    Route::post('/change-my-password',         [UserController::class, 'changeMyPassword'])->name('users.changeMyPassword');

    // ── Product helpers ───────────────────────────────────────────
    Route::get('/products/details',                  [ProductController::class, 'details'])       ->name('products.receiving');
    Route::get('/product/{product}/variations',      [ProductController::class, 'getVariations']) ->name('product.variations');
    Route::get('/get-subcategories/{category_id}',   [ProductCategoryController::class, 'getSubcategories'])->name('products.getSubcategories');

    // ── Purchase Invoice helpers ──────────────────────────────────
    // Must be declared BEFORE the $modules loop to avoid being
    // shadowed by the generic purchase_invoices/{id} pattern.
    Route::get('purchase-invoices/po-items/{poNo}',
        [PurchaseInvoiceController::class, 'getPoItems'])
        ->name('purchase_invoices.po_items');

    Route::get('/product/{product}/invoices',
        [PurchaseInvoiceController::class, 'getProductInvoices']);

    Route::get('sale-invoices/dropship-invoices/{customerId}',
        [App\Http\Controllers\SaleInvoiceController::class, 'getDropshipInvoices'])
        ->name('sale_invoices.dropship_invoices');

    // ── Purchase Order extra routes ───────────────────────────────
    // Also declared before $modules to avoid shadowing by /{id}.
    Route::patch('purchase_orders/{id}/restore',
        [PurchaseOrderController::class, 'restore'])
        ->middleware('check.permission:purchase_orders.edit')
        ->name('purchase_orders.restore');

    Route::patch('purchase_orders/{id}/status',
        [PurchaseOrderController::class, 'updateStatus'])
        ->middleware('check.permission:purchase_orders.edit')
        ->name('purchase_orders.status');

    Route::delete('purchase_orders/attachment/{id}',
        [PurchaseOrderController::class, 'deleteAttachment'])
        ->middleware('check.permission:purchase_orders.delete')
        ->name('purchase_orders.attachment.delete');

    // ── Sale Invoice helpers ──────────────────────────────────────
    // Returns unbilled drop-ship PIs for a customer.
    // Declared before $modules to avoid shadowing by sale_invoices/{id}.
    Route::get('sale-invoices/dropship-invoices/{customerId}',
        [SaleInvoiceController::class, 'getDropshipInvoices'])
        ->name('sale_invoices.dropship_invoices');

    // ── COA credit check (used by invoice forms via AJAX) ─────────
    Route::get('coa/{id}/credit-check',
        [COAController::class, 'creditCheck'])
        ->name('coa.credit-check');

    // ─────────────────────────────────────────────────────────────
    // Common Modules — standard CRUD via loop
    // ─────────────────────────────────────────────────────────────
    $modules = [
        // User Management
        'roles'                 => ['controller' => RoleController::class,              'permission' => 'user_roles'],
        'permissions'           => ['controller' => PermissionController::class,         'permission' => 'role_permissions'],
        'users'                 => ['controller' => UserController::class,               'permission' => 'users'],

        // Accounts
        'coa'                   => ['controller' => COAController::class,                'permission' => 'coa'],
        'shoa'                  => ['controller' => SubHeadOfAccController::class,       'permission' => 'shoa'],

        // Products
        'products'              => ['controller' => ProductController::class,            'permission' => 'products'],
        'product_categories'    => ['controller' => ProductCategoryController::class,    'permission' => 'product_categories'],
        'product_subcategories' => ['controller' => ProductSubcategoryController::class, 'permission' => 'product_subcategories'],
        'attributes'            => ['controller' => AttributeController::class,          'permission' => 'attributes'],

        // Purchases
        'purchase_invoices'     => ['controller' => PurchaseInvoiceController::class,    'permission' => 'purchase_invoices'],
        'purchase_orders'       => ['controller' => PurchaseOrderController::class,      'permission' => 'purchase_orders'],
        'purchase_return'       => ['controller' => PurchaseReturnController::class,     'permission' => 'purchase_return'],

        // Sales
        'sale_invoices'         => ['controller' => SaleInvoiceController::class,        'permission' => 'sale_invoices'],
        'sale_return'           => ['controller' => SaleReturnController::class,         'permission' => 'sale_return'],

        // Vouchers
        'vouchers'              => ['controller' => VoucherController::class,            'permission' => 'vouchers'],
    ];

    foreach ($modules as $uri => $config) {
        $controller = $config['controller'];
        $permission = $config['permission'];
        $param      = $uri === 'roles' ? '{role}' : '{id}';

        // Vouchers use a {type} prefix for all routes
        if ($uri === 'vouchers') {
            Route::prefix("$uri/{type}")->group(function () use ($controller, $permission) {
                Route::get('/',           [$controller, 'index'])  ->middleware("check.permission:$permission.index")  ->name('vouchers.index');
                Route::get('/create',     [$controller, 'create']) ->middleware("check.permission:$permission.create") ->name('vouchers.create');
                Route::post('/',          [$controller, 'store'])  ->middleware("check.permission:$permission.create") ->name('vouchers.store');
                Route::get('/{id}',       [$controller, 'show'])   ->middleware("check.permission:$permission.index")  ->name('vouchers.show');
                Route::get('/{id}/edit',  [$controller, 'edit'])   ->middleware("check.permission:$permission.edit")   ->name('vouchers.edit');
                Route::put('/{id}',       [$controller, 'update']) ->middleware("check.permission:$permission.edit")   ->name('vouchers.update');
                Route::delete('/{id}',    [$controller, 'destroy'])->middleware("check.permission:$permission.delete") ->name('vouchers.destroy');
                Route::get('/{id}/print', [$controller, 'print'])  ->middleware("check.permission:$permission.print")  ->name('vouchers.print');
            });
            continue;
        }

        // Standard CRUD
        Route::get("$uri",              [$controller, 'index'])  ->middleware("check.permission:$permission.index")  ->name("$uri.index");
        Route::get("$uri/create",       [$controller, 'create']) ->middleware("check.permission:$permission.create") ->name("$uri.create");
        Route::post("$uri",             [$controller, 'store'])  ->middleware("check.permission:$permission.create") ->name("$uri.store");
        Route::get("$uri/$param",       [$controller, 'show'])   ->middleware("check.permission:$permission.index")  ->name("$uri.show");
        Route::get("$uri/$param/edit",  [$controller, 'edit'])   ->middleware("check.permission:$permission.edit")   ->name("$uri.edit");
        Route::put("$uri/$param",       [$controller, 'update']) ->middleware("check.permission:$permission.edit")   ->name("$uri.update");
        Route::delete("$uri/$param",    [$controller, 'destroy'])->middleware("check.permission:$permission.delete") ->name("$uri.destroy");
        Route::get("$uri/$param/print", [$controller, 'print'])  ->middleware("check.permission:$permission.print")  ->name("$uri.print");
    }

    // ── Reports (read-only) ───────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('inventory', [InventoryReportController::class, 'inventoryReports'])->name('inventory');
        Route::get('purchase',  [PurchaseReportController::class,  'purchaseReports']) ->name('purchase');
        Route::get('sale',      [SalesReportController::class,     'saleReports'])     ->name('sale');
        Route::get('accounts',  [AccountsReportController::class,  'accounts'])        ->name('accounts');
    });

    Route::get('/get-location-stock',    [ProductController::class,     'getLocationStock']);
    Route::get('/stock-lots/available',  [StockTransferController::class,'getAvailableLots'])->name('stock.lots.available');
});