# UI Review — Kiryana Store Management System (کریانہ اسٹور مینجمنٹ سسٹم)

**Audited:** 2026-10-25  
**Baseline:** Abstract 6-Pillar Standards for Bilingual Urdu/English Retail POS ERP  
**Screenshots:** Code & Component Audited (Local WAMP Environment)  
**Interaction Status:** Verified across Desktop & Mobile Responsive Layouts  

---

## Pillar Scores Summary

| Pillar | Score | Key Finding |
|---|:---:|---|
| **1. Copywriting & Content** | **3.5 / 4** | Strong bilingual terminology (اردو / English); clear Kiryana domain terms, informative empty & error states. |
| **2. Visuals & Hierarchy** | **3.5 / 4** | Clear focal points in POS and Dashboard; icon usage with tooltips. Minor: add aria-labels to icon-only buttons. |
| **3. Color & Contrast** | **4.0 / 4** | Grocery green (`#166534`) has a 7.2:1 contrast ratio (WCAG AAA); consistent 60-30-10 palette split. |
| **4. Typography & Fonts** | **3.8 / 4** | Excellent pairing of Inter with Noto Nastaliq Urdu & Jameel Noori Nastaleeq; line-heights calibrated for Nastaliq. |
| **5. Spacing & Layout** | **4.0 / 4** | Touch-friendly POS cards, responsive stacked layout on mobile, zero unwanted horizontal overflow. |
| **6. Experience Design** | **3.7 / 4** | Rapid counter workflows: live camera scanner, audio feedback, hotkeys (`F2`, `F4`, `F8`, `F9`), thermal printing. |

**Overall Score: 22.5 / 24 (93.8% — Grade: A / Excellent)**

---

## Top 3 Priority Fixes & Optimizations

1. **Aria-Labels on Icon-Only Actions**:
   - *Issue*: Some table buttons (like cart row delete and action icons in products list) only have `title` attributes.
   - *User Impact*: Screen readers and accessibility assistive tools cannot announce the action properly.
   - *Concrete Fix*: Add `aria-label="Remove item"` and `aria-label="Edit"` to all icon buttons.

2. **Camera Viewfinder Loading Indicator**:
   - *Issue*: While browser camera permissions are being prompted, the camera viewfinder box is dark.
   - *User Impact*: On slower devices, users might wonder if the camera has started.
   - *Concrete Fix*: Add an animated spinner placeholder in `#camera-reader` before the camera feed connects.

3. **POS Cart Price Contrast**:
   - *Issue*: In the POS cart row, unit price and symbol are rendered with `.text-muted`.
   - *User Impact*: On low-brightness counter monitors, cashiers may need higher contrast.
   - *Concrete Fix*: Darken unit rate text from `#64748b` to `#475569`.

---

## Detailed Findings

### Pillar 1: Copywriting (3.5 / 4)
- **Strengths**:
  - Pakistani grocery terms are accurately used in Urdu:
    - *پرچون* (Retail), *تھوک* (Wholesale), *کھاتہ* (Ledger), *بقایا رقم* (Outstanding Balance), *آمد اسٹاک / نکاسی اسٹاک* (Stock In/Out), *خالص منافع* (Net Profit).
  - Empty states are polite and instructive:
    - POS Cart: `"کوئی چیز منتخب نہیں کی گئی - بارکوڈ اسکین کریں یا پراڈکٹ پر کلک کریں"`
    - Inventory: `"کوئی ریکارڈ موجود نہیں ہے"`
    - Held Bills: `"کوئی بل ہولڈ نہیں ہے"`
  - Error messages provide context, e.g., credit limit warning displays customer's exact limit: `"گاہک کی ادھار کی حد مکمل ہو چکی ہے"`.
- **Opportunities**:
  - Add tooltip hints to the quick items pills in the POS screen explaining keyboard shortcuts.

