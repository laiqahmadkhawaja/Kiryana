// assets/js/pos.js - Point of Sale Fast Billing Engine

let cart = [];
let customersList = [];
let categoriesList = [];
let selectedCustomer = null;
let currentPricingTier = 'retail'; // 'retail' or 'wholesale'

document.addEventListener("DOMContentLoaded", function () {
    initPOS();
    setupHotkeys();
    loadCustomers();
    searchProducts(''); // initial load of items
});

// Initialize POS listeners
function initPOS() {
    const searchInput = document.getElementById('posProductSearch');
    if (searchInput) {
        searchInput.focus();

        // Barcode scanner or fast typing listener
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = this.value.trim();
                if (query) {
                    handleBarcodeInput(query);
                }
            }
        });

        // Live typing search with debounce
        let searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                searchProducts(this.value.trim());
            }, 250);
        });
    }

    // Customer Select Change
    const customerSelect = document.getElementById('posCustomerSelect');
    if (customerSelect) {
        customerSelect.addEventListener('change', function () {
            const cid = parseInt(this.value);
            if (cid > 0) {
                selectedCustomer = customersList.find(c => c.id == cid) || null;
                updateCustomerBadge();
            } else {
                selectedCustomer = null;
                updateCustomerBadge();
            }
        });
    }

    // Pricing Tier Toggle
    const tierRadios = document.querySelectorAll('input[name="pricing_tier"]');
    tierRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            currentPricingTier = this.value;
            // update prices in current cart
            refreshCartPrices();
            searchProducts(document.getElementById('posProductSearch').value.trim());
        });
    });

    // Discount input listener
    const billDiscount = document.getElementById('billDiscountInput');
    if (billDiscount) {
        billDiscount.addEventListener('input', calculateCartTotals);
    }

    // Cash Tendered listener
    const cashTendered = document.getElementById('cashTenderedInput');
    if (cashTendered) {
        cashTendered.addEventListener('input', calculateChange);
    }

    // Modal Payment Method change listener (Block credit for walk-in)
    const modalPaymentMethod = document.getElementById('modalPaymentMethod');
    if (modalPaymentMethod) {
        modalPaymentMethod.addEventListener('change', function () {
            const isWalkIn = !selectedCustomer || selectedCustomer.id <= 0;
            if (isWalkIn && this.value === 'Customer Credit') {
                showToast("عام خریدار کے لیے ادھار ممکن نہیں! برائے مہربانی کھاتہ سے گاہک منتخب کریں۔", "error");
                this.value = 'Cash';
            }
            updateModalChange();
        });
    }
}

// Setup Keyboard Hotkeys (F2, F4, F8, F9)
function setupHotkeys() {
    window.addEventListener('keydown', function (e) {
        if (e.key === 'F2') {
            e.preventDefault();
            const search = document.getElementById('posProductSearch');
            if (search) {
                search.focus();
                search.select();
            }
        } else if (e.key === 'F4') {
            e.preventDefault();
            triggerCheckout();
        } else if (e.key === 'F8') {
            e.preventDefault();
            holdCurrentBill();
        } else if (e.key === 'F9') {
            e.preventDefault();
            resetBill();
        } else if (e.key === 'Escape') {
            // Close any open modals
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(m => {
                const bsModal = bootstrap.Modal.getInstance(m);
                if (bsModal) bsModal.hide();
            });
        }
    });
}

// Search products via AJAX
function searchProducts(query, categoryId = 0) {
    const grid = document.getElementById('posProductGrid');
    if (!grid) return;

    fetch(`ajax/pos_actions.php?action=search_products&q=${encodeURIComponent(query)}&category_id=${categoryId}&price_tier=${currentPricingTier}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderProductGrid(data.products);
            }
        })
        .catch(err => console.error("Error searching products:", err));
}

// Handle Barcode Scanner Immediate Hit
function handleBarcodeInput(query) {
    fetch(`ajax/pos_actions.php?action=search_products&q=${encodeURIComponent(query)}&price_tier=${currentPricingTier}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.products.length > 0) {
                // If exact barcode match or single item, add directly to cart
                const exact = data.products.find(p => p.barcode === query || p.code === query);
                const itemToAdd = exact || data.products[0];
                addToCart(itemToAdd);

                // clear search input for next scan
                const search = document.getElementById('posProductSearch');
                search.value = '';
            } else {
                showToast("Product not found! پراڈکٹ موجود نہیں ہے۔", "error");
            }
        });
}

