<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('organization/create', 'pages::organization.create')->name('organization.create');
});

Route::middleware(['auth', 'verified', 'tenant.context'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('products', 'pages::products.index')->name('products.index');
    Route::livewire('products/{id}', 'pages::products.show')->name('products.show');
    Route::livewire('inventory/movements', 'pages::inventory.movements.index')->name('inventory.movements.index');
    Route::livewire('inventory/adjustments/create', 'pages::inventory.adjustments.create')->name('inventory.adjustments.create');

    Route::livewire('suppliers', 'pages::suppliers.index')->name('suppliers.index');
    Route::livewire('suppliers/{id}', 'pages::suppliers.show')->name('suppliers.show');
    Route::view('procurement', 'pages.procurement.index')->name('procurement.index');
    Route::livewire('procurement/rfqs', 'pages::procurement.rfqs.index')->name('procurement.rfqs.index');
    Route::livewire('procurement/rfqs/create', 'pages::procurement.rfqs.create')->name('procurement.rfqs.create');
    Route::livewire('procurement/rfqs/{id}/edit', 'pages::procurement.rfqs.edit')->name('procurement.rfqs.edit');
    Route::livewire('procurement/rfqs/{id}', 'pages::procurement.rfqs.show')->name('procurement.rfqs.show');
    Route::livewire('procurement/purchase-orders', 'pages::procurement.purchase-orders.index')->name('procurement.purchase-orders.index');
    Route::livewire('procurement/purchase-orders/create', 'pages::procurement.purchase-orders.create')->name('procurement.purchase-orders.create');
    Route::livewire('procurement/purchase-orders/{id}/print', 'pages::procurement.purchase-orders.print')->name('procurement.purchase-orders.print');
    Route::livewire('procurement/purchase-orders/{id}', 'pages::procurement.purchase-orders.show')->name('procurement.purchase-orders.show');
    Route::livewire('procurement/goods-receipts', 'pages::procurement.goods-receipts.index')->name('procurement.goods-receipts.index');
    Route::livewire('procurement/goods-receipts/{id}', 'pages::procurement.goods-receipts.show')->name('procurement.goods-receipts.show');

    Route::livewire('customers', 'pages::customers.index')->name('customers.index');
    Route::livewire('bank-accounts', 'pages::bank-accounts.index')->name('bank-accounts.index');

    Route::livewire('sales/orders/create', 'pages::sales.orders.create')->name('sales.orders.create');
    Route::livewire('sales/orders/{id}/ship', 'pages::sales.orders.ship')->name('sales.orders.ship');
    Route::livewire('sales/orders/{id}/invoice', 'pages::sales.orders.invoice')->name('sales.orders.invoice');
    Route::livewire('sales/orders/{id}', 'pages::sales.orders.show')->name('sales.orders.show');
    Route::livewire('sales/orders', 'pages::sales.orders.index')->name('sales.orders.index');

    Route::livewire('accounting/payables', 'pages::accounting.payables.index')->name('accounting.payables.index');
    Route::livewire('accounting/payment-runs', 'pages::accounting.payment-runs.index')->name('accounting.payment-runs.index');
    Route::livewire('accounting/payment-runs/{id}', 'pages::accounting.payment-runs.show')->name('accounting.payment-runs.show');
    Route::livewire('accounting/receivables', 'pages::accounting.receivables.index')->name('accounting.receivables.index');
    Route::livewire('accounting/supplier-payments/create', 'pages::accounting.supplier-payments.create')->name('accounting.supplier-payments.create');
    Route::livewire('accounting/customer-payments/create', 'pages::accounting.customer-payments.create')->name('accounting.customer-payments.create');
});

require __DIR__.'/settings.php';