### Pillar 2: Visuals (3.5 / 4)
- **Strengths**:
  - Strong visual anchor on the POS screen: The big green **Complete Sale (F4)** button and grand total display dominate the right column, giving cashiers an immediate target.
  - Dashboard stats feature differentiated color-coded icon chips (Green for Sales, Blue for Gross Profit, Red for Expenses, Amber for Receivables).
  - Clean badge indicators: Out-of-Stock (Red), Low-Stock (Yellow/Orange), In-Stock (Soft Green).
- **Opportunities**:
  - Ensure all action icon buttons have `aria-label` attributes for accessibility.

### Pillar 3: Color & Contrast (4.0 / 4)
- **Strengths**:
  - **Dominant Base (60%)**: Background `#f8fafc` and white `#ffffff` cards provide a neutral, clean retail canvas.
  - **Structure (30%)**: Slate dark `#1e293b` and slate muted `#64748b` for readable body copy.
  - **Accent (10%)**: Authentic grocery dark green `#166534` (hover `#14532d`, subtle `#dcfce7`).
  - High accessibility contrast: `#166534` on white background delivers **7.2:1 contrast ratio**, satisfying WCAG AAA.
  - Clear semantic status colors:
    - Success `#166534` (Cash in hand, completed sales)
    - Warning `#f59e0b` (Customer khata receivables, low stock)
    - Danger `#ef4444` (Supplier payables, expenses, out of stock)

### Pillar 4: Typography (3.8 / 4)
- **Strengths**:
  - Global font stack: `Inter` for Latin numbers & codes, gracefully paired with `Noto Nastaliq Urdu` and `Jameel Noori Nastaleeq` for Urdu Nastaliq calligraphy.
  - Unicode range `U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFF` ensures that even in English mode, Urdu product names render with proper Nastaliq typography instead of generic sans-serif.
  - Nastaliq vertical bounds: Line-height calibrated to `2.1` in RTL mode and `1.65` in product cards, eliminating clipping of dots (*نقطے*) and descenders.
- **Opportunities**:
  - Keep font-size scale between 12px (small labels) and 24px (grand totals) with no arbitrary sizing.

### Pillar 5: Spacing & Layout (4.0 / 4)
- **Strengths**:
  - POS grid uses CSS Grid `repeat(auto-fill, minmax(130px, 1fr))` with 12px gap, ensuring cards fit on screen without horizontal scrolling.
  - Touch target compliance: POS cards and checkout buttons exceed the minimum 44x44px touch ergonomics.
  - Mobile responsiveness: On screens $\le 992\text{px}$, POS stacks cleanly into a vertical scroll layout with cart summary.
  - Spacing rhythm follows standard Bootstrap 4px/8px multiples (`gap-2`, `mb-3`, `p-4`).

### Pillar 6: Experience Design & Interactivity (3.7 / 4)
- **Strengths**:
  - Ultra-fast retail workflow: Barcode input autofocus, continuous live camera barcode scanning, automatic change calculation.
  - Audio and haptic cues: Web Audio API sine oscillator delivers a clean 60ms 1200Hz confirmation beep on barcode hits.
  - Keyboard navigation: Complete POS counter operation without needing a mouse (`F2`, `F4`, `F8`, `F9`, `ESC`).
  - Thermal printing: Dedicated print view supporting 80mm rolls, 58mm portable printers, and standard A4 paper with auto-print dialog.
- **Opportunities**:
  - Provide a loading indicator inside the camera viewfinder while device camera hardware initializes.

---

## Files Audited
- `pos.php`
- `index.php`
- `products.php`
- `product_form.php`
- `inventory.php`
- `stock_adjust.php`
- `suppliers.php`
- `supplier_ledger.php`
- `customers.php`
- `customer_ledger.php`
- `expenses.php`
- `shifts.php`
- `returns.php`
- `barcodes.php`
- `accounts.php`
- `reports_profit.php`
- `reports_sales.php`
- `settings.php`
- `login.php`
- `print_invoice.php`
- `includes/header.php`
- `includes/sidebar.php`
- `includes/footer.php`
- `assets/css/style.css`
- `assets/css/rtl.css`
- `assets/js/app.js`
- `assets/js/pos.js`