// Render product cards on left side
function renderProductGrid(products) {
    const grid = document.getElementById('posProductGrid');
    if (!grid) return;

    if (products.length === 0) {
        grid.innerHTML = `<div class="col-12 text-center text-muted py-5"><i class="bi bi-search fs-2"></i><br>کوئی پراڈکٹ نہیں ملی</div>`;
        return;
    }

    grid.innerHTML = products.map(p => `
        <div class="product-pos-card shadow-sm" onclick="addToCart(${JSON.stringify(p).replace(/"/g, '&quot;')})">
            <div class="pos-card-name" title="${p.name}">${p.name}</div>
            <div class="pos-card-price">Rs. ${parseFloat(p.current_price).toFixed(2)}</div>
            <div class="pos-card-stock">
                اسٹاک: <span class="${p.current_stock <= 0 ? 'text-danger fw-bold' : 'text-success'}">${p.current_stock} ${p.unit_symbol}</span>
            </div>
        </div>
    `).join('');
}

// Add Item to Cart
function addToCart(product) {
    const existingIndex = cart.findIndex(item => item.product_id === product.id);

    if (existingIndex > -1) {
        cart[existingIndex].qty += 1;
        cart[existingIndex].subtotal = (cart[existingIndex].qty * cart[existingIndex].price) - cart[existingIndex].discount;
    } else {
        const price = parseFloat(product.current_price);
        cart.push({
            product_id: product.id,
            code: product.code,
            barcode: product.barcode,
            name: product.name,
            price: price,
            retail_price: parseFloat(product.retail_price),
            wholesale_price: parseFloat(product.wholesale_price),
            cost_price: parseFloat(product.purchase_price),
            qty: 1,
            unit_id: product.unit_id,
            unit_symbol: product.unit_symbol,
            allow_decimal: product.allow_decimal,
            conversion_factor: product.conversion_factor || 1,
            discount: 0,
            subtotal: price
        });
    }

    renderCartTable();
    playBeep();
}

// Render Cart Table
function renderCartTable() {
    const tbody = document.getElementById('cartTableBody');
    const emptyNotice = document.getElementById('emptyCartNotice');
    if (!tbody) return;

    if (cart.length === 0) {
        tbody.innerHTML = '';
        if (emptyNotice) emptyNotice.style.display = 'block';
        calculateCartTotals();
        return;
    }

    if (emptyNotice) emptyNotice.style.display = 'none';

    tbody.innerHTML = cart.map((item, index) => `
        <tr>
            <td>
                <div class="fw-bold small text-truncate" style="max-width: 140px;">${item.name}</div>
                <div class="text-secondary fw-medium" style="font-size: 0.74rem;">${item.unit_symbol} | Rs. ${item.price.toFixed(2)}</div>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <button class="btn btn-sm btn-outline-secondary px-1 py-0 me-1" onclick="changeQty(${index}, -1)" aria-label="Decrease quantity">-</button>
                    <input type="number" step="${item.allow_decimal ? '0.05' : '1'}" min="0.01" class="form-control form-control-sm qty-input" 
                           value="${item.qty}" onchange="setQty(${index}, this.value)" aria-label="Quantity">
                    <button class="btn btn-sm btn-outline-secondary px-1 py-0 ms-1" onclick="changeQty(${index}, 1)" aria-label="Increase quantity">+</button>
                </div>
            </td>
            <td class="text-end fw-bold">
                Rs. ${item.subtotal.toFixed(2)}
            </td>
            <td class="text-center">
                <button class="btn btn-sm text-danger p-0 border-0" onclick="removeFromCart(${index})" title="Remove item" aria-label="Remove item">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </td>
        </tr>
    `).join('');

    calculateCartTotals();
}

// Change Quantity (+ / -)
function changeQty(index, delta) {
    if (cart[index]) {
        let newQty = cart[index].qty + delta;
        if (newQty <= 0) {
            removeFromCart(index);
            return;
        }
        cart[index].qty = Math.round(newQty * 1000) / 1000;
        cart[index].subtotal = (cart[index].qty * cart[index].price) - cart[index].discount;
        renderCartTable();
    }
}

// Set Exact Quantity (e.g. 0.250 kg)
function setQty(index, value) {
    const val = parseFloat(value);
    if (isNaN(val) || val <= 0) {
        removeFromCart(index);
        return;
    }
    if (cart[index]) {
        cart[index].qty = val;
        cart[index].subtotal = (cart[index].qty * cart[index].price) - cart[index].discount;
        renderCartTable();
    }
}

// Remove from cart
function removeFromCart(index) {
    cart.splice(index, 1);
    renderCartTable();
}

// Reset / Clear Bill (F9)
function resetBill() {
    if (cart.length > 0) {
        if (!confirm("Are you sure you want to clear this bill? کیا آپ بل صاف کرنا چاہتے ہیں؟")) {
            return;
        }
    }
    cart = [];
    selectedCustomer = null;
    document.getElementById('posCustomerSelect').value = '0';
    document.getElementById('billDiscountInput').value = '0';
    document.getElementById('cashTenderedInput').value = '';
    document.getElementById('changeReturnAmount').innerText = 'Rs. 0.00';
    updateCustomerBadge();
    renderCartTable();
    const search = document.getElementById('posProductSearch');
    if (search) search.focus();
}

