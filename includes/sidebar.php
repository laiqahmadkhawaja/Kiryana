<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="appSidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i>
                <span><?php echo __('dashboard'); ?></span>
            </a>
        </li>
        <li>
            <a href="pos.php" class="<?php echo $current_page == 'pos.php' ? 'active' : ''; ?>">
                <i class="bi bi-cart-check"></i>
                <span><?php echo __('pos'); ?></span>
            </a>
        </li>

        <li class="sidebar-heading"><?php echo __('inventory'); ?></li>
        <li>
            <a href="products.php" class="<?php echo in_array($current_page, ['products.php', 'product_form.php']) ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i>
                <span><?php echo __('products'); ?></span>
            </a>
        </li>
        <li>
            <a href="inventory.php" class="<?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">
                <i class="bi bi-boxes"></i>
                <span><?php echo __('stock'); ?></span>
            </a>
        </li>
        <li>
            <a href="stock_adjust.php" class="<?php echo $current_page == 'stock_adjust.php' ? 'active' : ''; ?>">
                <i class="bi bi-arrow-left-right"></i>
                <span><?php echo __('adjust_stock'); ?></span>
            </a>
        </li>
        <li>
            <a href="units.php" class="<?php echo $current_page == 'units.php' ? 'active' : ''; ?>">
                <i class="bi bi-rulers"></i>
                <span><?php echo __('units'); ?></span>
            </a>
        </li>
        <li>
            <a href="categories.php" class="<?php echo $current_page == 'categories.php' ? 'active' : ''; ?>">
                <i class="bi bi-tags"></i>
                <span><?php echo __('categories'); ?> & <?php echo __('brands'); ?></span>
            </a>
        </li>
        <li>
            <a href="barcodes.php" class="<?php echo $current_page == 'barcodes.php' ? 'active' : ''; ?>">
                <i class="bi bi-upc-scan"></i>
                <span><?php echo __('barcodes'); ?></span>
            </a>
        </li>

        <li class="sidebar-heading"><?php echo __('purchases'); ?> & <?php echo __('suppliers'); ?></li>
        <li>
            <a href="purchase_create.php" class="<?php echo $current_page == 'purchase_create.php' ? 'active' : ''; ?>">
                <i class="bi bi-bag-plus"></i>
                <span>+ <?php echo __('purchases'); ?></span>
            </a>
        </li>
        <li>
            <a href="purchases.php" class="<?php echo $current_page == 'purchases.php' ? 'active' : ''; ?>">
                <i class="bi bi-receipt"></i>
                <span><?php echo __('purchases'); ?> (بلز)</span>
            </a>
        </li>
        <li>
            <a href="suppliers.php" class="<?php echo in_array($current_page, ['suppliers.php', 'supplier_ledger.php']) ? 'active' : ''; ?>">
                <i class="bi bi-truck"></i>
                <span><?php echo __('suppliers'); ?> (کھاتہ)</span>
            </a>
        </li>

        <li class="sidebar-heading"><?php echo __('customers'); ?> & سیلز</li>
        <li>
            <a href="customers.php" class="<?php echo in_array($current_page, ['customers.php', 'customer_ledger.php']) ? 'active' : ''; ?>">
                <i class="bi bi-people"></i>
                <span><?php echo __('customers'); ?> (ادھار کھاتہ)</span>
            </a>
        </li>
        <li>
            <a href="sales.php" class="<?php echo $current_page == 'sales.php' ? 'active' : ''; ?>">
                <i class="bi bi-receipt-cutoff"></i>
                <span>تمام بلز و انوائسز</span>
            </a>
        </li>
        <li>
            <a href="returns.php" class="<?php echo $current_page == 'returns.php' ? 'active' : ''; ?>">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span><?php echo __('returns'); ?></span>
            </a>
        </li>

        <li class="sidebar-heading"><?php echo __('expenses'); ?> و مالیات</li>
        <li>
            <a href="expenses.php" class="<?php echo $current_page == 'expenses.php' ? 'active' : ''; ?>">
                <i class="bi bi-wallet2"></i>
                <span><?php echo __('expenses'); ?></span>
            </a>
        </li>
        <li>
            <a href="accounts.php" class="<?php echo $current_page == 'accounts.php' ? 'active' : ''; ?>">
                <i class="bi bi-bank"></i>
                <span><?php echo __('accounts'); ?></span>
            </a>
        </li>
        <li>
            <a href="shifts.php" class="<?php echo $current_page == 'shifts.php' ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i>
                <span><?php echo __('shifts'); ?></span>
            </a>
        </li>

        <li class="sidebar-heading"><?php echo __('reports'); ?> و منافع</li>
        <li>
            <a href="reports_profit.php" class="<?php echo $current_page == 'reports_profit.php' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span><?php echo __('gross_profit'); ?> و نفع</span>
            </a>
        </li>
        <li>
            <a href="reports_sales.php" class="<?php echo $current_page == 'reports_sales.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart"></i>
                <span>فروخت کی رپورٹس</span>
            </a>
        </li>

        <li class="sidebar-heading">انتظام و کنٹرول</li>
        <li>
            <a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                <i class="bi bi-shield-lock"></i>
                <span><?php echo __('users'); ?></span>
            </a>
        </li>
        <li>
            <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-sliders"></i>
                <span><?php echo __('settings'); ?></span>
            </a>
        </li>
        <li>
            <a href="backup.php" class="<?php echo $current_page == 'backup.php' ? 'active' : ''; ?>">
                <i class="bi bi-cloud-arrow-down"></i>
                <span><?php echo __('backup'); ?></span>
            </a>
        </li>
    </ul>
</aside>
<main class="main-content">