// Calculate Cart Totals
function calculateCartTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.subtotal;
    });

    const discountInput = document.getElementById('billDiscountInput');
    const discount = discountInput ? (parseFloat(discountInput.value) || 0) : 0;
    const grandTotal = Math.max(0, subtotal - discount);

    document.getElementById('posSubtotal').innerText = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('posGrandTotal').innerText = 'Rs. ' + grandTotal.toFixed(2);
    document.getElementById('cartItemsCount').innerText = cart.length;

    calculateChange();
}

// Calculate Change Return
function calculateChange() {
    const grandTotalStr = document.getElementById('posGrandTotal').innerText.replace('Rs. ', '');
    const grandTotal = parseFloat(grandTotalStr) || 0;
    const cashInput = document.getElementById('cashTenderedInput');
    const tendered = cashInput ? (parseFloat(cashInput.value) || 0) : 0;

    const change = Math.max(0, tendered - grandTotal);
    const changeEl = document.getElementById('changeReturnAmount');
    if (changeEl) {
        changeEl.innerText = 'Rs. ' + change.toFixed(2);
    }
}

// Refresh Cart Prices when switching Retail / Wholesale
function refreshCartPrices() {
    cart.forEach(item => {
        item.price = currentPricingTier === 'wholesale' ? item.wholesale_price : item.retail_price;
        item.subtotal = (item.qty * item.price) - item.discount;
    });
    renderCartTable();
}

// Trigger Checkout Modal (F4)
function triggerCheckout() {
    if (cart.length === 0) {
        showToast("Cart is empty! برائے مہربانی بل میں اشیاء شامل کریں۔", "error");
        return;
    }

    const modalEl = document.getElementById('checkoutModal');
    if (modalEl) {
        const grandTotalStr = document.getElementById('posGrandTotal').innerText.replace('Rs. ', '');
        const grandTotal = parseFloat(grandTotalStr) || 0;

        document.getElementById('modalTotalAmount').innerText = 'Rs. ' + grandTotal.toFixed(2);
        
        const isWalkIn = !selectedCustomer || selectedCustomer.id <= 0;
        const optCredit = document.getElementById('optCustomerCredit') || document.querySelector('#modalPaymentMethod option[value="Customer Credit"]');
        const paymentMethodSelect = document.getElementById('modalPaymentMethod');

        if (optCredit) {
            optCredit.disabled = isWalkIn;
        }

        if (isWalkIn && paymentMethodSelect && paymentMethodSelect.value === 'Customer Credit') {
            paymentMethodSelect.value = 'Cash';
        }

        // Default cash amount to grand total if empty or if walk-in customer has less than total
        const modalCash = document.getElementById('modalPaidAmount');
        const currentPaid = parseFloat(modalCash.value) || 0;
        if (!modalCash.value || currentPaid === 0 || (isWalkIn && currentPaid < grandTotal)) {
            modalCash.value = grandTotal;
        }
        updateModalChange();

        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();

        // Focus cash input
        setTimeout(() => {
            modalCash.focus();
            modalCash.select();
        }, 400);
    }
}

// Update Modal Change
function updateModalChange() {
    const total = parseFloat(document.getElementById('modalTotalAmount').innerText.replace('Rs. ', '')) || 0;
    const paid = parseFloat(document.getElementById('modalPaidAmount').value) || 0;
    const change = Math.max(0, paid - total);
    const balance = Math.max(0, total - paid);

    document.getElementById('modalChangeAmount').innerText = 'Rs. ' + change.toFixed(2);
    document.getElementById('modalBalanceAmount').innerText = 'Rs. ' + balance.toFixed(2);

    const isWalkIn = !selectedCustomer || selectedCustomer.id <= 0;
    const warningEl = document.getElementById('walkinCreditWarning');
    const submitBtn = document.getElementById('btnSubmitSale');
    const paymentMethodSelect = document.getElementById('modalPaymentMethod');
    const isCreditMethod = paymentMethodSelect && paymentMethodSelect.value === 'Customer Credit';

    // Strictly forbid credit/balance for walk-in customer
    if (isWalkIn && (balance > 0 || isCreditMethod || paid < total)) {
        if (warningEl) {
            warningEl.classList.remove('d-none');
            warningEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>عام خریدار (Walk-in) کے لیے ادھار ممکن نہیں ہے۔</strong> پورا کیش (Rs. ${total.toFixed(2)}) وصول کریں یا کھاتہ دار گاہک منتخب کریں۔`;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50');
            submitBtn.title = "عام خریدار کے لیے ادھار ممکن نہیں";
        }
    } else {
        if (warningEl) warningEl.classList.add('d-none');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50');
            submitBtn.removeAttribute('title');
        }
    }
}

// Process Complete Sale
function processSale(paymentMethodOverride = null) {
    if (cart.length === 0) return;

    const subtotal = parseFloat(document.getElementById('posSubtotal').innerText.replace('Rs. ', '')) || 0;
    const discount = parseFloat(document.getElementById('billDiscountInput').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount);

    let paidAmount = parseFloat(document.getElementById('modalPaidAmount').value) || 0;
    let paymentMethod = paymentMethodOverride || document.getElementById('modalPaymentMethod').value || 'Cash';
    let accountId = parseInt(document.getElementById('modalPaymentAccount').value) || 1;

    let saleType = 'cash';
    let balanceAmount = 0;
    let changeAmount = 0;

    if (paidAmount >= grandTotal) {
        changeAmount = paidAmount - grandTotal;
        paidAmount = grandTotal; // Net paid towards invoice
        saleType = 'cash';
    } else if (paidAmount > 0 && paidAmount < grandTotal) {
        saleType = 'split'; // partial credit
        balanceAmount = grandTotal - paidAmount;
    } else {
        saleType = 'credit'; // full credit
        balanceAmount = grandTotal;
    }

    // If credit or balance, ensure customer is selected
    const customerId = selectedCustomer ? selectedCustomer.id : 0;
    const customerName = selectedCustomer ? selectedCustomer.name : 'Walk-in Customer';
    const isWalkIn = customerId <= 0;

    // Strict validation: Walk-in customer cannot have credit or partial balance
    if (isWalkIn && (balanceAmount > 0 || paymentMethod === 'Customer Credit' || paidAmount < grandTotal || saleType !== 'cash')) {
        showToast("عام خریدار کو ادھار پر بل جاری نہیں کیا جا سکتا! برائے مہربانی پہلے کھاتہ سے گاہک منتخب کریں۔", "error");
        updateModalChange();
        return;
    }

    if (balanceAmount > 0 && customerId <= 0) {
        showToast("Credit sale requires selecting a customer from Khata! ادھار فروخت کے لیے گاہک منتخب کریں۔", "error");
        return;
    }

    const payload = {
        action: 'save_sale',
        customer_id: customerId,
        customer_name: customerName,
        customer_type: selectedCustomer ? selectedCustomer.customer_type : 'retail',
        sale_type: saleType,
        subtotal: subtotal,
        discount_amount: discount,
        tax_amount: 0,
        grand_total: grandTotal,
        paid_amount: paidAmount,
        balance_amount: balanceAmount,
        change_amount: changeAmount,
        payment_account_id: accountId,
        payment_method: paymentMethod,
        items: cart
    };

    fetch('ajax/pos_actions.php?action=save_sale', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Close modal
            const modalEl = document.getElementById('checkoutModal');
            if (modalEl) {
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
            }

            showToast(data.message, "success");

            // Open Thermal Receipt Print Dialog / Popup
            openReceiptPrint(data.sale_id);

            // Reset cart
            cart = [];
            selectedCustomer = null;
            document.getElementById('posCustomerSelect').value = '0';
            document.getElementById('billDiscountInput').value = '0';
            document.getElementById('cashTenderedInput').value = '';
            updateCustomerBadge();
            renderCartTable();

            // Refresh products stock view
            searchProducts('');
        } else {
            showToast(data.message, "error");
        }
    })
    .catch(err => {
        console.error(err);
        showToast("Network error while completing sale!", "error");
    });
}

// Open Print Receipt in iframe or popup
function openReceiptPrint(saleId) {
    const printUrl = `print_invoice.php?id=${saleId}&auto_print=1`;
    const printWindow = window.open(printUrl, 'InvoicePrint', 'width=420,height=600');
    if (printWindow) {
        printWindow.focus();
    }
}

// Load Customers for Dropdown
function loadCustomers() {
    fetch('ajax/pos_actions.php?action=search_customers')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                customersList = data.customers;
                const select = document.getElementById('posCustomerSelect');
                if (select) {
                    select.innerHTML = '<option value="0">عام خریدار (Walk-in Cash Customer)</option>' +
                        data.customers.map(c => `
                            <option value="${c.id}">${c.name} (${c.phone || 'No phone'}) - بقایا: Rs. ${parseFloat(c.current_balance).toFixed(2)}</option>
                        `).join('');
                }
            }
        });
}

// Update Customer Info Badge on POS
function updateCustomerBadge() {
    const infoBox = document.getElementById('posCustomerInfoBox');
    if (!infoBox) return;

    if (selectedCustomer) {
        infoBox.innerHTML = `
            <div class="alert alert-info py-1 px-2 mb-2 small d-flex justify-content-between align-items-center">
                <div>
                    <strong><i class="bi bi-person-check me-1"></i> ${selectedCustomer.name}</strong><br>
                    سابقہ بقایا کھاتہ: <span class="fw-bold text-danger">Rs. ${parseFloat(selectedCustomer.current_balance).toFixed(2)}</span>
                </div>
                <div class="text-end">
                    ادھار کی حد: <span class="badge bg-secondary">Rs. ${parseFloat(selectedCustomer.credit_limit).toFixed(2)}</span>
                </div>
            </div>
        `;
    } else {
        infoBox.innerHTML = '';
    }
}

// Hold Current Bill (F8)
function holdCurrentBill() {
    if (cart.length === 0) {
        showToast("Cart is empty to hold! روکنے کے لیے بل میں اشیاء ہونا ضروری ہیں۔", "error");
        return;
    }

    const payload = {
        customer_name: selectedCustomer ? selectedCustomer.name : 'Walk-in',
        customer_id: selectedCustomer ? selectedCustomer.id : 0,
        items: cart,
        grand_total: document.getElementById('posGrandTotal').innerText
    };

    fetch('ajax/pos_actions.php?action=hold_bill', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, "info");
            cart = [];
            renderCartTable();
            updateHeldBillsCount(data.held_bills.length);
        }
    });
}

// View Held Bills Modal
function showHeldBillsModal() {
    fetch('ajax/pos_actions.php?action=get_held_bills')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const listEl = document.getElementById('heldBillsList');
                if (data.held_bills.length === 0) {
                    listEl.innerHTML = '<div class="text-center text-muted py-4">کوئی بل ہولڈ نہیں ہے۔</div>';
                } else {
                    listEl.innerHTML = data.held_bills.map(b => `
                        <div class="card mb-2 p-2 border shadow-sm d-flex flex-row justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold">${b.customer_name}</h6>
                                <div class="small text-muted">${b.time} | ${b.items.length} اشیاء | ${b.total}</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-success" onclick="resumeHeldBill('${b.id}')">
                                    <i class="bi bi-arrow-clockwise"></i> بحال کریں
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteHeldBill('${b.id}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `).join('');
                }

                const modal = new bootstrap.Modal(document.getElementById('heldBillsModal'));
                modal.show();
            }
        });
}

// Resume Held Bill
function resumeHeldBill(holdId) {
    fetch('ajax/pos_actions.php?action=get_held_bills')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const bill = data.held_bills.find(b => b.id === holdId);
                if (bill) {
                    cart = bill.items;
                    if (bill.customer_id > 0) {
                        document.getElementById('posCustomerSelect').value = bill.customer_id;
                        selectedCustomer = customersList.find(c => c.id == bill.customer_id) || null;
                        updateCustomerBadge();
                    }
                    renderCartTable();

                    // Delete from held bills
                    deleteHeldBill(holdId, false);

                    const modal = bootstrap.Modal.getInstance(document.getElementById('heldBillsModal'));
                    if (modal) modal.hide();

                    showToast("Held bill resumed! روکا ہوا بل بحال کر دیا گیا۔", "success");
                }
            }
        });
}

// Delete Held Bill
function deleteHeldBill(holdId, refreshUI = true) {
    fetch(`ajax/pos_actions.php?action=delete_held_bill&hold_id=${holdId}`)
        .then(res => res.json())
        .then(data => {
            if (refreshUI) {
                showHeldBillsModal();
            }
            updateHeldBillsCount(data.held_bills.length);
        });
}

function updateHeldBillsCount(count) {
    const badge = document.getElementById('heldBillsBadge');
    if (badge) {
        badge.innerText = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
    }
}

// Quick Audio Beep for POS
function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.value = 1200;
        gain.gain.value = 0.05;
        osc.start();
        setTimeout(() => {
            osc.stop();
            audioCtx.close();
        }, 60);
    } catch (e) {
        // AudioContext not allowed or unsupported
    }
}

// ==========================================
// Live Camera Barcode Scanner — Auto Zoom & Align + Zoom In/Out Modification
// ==========================================
let html5QrCodeScanner = null;
let lastScannedCode = "";
let lastScanTime = 0;

// Native BarcodeDetector scan loop state
let _scanStream = null;
let _scanAnimFrame = null;
let _scanVideo = null;
let _scanCanvas = null;
let _scanCtx = null;
let _barcodeDetector = null;

// Zoom state
let _currentManualZoom = 1.0;
let _maxZoom = 4.0;
let _minZoom = 1.0;
let _zoomTrack = null;

// Smooth crop animation state (video pixel coordinates)
let _zoomRect = null;
let _targetRect = null;

// Focus/Center alignment helper
let _barcodeFoundTime = 0;

function openCameraScanner() {
    const modalEl = document.getElementById('cameraScannerModal');
    if (!modalEl) return;

    _currentManualZoom = 1.0;
    updateZoomDisplay();

    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    modalEl.addEventListener('shown.bs.modal', function onShown() {
        startCameraScanner();
        modalEl.removeEventListener('shown.bs.modal', onShown);
    });

    modalEl.addEventListener('hidden.bs.modal', function onHidden() {
        stopCameraScanner();
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
    });
}

function updateZoomDisplay() {
    const el = document.getElementById('zoomLevelDisplay');
    if (el) {
        el.innerText = `${_currentManualZoom.toFixed(1)}x`;
    }
}

function adjustScannerZoom(delta) {
    const newZoom = Math.min(_maxZoom, Math.max(_minZoom, _currentManualZoom + delta));
    setScannerZoom(newZoom);
}

function resetScannerZoom() {
    setScannerZoom(1.0);
}

function setScannerZoom(zoomVal) {
    _currentManualZoom = Math.round(zoomVal * 100) / 100;
    updateZoomDisplay();

    // Apply zoom whether on Native BarcodeDetector OR on html5-qrcode fallback!
    
    // 1. Hardware track zoom (applies to both modes if track is available)
    let track = _zoomTrack;
    if (!track && html5QrCodeScanner) {
        try {
            // Retrieve video element created by html5-qrcode
            const videoElem = document.querySelector('#camera-reader video');
            if (videoElem && videoElem.srcObject) {
                const trks = videoElem.srcObject.getVideoTracks();
                if (trks.length > 0) track = trks[0];
            }
        } catch (_) {}
    }

    let hwZoomApplied = false;
    if (track && typeof track.getCapabilities === 'function') {
        try {
            const caps = track.getCapabilities();
            if (caps.zoom) {
                const hwMin = caps.zoom.min || 1;
                const hwMax = caps.zoom.max || 1;
                const hwVal = Math.min(hwMax, Math.max(hwMin, _currentManualZoom));
                track.applyConstraints({ advanced: [{ zoom: hwVal }] }).then(() => {
                    hwZoomApplied = true;
                }).catch(() => {});
            }
        } catch (_) {}
    }

    // 2. Fallback / supplementary Digital CSS Zoom:
    // If html5-qrcode is active or camera is rendering in video element,
    // apply CSS transform scale directly to the video element for instant visual zoom!
    const videoElem = document.querySelector('#camera-reader video');
    if (videoElem && html5QrCodeScanner) {
        videoElem.style.transition = 'transform 0.2s ease';
        videoElem.style.transformOrigin = 'center center';
        videoElem.style.transform = `scale(${_currentManualZoom})`;
    }

    // 3. Adjust digital canvas crop bounds immediately for native canvas scanner
    if (_scanVideo && _scanVideo.videoWidth > 0) {
        const vw = _scanVideo.videoWidth;
        const vh = _scanVideo.videoHeight;
        const cropW = vw / _currentManualZoom;
        const cropH = vh / _currentManualZoom;
        _targetRect = {
            x: (vw - cropW) / 2,
            y: (vh - cropH) / 2,
            w: cropW,
            h: cropH
        };
    }
}

function _handleScanResult(code) {
    const now = Date.now();
    if (code === lastScannedCode && (now - lastScanTime) < 1500) return;
    lastScannedCode = code;
    lastScanTime = now;

    // Visual alignment highlight: flash green reticle
    const box = document.getElementById('_scanBox');
    if (box) {
        box.style.borderColor = '#22c55e';
        box.style.boxShadow = '0 0 15px rgba(34, 197, 94, 0.9)';
        setTimeout(() => {
            if (box) {
                box.style.borderColor = 'rgba(74,222,128,0.9)';
                box.style.boxShadow = '0 0 0 9999px rgba(0,0,0,0.38)';
            }
        }, 600);
    }

    playBeep();
    showToast(`کیمرہ سے اسکین ہوا: ${code}`, "success");
    handleBarcodeInput(code);

    const continuousCheck = document.getElementById('cameraContinuousScan');
    if (continuousCheck && !continuousCheck.checked) {
        stopCameraScanner();
        const modal = bootstrap.Modal.getInstance(document.getElementById('cameraScannerModal'));
        if (modal) modal.hide();
    }
}

// Smooth linear interpolation between two crop rectangles
function _lerpRect(cur, tgt, t) {
    if (!cur) return tgt;
    return {
        x: cur.x + (tgt.x - cur.x) * t,
        y: cur.y + (tgt.y - cur.y) * t,
        w: cur.w + (tgt.w - cur.w) * t,
        h: cur.h + (tgt.h - cur.h) * t
    };
}

function _startNativeScanner(readerEl) {
    readerEl.innerHTML = '';
    readerEl.style.position = 'relative';

    // Hidden video element
    const video = document.createElement('video');
    video.setAttribute('playsinline', '');
    video.setAttribute('muted', '');
    video.style.cssText = 'position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;';
    readerEl.appendChild(video);

    // Visible canvas
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'width:100%;height:100%;border-radius:8px;display:block;background:#0f172a;cursor:pointer;';
    canvas.title = "ڈبل کلک یا ٹیپ سے زوم ان / زوم آؤٹ کریں (Double-click/tap to zoom)";
    readerEl.appendChild(canvas);

    // Double-click or tap to toggle zoom
    canvas.addEventListener('dblclick', () => {
        if (_currentManualZoom > 1.2) {
            resetScannerZoom();
        } else {
            setScannerZoom(2.0);
        }
    });

    // Targeting reticle overlay with animated scan line
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:absolute;inset:0;pointer-events:none;';
    overlay.innerHTML = `
      <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
        <div id="_scanBox" style="width:78%;height:50%;border:2px solid rgba(74,222,128,0.9);border-radius:8px;
             box-shadow:0 0 0 9999px rgba(0,0,0,0.38);position:relative;transition:border-color 0.2s, box-shadow 0.2s;">
          <div style="position:absolute;top:-2px;left:-2px;width:22px;height:22px;border-top:3px solid #4ade80;border-left:3px solid #4ade80;border-radius:3px 0 0 0;"></div>
          <div style="position:absolute;top:-2px;right:-2px;width:22px;height:22px;border-top:3px solid #4ade80;border-right:3px solid #4ade80;border-radius:0 3px 0 0;"></div>
          <div style="position:absolute;bottom:-2px;left:-2px;width:22px;height:22px;border-bottom:3px solid #4ade80;border-left:3px solid #4ade80;border-radius:0 0 0 3px;"></div>
          <div style="position:absolute;bottom:-2px;right:-2px;width:22px;height:22px;border-bottom:3px solid #4ade80;border-right:3px solid #4ade80;border-radius:0 0 3px 0;"></div>
          <div style="position:absolute;top:0;left:0;width:100%;height:2px;background:linear-gradient(to right,transparent,#4ade80,transparent);animation:_kScanline 1.6s linear infinite;"></div>
        </div>
        <div style="position:absolute;bottom:10px;left:0;right:0;text-align:center;color:#e2e8f0;font-size:0.75rem;text-shadow:0 1px 3px #000;">
          خودکار الائن اور زوم فعال ہے | ڈبل ٹیپ سے زوم تبدیل کریں
        </div>
      </div>`;
    readerEl.appendChild(overlay);

    if (!document.getElementById('_kScanlineCSS')) {
        const st = document.createElement('style');
        st.id = '_kScanlineCSS';
        st.textContent = '@keyframes _kScanline{0%{top:0}100%{top:calc(100% - 2px)}}';
        document.head.appendChild(st);
    }

    _scanVideo = video;
    _scanCanvas = canvas;
    _scanCtx = canvas.getContext('2d');
    _barcodeDetector = new BarcodeDetector({
        formats: ['ean_13','ean_8','upc_a','upc_e','code_128','code_39','qr_code','itf','codabar']
    });

    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'environment',
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        }
    }).then(stream => {
        _scanStream = stream;
        const tracks = stream.getVideoTracks();
        if (tracks.length > 0) {
            _zoomTrack = tracks[0];
            // Read hardware zoom capabilities if present
            if (typeof _zoomTrack.getCapabilities === 'function') {
                const caps = _zoomTrack.getCapabilities();
                if (caps.zoom) {
                    _minZoom = caps.zoom.min || 1;
                    _maxZoom = caps.zoom.max || 4;
                }
            }
        }

        video.srcObject = stream;
        video.play();
        video.addEventListener('loadedmetadata', () => {
            canvas.width  = readerEl.clientWidth  || 400;
            canvas.height = readerEl.clientHeight || 280;
            _zoomRect   = { x: 0, y: 0, w: video.videoWidth, h: video.videoHeight };
            _targetRect = { ..._zoomRect };
            _nativeScanLoop();
        });
    }).catch(err => {
        const errEl = document.getElementById('camera-error');
        if (errEl) {
            errEl.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> کیمرہ آن نہیں ہو سکا: ${err}. براہ کرم براؤزر کیمرہ کی اجازت چیک کریں۔`;
            errEl.style.display = 'block';
        }
    });
}

function _nativeScanLoop() {
    if (!_scanVideo || _scanVideo.readyState < 2) {
        _scanAnimFrame = requestAnimationFrame(_nativeScanLoop);
        return;
    }

    const vw = _scanVideo.videoWidth;
    const vh = _scanVideo.videoHeight;
    const cw = _scanCanvas.width;
    const ch = _scanCanvas.height;

    // Apply manual base zoom if user zoomed in/out
    let baseW = vw / _currentManualZoom;
    let baseH = vh / _currentManualZoom;
    let baseX = (vw - baseW) / 2;
    let baseY = (vh - baseH) / 2;

    if (!_targetRect) {
        _targetRect = { x: baseX, y: baseY, w: baseW, h: baseH };
    }

    // Smooth ease toward target
    _zoomRect = _lerpRect(_zoomRect, _targetRect, 0.10);

    // Draw frame onto canvas
    _scanCtx.drawImage(_scanVideo, _zoomRect.x, _zoomRect.y, _zoomRect.w, _zoomRect.h, 0, 0, cw, ch);

    // Barcode detection on video source
    _barcodeDetector.detect(_scanVideo).then(codes => {
        if (!codes.length) {
            // Keep user manual zoom base rect
            _targetRect = { x: baseX, y: baseY, w: baseW, h: baseH };
            return;
        }

        const bc = codes[0];
        _barcodeFoundTime = Date.now();
        _handleScanResult(bc.rawValue);

        // Auto-align: Center and auto-zoom directly onto barcode bounding box
        const bb = bc.boundingBox;
        const padX = bb.width  * 0.5;
        const padY = bb.height * 0.5;
        let nx = Math.max(0, bb.left - padX);
        let ny = Math.max(0, bb.top  - padY);
        let nw = Math.min(vw - nx, bb.width  + padX * 2);
        let nh = Math.min(vh - ny, bb.height + padY * 2);

        // Maintain aspect ratio matching canvas
        const aspect = cw / ch;
        if (nw / nh > aspect) {
            nh = nw / aspect;
        } else {
            nw = nh * aspect;
        }

        const minW = vw * 0.20;
        const minH = vh * 0.20;
        nw = Math.max(nw, minW);
        nh = Math.max(nh, minH);
        nx = Math.max(0, Math.min(vw - nw, (bb.left + bb.width / 2) - nw / 2));
        ny = Math.max(0, Math.min(vh - nh, (bb.top  + bb.height / 2) - nh / 2));

        _targetRect = { x: nx, y: ny, w: nw, h: nh };
    }).catch(() => {});

    _scanAnimFrame = requestAnimationFrame(_nativeScanLoop);
}

function startCameraScanner() {
    const readerEl = document.getElementById('camera-reader');
    if (!readerEl) return;

    stopCameraScanner();
    const errEl = document.getElementById('camera-error');
    if (errEl) errEl.style.display = 'none';

    // 1. Native BarcodeDetector (Chrome 83+, Edge, Android WebView) with real-time dynamic zoom & align
    if ('BarcodeDetector' in window) {
        _startNativeScanner(readerEl);
        return;
    }

    // 2. Fallback: html5-qrcode library
    if (typeof Html5Qrcode === "undefined") {
        if (errEl) {
            errEl.innerText = "Camera scanner library is loading. Please refresh or check connection.";
            errEl.style.display = 'block';
        }
        return;
    }

    html5QrCodeScanner = new Html5Qrcode("camera-reader");
    const rw = readerEl.clientWidth || 320;
    const boxW = Math.round(Math.min(rw * 0.82, 300));

    html5QrCodeScanner.start(
        { facingMode: "environment" },
        {
            fps: 15,
            qrbox: { width: boxW, height: Math.round(boxW * 0.55) },
            aspectRatio: 1.777778,
            videoConstraints: {
                facingMode: "environment",
                width: { ideal: 1280 },
                height: { ideal: 720 },
                advanced: [{ focusMode: "continuous" }]
            }
        },
        (decodedText) => _handleScanResult(decodedText),
        (_err) => {}
    ).then(() => {
        try {
            const caps = html5QrCodeScanner.getRunningTrackCameraCapabilities();
            if (caps && typeof caps.zoomFeature === 'function') {
                const zf = caps.zoomFeature();
                if (zf && zf.isSupported()) {
                    zf.apply(Math.min(zf.min() * 1.5, zf.max()));
                }
            }
        } catch (_) {}
    }).catch(err => {
        if (errEl) {
            errEl.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> کیمرہ آن نہیں ہو سکا: ${err}. براہ کرم براؤزر کیمرہ کی اجازت چیک کریں۔`;
            errEl.style.display = 'block';
        }
    });
}

function stopCameraScanner() {
    // Stop native scanner loop
    if (_scanAnimFrame) {
        cancelAnimationFrame(_scanAnimFrame);
        _scanAnimFrame = null;
    }
    if (_scanStream) {
        _scanStream.getTracks().forEach(t => t.stop());
        _scanStream = null;
    }
    _zoomTrack = null;
    _scanVideo = null;
    _scanCanvas = null;
    _scanCtx = null;
    _barcodeDetector = null;
    _zoomRect = null;
    _targetRect = null;
    _currentManualZoom = 1.0;

    // Stop html5-qrcode fallback
    if (html5QrCodeScanner) {
        html5QrCodeScanner.stop().then(() => {
            html5QrCodeScanner.clear();
            html5QrCodeScanner = null;
        }).catch(() => {
            html5QrCodeScanner = null;
        });
    }
}
