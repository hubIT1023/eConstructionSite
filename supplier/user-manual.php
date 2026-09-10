<?php require_once('header.php'); ?>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="font-weight: 800; color: #0f172a; margin: 0;">
                <i class="fa fa-book text-primary"></i> Supplier Users' Manual & Standard Operating Procedures (SOP)
                <small>POS Operations, Role-Based Discount Approvals, Pricing Logic & Category Setup</small>
            </h1>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="../user-manual.html" target="_blank" class="btn btn-default btn-sm" style="font-weight: 700;">
                <i class="fa fa-external-link"></i> Full Interactive Manual
            </a>
            <button type="button" class="btn btn-default btn-sm" onclick="window.print()" style="font-weight: 700;">
                <i class="fa fa-print"></i> Print Guide
            </button>
            <a href="discount-approvals.php" class="btn btn-info btn-sm" style="font-weight: 700;">
                <i class="fa fa-shield"></i> Discount Approvals
            </a>
            <a href="pos.php" class="btn btn-primary btn-sm" style="font-weight: 700;">
                <i class="fa fa-calculator"></i> Open POS Terminal
            </a>
        </div>
    </div>
</section>

<style>
.manual-box {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 24px;
    overflow: hidden;
}
.manual-header {
    background: #f8fafc;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.manual-title {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}
.manual-body {
    padding: 22px;
}
.step-card {
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    background: #ffffff;
    transition: all 0.2s ease-in-out;
}
.step-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);
}
.step-num-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #2563eb;
    color: #fff;
    font-weight: 800;
    font-size: 13px;
    margin-right: 8px;
}
.code-tag {
    background: #f1f5f9;
    color: #0f172a;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12.5px;
    font-weight: 700;
    border: 1px solid #cbd5e1;
}
.formula-box {
    background: #eff6ff;
    border: 1.5px dashed #93c5fd;
    border-radius: 6px;
    padding: 12px 16px;
    margin: 10px 0;
    color: #1e3a8a;
    font-weight: 600;
    font-size: 13.5px;
}
.table-manual th {
    background: #f8fafc;
    font-weight: 700;
    color: #334155;
    font-size: 12.5px;
    text-transform: uppercase;
}
.workflow-diagram {
    background: #0f172a;
    color: #f8fafc;
    border-radius: 8px;
    padding: 16px;
    font-family: 'JetBrains Mono', monospace, Courier;
    font-size: 12.5px;
    line-height: 1.6;
    overflow-x: auto;
    margin: 12px 0;
}
.badge-role {
    display: inline-block;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 4px;
    text-transform: uppercase;
}
.badge-role-admin { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.badge-role-manager { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
.badge-role-supervisor { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-role-cashier { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
.badge-role-operator { background: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; }

@media print {
    .main-header, .main-sidebar, .content-header-right, .btn { display: none !important; }
    .content-wrapper { margin: 0 !important; padding: 0 !important; }
    .manual-box { border: 1px solid #ccc !important; box-shadow: none !important; page-break-inside: avoid; }
}
</style>

<section class="content">

    <!-- Quick Navigation Anchor Bar -->
    <div style="margin-bottom: 20px; display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="#pos-guide" class="btn btn-default btn-sm" style="font-weight: 700;"><i class="fa fa-calculator text-primary"></i> 1. POS Terminal Guide</a>
        <a href="#discount-hierarchy" class="btn btn-default btn-sm" style="font-weight: 700;"><i class="fa fa-shield text-info"></i> 2. Role-Based Discount Approval Hierarchy</a>
        <a href="#product-guide" class="btn btn-default btn-sm" style="font-weight: 700;"><i class="fa fa-cubes text-success"></i> 3. Product Add & Category Configuration</a>
        <a href="#pricing-rollover" class="btn btn-default btn-sm" style="font-weight: 700;"><i class="fa fa-tag text-warning"></i> 4. Pricing, Mark-Up (₱) & Stock Rollover</a>
        <a href="#returns-guide" class="btn btn-default btn-sm" style="font-weight: 700;"><i class="fa fa-undo text-danger"></i> 5. Return Items & Customer Refunds</a>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 1: POS TERMINAL USER MANUAL                     -->
    <!-- ======================================================= -->
    <div id="pos-guide" class="manual-box">
        <div class="manual-header" style="background: #eff6ff; border-color: #bfdbfe;">
            <h3 class="manual-title" style="color: #1e40af;">
                <i class="fa fa-calculator text-primary"></i> 1. Supplier POS (Point of Sale) Operations Guide
            </h3>
            <span class="label label-primary" style="font-size: 11px; padding: 4px 8px;">POS User / Cashier</span>
        </div>
        <div class="manual-body">
            
            <p style="font-size: 14.5px; color: #334155; margin-bottom: 16px;">
                The Supplier POS Terminal (<a href="pos.php"><code>supplier/pos.php</code></a>) is designed for high-speed counter sales, multi-size product selection, manual special orders, barangay location delivery, amount-based item discounts, cash tendering, and direct return handling.
            </p>

            <div class="row">
                
                <div class="col-md-6">
                    <div class="step-card">
                        <h4 style="margin: 0 0 8px 0; font-weight: 800; color: #0f172a;">
                            <span class="step-num-badge">1</span> Browse & Filter Products
                        </h4>
                        <p style="font-size: 13px; color: #475569;">
                            • <strong>Search Bar</strong>: Instant search by product name, specification, dimension, brand, or SKU.<br>
                            • <strong>Category Tabs</strong>: Click top pills (e.g. <em>Nails, Tubular Steel, Angle Bars, GI Pipes, Plywood</em>) to filter the catalog instantly.<br>
                            • <strong>Master Product Cards</strong>: Products are grouped into Master Cards (e.g., <em>Common Nails, Angle Bars</em>) with clear price ranges and total stock.
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="step-card">
                        <h4 style="margin: 0 0 8px 0; font-weight: 800; color: #0f172a;">
                            <span class="step-num-badge">2</span> Multi-Size & Variant Selection
                        </h4>
                        <p style="font-size: 13px; color: #475569;">
                            • Click any master card to open the <strong>Specification & Size Modal</strong>.<br>
                            • Click the quick <strong>Variant Chips</strong> (e.g., <code class="code-tag">1" (1/4kg)</code>, <code class="code-tag">2" (1kg)</code>, <code class="code-tag">2" x 4" (t=1.5)</code>).<br>
                            • The modal dynamically updates the unit price, SKU, live stock count, and subtotal. Set quantity and click <strong>"Add to Current Sale"</strong>.
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="step-card">
                        <h4 style="margin: 0 0 8px 0; font-weight: 800; color: #0f172a;">
                            <span class="step-num-badge">3</span> Special Orders (Manual Items)
                        </h4>
                        <p style="font-size: 13px; color: #475569;">
                            • For items not in the catalog (e.g., custom fabrication or special orders), click the orange <strong>"+ Special Order"</strong> button.<br>
                            • Enter Product Name, Specifications/Dimensions, Unit Price, and Quantity.<br>
                            • Click <strong>"Add to Cart"</strong>. Special Orders do <em>not</em> deduct from standard inventory.
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="step-card">
                        <h4 style="margin: 0 0 8px 0; font-weight: 800; color: #0f172a;">
                            <span class="step-num-badge">4</span> Customer Type & Location Delivery
                        </h4>
                        <p style="font-size: 13px; color: #475569;">
                            • <strong>Walk-in (OTC)</strong>: Enter optional customer name and phone.<br>
                            • <strong>Registered User</strong>: Select a registered contractor/customer from the dropdown.<br>
                            • <strong>Location Delivery</strong>: Check the <em>Location</em> box to select a Barangay and automatically apply delivery fees.
                        </p>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="step-card" style="background: #f0fdf4; border-color: #bbf7d0;">
                        <h4 style="margin: 0 0 8px 0; font-weight: 800; color: #166534;">
                            <span class="step-num-badge" style="background: #16a34a;">5</span> Tendering Cash & Printing Receipts
                        </h4>
                        <p style="font-size: 13.5px; color: #166534;">
                            1. In the right panel, enter the <strong>Amount Tendered</strong> (or click preset buttons: <em>₱100, ₱500, ₱1,000, ₱5,000, Exact</em>).<br>
                            2. The system instantly calculates and displays <strong>Change Due</strong> in green.<br>
                            3. Click <strong>"Complete Sale & Print Receipt"</strong>. The transaction is recorded in <code class="code-tag">tbl_payment</code> and <code class="code-tag">tbl_order</code>, shelf stock is deducted, and a clean thermal receipt popups for printing.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- ======================================================= -->
    <!-- ======================================================= -->
    <!-- SECTION 2: ROLE-BASED DISCOUNT APPROVAL HIERARCHY       -->
    <!-- ======================================================= -->
    <div id="discount-hierarchy" class="manual-box">
        <div class="manual-header" style="background: #f0fdfa; border-color: #99f6e4;">
            <h3 class="manual-title" style="color: #0f766e;">
                <i class="fa fa-shield text-info"></i> 2. POS Item Discount Workflow & Role-Based Approval Hierarchy
            </h3>
            <span class="label label-info" style="font-size: 11px; padding: 4px 8px;">Security, Payment Locking & Approval SOP</span>
        </div>
        <div class="manual-body">
            
            <p style="font-size: 14.5px; color: #334155; margin-bottom: 16px;">
                To safeguard store profit margins, prevent cashier fraud, and maintain strict multi-tenant auditability, all item-level discounts requested at the POS terminal (<a href="pos.php"><code>supplier/pos.php</code></a>) are governed by a multi-tier <strong>Role-Based Approval Hierarchy</strong> with <strong>real-time payment locking</strong>.
            </p>

            <!-- 2.1 COMPLETE END-TO-END WORKFLOW LIFECYCLE -->
            <div class="step-card" style="border-left: 4px solid #0d9488;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 12px; gap: 8px;">
                    <h4 style="margin: 0; font-weight: 800; color: #0f766e;">
                        <i class="fa fa-refresh"></i> 2.1 Complete End-to-End Item Discount &amp; Checkout Lifecycle Diagram
                    </h4>
                    <div style="display: flex; gap: 6px;">
                        <a href="../assets/uploads/pos-discount-lifecycle.svg" target="_blank" class="btn btn-default btn-xs" style="font-weight: 700;">
                            <i class="fa fa-external-link"></i> Open Full HD Diagram
                        </a>
                        <a href="../assets/uploads/pos-discount-lifecycle.svg" download="POS-Item-Discount-Lifecycle.svg" class="btn btn-info btn-xs" style="font-weight: 700;">
                            <i class="fa fa-download"></i> Download Vector SVG
                        </a>
                    </div>
                </div>
                
                <p style="font-size: 13.5px; color: #475569; margin-bottom: 14px;">
                    The following high-resolution diagram illustrates the strict lifecycle of an item discount from initial cashier request in ₱ to final payment completion:
                </p>

                <!-- High-Resolution Rendered Vector Diagram -->
                <div style="background: #0b0f19; border: 1.5px solid #1e293b; border-radius: 8px; padding: 12px; text-align: center; margin-bottom: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.15);">
                    <a href="../assets/uploads/pos-discount-lifecycle.svg" target="_blank" title="Click to view full size vector diagram">
                        <img src="../assets/uploads/pos-discount-lifecycle.svg" alt="POS Item Discount Request &amp; Payment Completion Lifecycle" style="width: 100%; max-width: 1100px; height: auto; border-radius: 6px; display: inline-block;">
                    </a>
                    <div style="margin-top: 8px; font-size: 12px; color: #94a3b8;">
                        <i class="fa fa-search-plus"></i> <em>Click diagram to view in high definition or zoom</em>
                    </div>
                </div>
            </div>

            <!-- 2.2 DETAILED OPERATIONAL WORKFLOW -->
            <div class="step-card" style="border-left: 4px solid #3b82f6;">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #1e40af;">
                    <i class="fa fa-list-ol"></i> 2.2 Step-by-Step Operational Workflow
                </h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 12px;">
                            <strong style="color: #2563eb;"><span class="step-num-badge" style="width: 22px; height: 22px; font-size: 11px;">1</span> Cashier Discount Request in ₱</strong>
                            <p style="font-size: 12.5px; color: #475569; margin: 6px 0 0 0;">
                                On any item in the Current Sale cart, click the <strong>"% Discount"</strong> button. Enter the requested discount in <strong>Philippine Pesos (₱)</strong> (e.g. <code>₱50.00</code>). The modal instantly calculates:
                                <br>• <strong>Original Gross:</strong> <code>₱250.00 × 2 = ₱500.00</code>
                                <br>• <strong>Calculated %:</strong> <code>(₱50 ÷ ₱500) × 100 = 10.00%</code>
                                <br>• <strong>Net Line Total:</strong> <code>₱500.00 - ₱50.00 = ₱450.00</code>
                            </p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 12px;">
                            <strong style="color: #d97706;"><span class="step-num-badge" style="width: 22px; height: 22px; font-size: 11px; background: #d97706;">2</span> Automatic Policy Tier Validation</strong>
                            <p style="font-size: 12.5px; color: #475569; margin: 6px 0 0 0;">
                                The system validates the discount against store limits:
                                <br>• <strong>Normal Tier (&le; 10%):</strong> Status <code class="code-tag">PENDING</code>.
                                <br>• <strong>Special Tier (&gt; 10% &amp; &le; 20%):</strong> Status <code class="code-tag">ESCALATED</code> for Admin/Manager review.
                                <br>• <strong>Prohibited (&gt; 20% or &ge; Gross):</strong> Hard-blocked on screen and rejected by server API.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 14px; margin-bottom: 12px;">
                            <strong style="color: #b91c1c;"><span class="step-num-badge" style="width: 22px; height: 22px; font-size: 11px; background: #dc2626;">3</span> POS Payment Locking Interlock</strong>
                            <p style="font-size: 12.5px; color: #7f1d1d; margin: 6px 0 0 0;">
                                While any discount request is <span class="label label-warning">PENDING</span> or <span class="label label-danger">ESCALATED</span>:
                                <br>• The <strong>Amount Tendered</strong> field and <strong>"Complete Sale &amp; Print Receipt"</strong> button are <strong>disabled</strong>.
                                <br>• An amber notice banner (<code>#posPaymentLockedNotice</code>) alerts the cashier.
                                <br>• The cashier can wait for approval or click <strong>"Cancel Discount"</strong> on the line item to unlock immediately.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 14px; margin-bottom: 12px;">
                            <strong style="color: #166534;"><span class="step-num-badge" style="width: 22px; height: 22px; font-size: 11px; background: #16a34a;">4</span> Approver Processing &amp; Modification</strong>
                            <p style="font-size: 12.5px; color: #14532d; margin: 6px 0 0 0;">
                                Authorized approvers (Supervisor/Manager/Admin) review the request via:
                                <br>• <strong>In-POS Quick Modal:</strong> Click <em>"Approvals"</em> button with pending badge.
                                <br>• <strong>Dedicated Portal:</strong> Open <a href="discount-approvals.php"><code>discount-approvals.php</code></a>.
                                <br>• Approvers can <strong>Approve</strong>, <strong>Modify (in ₱)</strong>, or <strong>Reject</strong> with mandatory remarks.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px;">
                            <strong style="color: #1e40af;"><span class="step-num-badge" style="width: 22px; height: 22px; font-size: 11px; background: #2563eb;">5</span> Real-Time Polling &amp; Cart Update</strong>
                            <p style="font-size: 12.5px; color: #1e3a8a; margin: 6px 0 0 0;">
                                The POS background poller (<code>poll_status</code>) syncs every 3.5 seconds:
                                <br>• The item badge turns <strong>Green (Approved)</strong> or <strong>Blue (Modified)</strong>.
                                <br>• The original price is struck through, and net price is applied.
                                <br>• The payment section is automatically unlocked!
                            </p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 6px; padding: 14px;">
                            <strong style="color: #6b21a8;"><span class="step-num-badge" style="width: 22px; height: 22px; font-size: 11px; background: #7c3aed;">6</span> Checkout, Audit &amp; Receipt Finalization</strong>
                            <p style="font-size: 12.5px; color: #581c87; margin: 6px 0 0 0;">
                                Cashier enters Amount Tendered and clicks <strong>"Complete Sale &amp; Print Receipt"</strong>:
                                <br>• Server re-verifies discount status authoritatively.
                                <br>• Payment recorded in <code class="code-tag">tbl_payment</code>, stock decremented.
                                <br>• <code class="code-tag">tbl_discount_requests</code> linked with generated <code class="code-tag">payment_id</code>.
                                <br>• Thermal receipt printed with clear discount breakdown.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2.3 ROLE-BASED APPROVAL HIERARCHY MATRIX -->
            <div class="step-card" style="border-left: 4px solid #0d9488;">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #0f766e;">
                    <i class="fa fa-sitemap"></i> 2.3 Role-Based Approval Authority &amp; Anti-Fraud Matrix
                </h4>
                <p style="font-size: 13.5px; color: #475569;">
                    The required approval level strictly depends on the role of the requester. Self-approval and peer-approval are strictly regulated:
                </p>

                <div class="workflow-diagram">
┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 ROLE-BASED APPROVAL HIERARCHY MATRIX                                   │
├─────────────────────────┬───────────────────────────────────────┬──────────────────┬───────────────────┤
│ Requester Role          │ Required Approval Authority           │ Self-Approval?   │ Peer-Approval?    │
├─────────────────────────┼───────────────────────────────────────┼──────────────────┼───────────────────┤
│ 1. Admin / Manager      │ Admin OR Manager                      │ ALLOWED (Yes)*   │ ALLOWED (Yes)     │
│ 2. Supervisor           │ Admin OR Manager                      │ BLOCKED (No)     │ BLOCKED (No)      │
│ 3. Cashier / Operator   │ Supervisor OR Admin / Manager         │ BLOCKED (No)     │ BLOCKED (No)      │
│ 4. Order Processing     │ Supervisor OR Admin / Manager         │ BLOCKED (No)     │ BLOCKED (No)      │
│ 5. Encoder Staff        │ Supervisor OR Admin / Manager         │ BLOCKED (No)     │ BLOCKED (No)      │
└─────────────────────────┴───────────────────────────────────────┴──────────────────┴───────────────────┘
* Note: Admin / Manager self-approval is subject to configured store maximum policy limits.</div>

                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-4">
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 12px;">
                            <strong style="color: #1e40af;"><i class="fa fa-user-secret"></i> 1. Admin / Manager</strong>
                            <p style="font-size: 12.5px; color: #1e3a8a; margin: 6px 0 0 0;">
                                Authorized to <strong>self-approve</strong> their own discount requests or approve any staff discount within policy limits. Can approve both Normal and Escalated Special discounts and modify discount amounts in ₱.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 6px; padding: 12px;">
                            <strong style="color: #854d0e;"><i class="fa fa-user-tie"></i> 2. Supervisor</strong>
                            <p style="font-size: 12.5px; color: #713f12; margin: 6px 0 0 0;">
                                Authorized to approve Normal discounts (&le; 10%) requested by cashiers. <strong>Cannot self-approve</strong> their own requests, and cannot approve peer supervisor requests (must be escalated to Admin/Manager).
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px;">
                            <strong style="color: #334155;"><i class="fa fa-user"></i> 3. Cashier / Operator</strong>
                            <p style="font-size: 12.5px; color: #475569; margin: 6px 0 0 0;">
                                Frontline POS operators enter discount requests in ₱. <strong>Cannot self-approve or peer-approve</strong>. Payment remains locked until an authorized Supervisor or Admin reviews and approves the request.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2.4 STORE LIMITS & THRESHOLD POLICIES -->
            <div class="step-card" style="border-left: 4px solid #f59e0b;">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #b45309;">
                    <i class="fa fa-sliders"></i> 2.4 Store Discount Limits &amp; Escalation Thresholds
                </h4>
                <p style="font-size: 13.5px; color: #475569;">
                    Discount threshold parameters are configured per supplier tenant in <a href="discount-settings.php"><code>supplier/discount-settings.php</code></a> and strictly enforced:
                </p>

                <table class="table table-bordered table-manual" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th width="200">Discount Tier</th>
                            <th width="180">Threshold Scope</th>
                            <th width="140">Initial Status</th>
                            <th>Approval Authority &amp; System Behavior</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong style="color: #0284c7;"><i class="fa fa-check-circle"></i> Normal Discount</strong></td>
                            <td>&le; Configured Normal Max (e.g. <code>10.00%</code>)</td>
                            <td><span class="label label-info">PENDING</span></td>
                            <td>Can be approved by immediate authorized <strong>Supervisor</strong> OR <strong>Admin / Manager</strong>. Payment locked until approved.</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #d97706;"><i class="fa fa-exclamation-triangle"></i> Special / Escalated</strong></td>
                            <td>&gt; Normal Max AND &le; Absolute Max (e.g. <code>10.01% - 20.00%</code>)</td>
                            <td><span class="label label-warning">ESCALATED</span></td>
                            <td>Marked with amber warning badge. Requires <strong>Admin / Manager</strong> review. Supervisors cannot approve.</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #dc2626;"><i class="fa fa-ban"></i> Prohibited Discount</strong></td>
                            <td>&gt; Absolute Max Limit (e.g. <code>&gt; 20.00%</code>) OR &ge; Gross Value</td>
                            <td><span class="label label-danger">HARD BLOCKED</span></td>
                            <td>Strictly rejected by POS modal interface and server API (<code class="code-tag">DISCOUNT_PROHIBITED</code>). Cannot be submitted.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 2.5 APPROVAL ACTIONS & CHANNELS -->
            <div class="step-card" style="border-left: 4px solid #10b981;">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #047857;">
                    <i class="fa fa-check-square"></i> 2.5 Dual Approval Channels &amp; Approver Actions
                </h4>
                <p style="font-size: 13.5px; color: #475569;">
                    Approvers can process requests via two seamless channels:
                    <strong>(A)</strong> In-POS Quick Modal Queue (<a href="pos.php"><code>pos.php</code></a>) via the top <em>"Approvals"</em> button with live pending count badge, or 
                    <strong>(B)</strong> Dedicated Discount Approvals Portal (<a href="discount-approvals.php"><code>discount-approvals.php</code></a>).
                </p>

                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-4">
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px;">
                            <strong style="color: #166534;"><i class="fa fa-check"></i> 1. Direct Approve</strong>
                            <p style="font-size: 12.5px; color: #166534; margin: 4px 0 0 0;">
                                Approves the requested Peso amount. The cashier's terminal updates within 3.5 seconds via real-time polling, applying the green discount badge and unlocking payment.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 12px;">
                            <strong style="color: #1d4ed8;"><i class="fa fa-edit"></i> 2. Modify Discount (₱)</strong>
                            <p style="font-size: 12.5px; color: #1e40af; margin: 4px 0 0 0;">
                                Approver inputs an adjusted Peso discount (e.g. counter-offer from ₱50 to ₱30). Marked as <code class="code-tag">APPROVED_MODIFIED</code> with recalculated net price.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 12px;">
                            <strong style="color: #991b1b;"><i class="fa fa-times"></i> 3. Reject Request</strong>
                            <p style="font-size: 12.5px; color: #991b1b; margin: 4px 0 0 0;">
                                Rejects discount with a required explanation reason. The POS line item reverts to standard price and unlocks checkout.
                            </p>
                        </div>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-top: 14px;">
                    <p style="font-size: 12.5px; color: #334155; margin: 0;">
                        <i class="fa fa-lock text-primary"></i> <strong>Audit Logging &amp; Compliance:</strong> Every discount action records <code class="code-tag">request_id</code>, <code class="code-tag">supplier_id</code>, <code class="code-tag">cashier_id</code>, <code class="code-tag">requester_role</code>, <code class="code-tag">original_unit_price</code>, <code class="code-tag">original_item_value</code>, <code class="code-tag">discount_amount</code>, <code class="code-tag">approved_discount_percent</code>, <code class="code-tag">final_item_value</code>, <code class="code-tag">approver_id</code>, <code class="code-tag">approver_role</code>, <code class="code-tag">payment_id</code>, and timestamps in <code class="code-tag">tbl_discount_requests</code>.
                    </p>
                </div>
            </div>

        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 3: PRODUCT ADD & EDIT BY CATEGORY               -->
    <!-- ======================================================= -->
    <div id="product-guide" class="manual-box">
        <div class="manual-header" style="background: #f0fdf4; border-color: #bbf7d0;">
            <h3 class="manual-title" style="color: #166534;">
                <i class="fa fa-cubes text-success"></i> 3. How to Add & Configure Products According to Category
            </h3>
            <span class="label label-success" style="font-size: 11px; padding: 4px 8px;">Supplier Admin</span>
        </div>
        <div class="manual-body">
            
            <p style="font-size: 14.5px; color: #334155; margin-bottom: 16px;">
                Follow these step-by-step instructions to create new products in <a href="product-add.php"><code>supplier/product-add.php</code></a> or update existing products in <a href="product-edit.php"><code>supplier/product-edit.php</code></a> so they are categorized correctly and display seamlessly across the e-commerce store and POS terminal.
            </p>

            <!-- STEP 1 -->
            <div class="step-card">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #0f172a;">
                    <span class="step-num-badge">Step 1</span> Select the 3-Level Category Hierarchy
                </h4>
                <p style="font-size: 13.5px; color: #475569;">
                    Every construction product belongs to a strict 3-tier classification system:
                </p>
                <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 16px; margin: 10px 0;">
                    <ol style="margin-left: 20px; font-size: 13px; color: #334155; line-height: 1.8;">
                        <li><strong>Top Level Category</strong>: Select the master department (e.g. <code>Building Materials</code>, <code>Infrastructure & Utilities</code>, or <code>Tools & Safety</code>).</li>
                        <li><strong>Mid Level Category</strong>: The mid dropdown will populate automatically (e.g. <code>Steel & Metal</code>, <code>Concrete & Cement</code>, <code>Roofing & Wall</code>, <code>Plumbing & Pipes</code>, <code>Power Tools</code>).</li>
                        <li><strong>End Level Category</strong>: Select the specific terminal product category (e.g. <code>Nails</code>, <code>Tubular Steel</code>, <code>Angle Bars</code>, <code>C-Purlins</code>, <code>GI Pipes</code>, <code>Rebar & Mesh</code>, <code>Plywood, Phenolic & Hardiflex</code>, <code>Paints</code>).</li>
                        <li><em>Need a new category?</em> Click <strong>"+ Add New Category (Not Found in List)"</strong> to create and link a brand-new End Category on the fly without leaving the page.</li>
                    </ol>
                </div>
            </div>

            <!-- STEP 2 -->
            <div class="step-card">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #0f172a;">
                    <span class="step-num-badge">Step 2</span> Follow the Standard Naming Convention for POS Multi-Size Grouping
                </h4>
                <p style="font-size: 13.5px; color: #475569;">
                    To allow the POS terminal to automatically group products into single cards with multi-size chips, enter product names using the standard format:
                </p>
                
                <table class="table table-bordered table-striped table-manual" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Master Family Name</th>
                            <th>Recommended Naming Format</th>
                            <th>Sizes / Variants Extracted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="label label-primary">Nails</span></td>
                            <td>Common Nails / Finishing Nails</td>
                            <td><code>Common Nail 2" (1kg)</code>, <code>Finishing Nail 1 1/2" (1/2kg)</code></td>
                            <td>Length: <code>1"</code> to <code>6"</code>, Weight: <code>1/4kg, 1/2kg, 1kg</code></td>
                        </tr>
                        <tr>
                            <td><span class="label label-primary">Tubular Steel</span></td>
                            <td>Tubular Steel</td>
                            <td><code>Tubular Steel - 2" x 4" (t = 1.5)</code>, <code>Tubular Steel - 1" x 1" (t = 1.2) Stainless</code></td>
                            <td>Dimension: <code>1"x1"</code> to <code>4"x4"</code>, Thickness: <code>t=1.2, 1.5</code>, Finish</td>
                        </tr>
                        <tr>
                            <td><span class="label label-primary">Angle Bars</span></td>
                            <td>Angle Bars</td>
                            <td><code>Angle Bar - 1 1/2" x 1 1/2" (t = 3/16") Green</code></td>
                            <td>Dimension: <code>1"x1"</code> to <code>2"x2"</code>, Thickness: <code>1/8", 3/16", 1/4"</code>, Color</td>
                        </tr>
                        <tr>
                            <td><span class="label label-primary">C-Purlins</span></td>
                            <td>C-Purlins</td>
                            <td><code>C-Purlins 2" x 4" (t = 1.2)</code></td>
                            <td>Profile: <code>2"x3", 2"x4", 2"x6"</code>, Gauge: <code>t=1.0, 1.2, 1.5</code></td>
                        </tr>
                        <tr>
                            <td><span class="label label-primary">GI Pipes</span></td>
                            <td>GI Pipes (Schedule 40)</td>
                            <td><code>GI Pipe 2" #40 (Schedule 40)</code></td>
                            <td>Diameter: <code>1/2"</code> to <code>4" #40</code></td>
                        </tr>
                        <tr>
                            <td><span class="label label-primary">Plywood & Boards</span></td>
                            <td>Marine Plywood / Phenolic / Hardiflex</td>
                            <td><code>Marine Plywood 1/2" (Local)</code>, <code>Hardiflex Board 4.5mm</code></td>
                            <td>Thickness: <code>1/4", 1/2", 3/4", 3.5mm, 4.5mm</code>, Origin</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- STEP 3 -->
            <div class="step-card">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #0f172a;">
                    <span class="step-num-badge">Step 3</span> Select Sizes & Dimensions (<code class="code-tag">tbl_size</code>)
                </h4>
                <p style="font-size: 13.5px; color: #475569;">
                    • Under the <strong>"Select Size"</strong> multi-select dropdown, select all applicable dimensions (e.g. <code class="code-tag">2" x 4" (t = 1.5)</code>, <code class="code-tag">1/2" #40</code>, <code class="code-tag">1/4 kg</code>, <code class="code-tag">16 mm</code>).<br>
                    • Type in the dropdown to quickly search through pre-registered construction dimensions.<br>
                    • If the product comes in colors/coatings, select the color in the <strong>"Select Color"</strong> dropdown.
                </p>
            </div>

        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 4: PRICING, MARKUP & INVENTORY ROLLOVER         -->
    <!-- ======================================================= -->
    <div id="pricing-rollover" class="manual-box">
        <div class="manual-header" style="background: #fefce8; border-color: #fef08a;">
            <h3 class="manual-title" style="color: #854d0e;">
                <i class="fa fa-tag text-warning"></i> 4. Price Rule, Stock Alerts &amp; Quantity Adjustment Logic
            </h3>
            <span class="label label-warning" style="font-size: 11px; padding: 4px 8px;">Pricing &amp; Inventory Engine</span>
        </div>
        <div class="manual-body">
            
            <p style="font-size: 14.5px; color: #334155; margin-bottom: 16px;">
                The platform implements an automated, mathematically verified price rule, replenishment addition, and multi-priority stock alert engine to protect profit margins and automate inventory maintenance.
            </p>

            <!-- 4.1 LEGEND & FIELD DEFINITIONS -->
            <div class="step-card" style="border-left: 4px solid #eab308;">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #854d0e;">
                    <i class="fa fa-list-alt"></i> 4.1 Legend &amp; Field Definitions
                </h4>
                
                <table class="table table-bordered table-manual" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th width="40">#</th>
                            <th width="180">Field</th>
                            <th width="90">Symbol</th>
                            <th width="130">Database Field</th>
                            <th>Definition &amp; Operational Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><strong>Capital Price</strong></td>
                            <td><code class="code-tag" style="background: #f8fafc; color: #0f172a;">Ca</code></td>
                            <td><code>p_capital_price</code></td>
                            <td>Original/base capital cost of the product per unit in Pesos (₱).</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td><strong>Mark-Up</strong></td>
                            <td><code class="code-tag" style="background: #eff6ff; color: #1d4ed8;">₱</code></td>
                            <td><code>p_markup</code></td>
                            <td>Fixed peso amount added to the Capital Price (monetary amount, <strong>NOT a percentage</strong>).</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td><strong>New Price</strong></td>
                            <td><code class="code-tag" style="background: #f0fdf4; color: #166534;">N</code></td>
                            <td><code>p_new_price</code></td>
                            <td>New selling price calculated as <code>N = Ca + ₱ Mark-Up</code> or manually edited by the user.</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><strong>Current Price</strong></td>
                            <td><code class="code-tag" style="background: #eff6ff; color: #1e40af;">C</code></td>
                            <td><code>p_current_price</code></td>
                            <td>Current selling price after applying the Price Rule.</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td><strong>Safety Stock Level</strong></td>
                            <td><code class="code-tag" style="background: #fdf4ff; color: #6b21a8;">S</code></td>
                            <td><code>p_s_level</code></td>
                            <td>Threshold buffer for stock level alerts (default: <code>10</code>).</td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td><strong>New Quantity</strong></td>
                            <td><code class="code-tag" style="background: #f8fafc; color: #334155;">NQ</code></td>
                            <td><code>p_new_qty</code></td>
                            <td>Incoming replenishment quantity staged in reserve.</td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td><strong>Quantity in Stock (Current)</strong></td>
                            <td><code class="code-tag" style="background: #ecfdf5; color: #047857;">Q</code></td>
                            <td><code>p_qty</code></td>
                            <td>Active sellable stock currently on hand.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 4.2 PRICE RULE EXECUTION ORDER -->
            <div class="step-card" style="border-left: 4px solid #3b82f6;">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #1e40af;">
                    <i class="fa fa-balance-scale"></i> 4.2 Price Rule Execution Order
                </h4>
                <p style="font-size: 13.5px; color: #475569;">
                    Price rules are evaluated strictly in sequential order before replenishment additions take effect:
                </p>

                <div class="table-responsive" style="margin-top: 12px;">
                    <table class="table table-bordered table-manual">
                        <thead>
                            <tr>
                                <th width="100">Priority</th>
                                <th width="220">Mathematical Condition</th>
                                <th width="240">Resulting Price</th>
                                <th>Operational Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="label label-primary">Rule 1</span></td>
                                <td><code>Q &lt; 6 AND NQ &gt; 5</code></td>
                                <td><strong style="color: #166534;">C = N</strong></td>
                                <td><strong>Low Stock Replenishment:</strong> When existing stock is low and a substantial replenishment arrives, the selling price updates to the New Price.</td>
                            </tr>
                            <tr>
                                <td><span class="label label-info">Rule 2</span></td>
                                <td><code>C &gt; N AND Q &gt; 5</code></td>
                                <td><strong style="color: #1e40af;">C = C (Unchanged)</strong></td>
                                <td><strong>Price Protection:</strong> When current price is higher than new price and sufficient stock exists, the price is protected from decreases.</td>
                            </tr>
                            <tr>
                                <td><span class="label label-default">Rule 3</span></td>
                                <td><em>Else (all other cases)</em></td>
                                <td><strong style="color: #334155;">C = C (Unchanged)</strong></td>
                                <td><strong>Default Selling Price:</strong> Current price remains unchanged.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 4.3 QUANTITY ADDITION RULE -->
            <div class="step-card" style="border-left: 4px solid #10b981;">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #047857;">
                    <i class="fa fa-cubes"></i> 4.3 Quantity Addition Rule
                </h4>
                
                <div class="formula-box" style="background: #f0fdf4; border-color: #86efac; color: #166534;">
                    <i class="fa fa-check-circle"></i> <strong>Addition Trigger Condition:</strong><br>
                    <code style="font-size: 14px; font-weight: 800; color: #15803d;">Q &lt; 2 AND NQ &gt; 1</code> &rarr; <code>Q = Q + NQ</code>, and <code>NQ = 0</code>
                </div>

                <p style="font-size: 13px; color: #475569; margin-top: 8px;">
                    • If <code class="code-tag">Q &ge; 2</code> or <code class="code-tag">NQ &le; 1</code>: Quantity transfer does <strong>NOT</strong> trigger (e.g. if Q = 3 and NQ = 10, Q remains 3 and NQ remains 10).<br>
                    • If condition is satisfied, the replenishment stock transfers to active inventory and NQ resets to <code>0</code>.
                </p>
            </div>

            <!-- 4.4 STOCK COLOR ALERTS -->
            <div class="step-card" style="border-left: 4px solid #8b5cf6;">
                <h4 style="margin: 0 0 10px 0; font-weight: 800; color: #6d28d9;">
                    <i class="fa fa-bell"></i> 4.4 Stock Color Alert Evaluation
                </h4>
                <p style="font-size: 13.5px; color: #475569;">
                    Stock alerts are evaluated on the <strong>final Quantity (Q)</strong> against the <strong>Safety Stock Level (S)</strong> with strict priority:
                </p>
                
                <table class="table table-bordered table-manual" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th width="100">Priority</th>
                            <th width="130">Alert Badge</th>
                            <th width="200">Condition</th>
                            <th>Description &amp; Action Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Priority 1</strong></td>
                            <td><span class="badge" style="background-color: #ef4444; color: #fff; padding: 4px 8px; font-weight: 700;"><i class="fa fa-exclamation-triangle"></i> RED (&lt;50%)</span></td>
                            <td><code>Q &lt; 0.50 &times; S</code></td>
                            <td><strong>Critical Alert:</strong> Stock is below 50% of safety level. Immediate reorder required.</td>
                        </tr>
                        <tr>
                            <td><strong>Priority 2</strong></td>
                            <td><span class="badge" style="background-color: #f97316; color: #fff; padding: 4px 8px; font-weight: 700;"><i class="fa fa-warning"></i> ORANGE</span></td>
                            <td><code>0.50 &times; S &le; Q &lt; 0.80 &times; S</code></td>
                            <td><strong>Warning Alert:</strong> Stock is between 50% and 80% of safety level. Replenishment advised.</td>
                        </tr>
                        <tr>
                            <td><strong>Priority 3</strong></td>
                            <td><span class="badge" style="background-color: #10b981; color: #fff; padding: 4px 8px; font-weight: 600;"><i class="fa fa-check"></i> NORMAL</span></td>
                            <td><code>Q &ge; 0.80 &times; S</code></td>
                            <td><strong>Normal:</strong> Stock level is 80% or greater of safety stock level.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 5: RETURN ITEMS & REFUNDS                       -->
    <!-- ======================================================= -->
    <div id="returns-guide" class="manual-box">
        <div class="manual-header" style="background: #fef2f2; border-color: #fecaca;">
            <h3 class="manual-title" style="color: #991b1b;">
                <i class="fa fa-undo text-danger"></i> 5. Processing Customer Returns & Refunds
            </h3>
            <span class="label label-danger" style="font-size: 11px; padding: 4px 8px;">Returns & Restock</span>
        </div>
        <div class="manual-body">
            
            <p style="font-size: 14.5px; color: #334155; margin-bottom: 16px;">
                When a customer brings back an item for return or refund:
            </p>

            <ul class="step-list" style="list-style: none; padding: 0;">
                <li style="margin-bottom: 12px;">
                    <div style="font-weight: 800; color: #0f172a; font-size: 14px;"><i class="fa fa-search text-danger"></i> 1. Search Original Transaction</div>
                    <div style="font-size: 13px; color: #475569;">In POS, click the red <strong>[ RETURN ]</strong> button and enter the Receipt/Invoice ID (e.g. <code>POS-20260905-XXXXX</code>), customer name, or phone number.</div>
                </li>
                <li style="margin-bottom: 12px;">
                    <div style="font-weight: 800; color: #0f172a; font-size: 14px;"><i class="fa fa-check-square text-danger"></i> 2. Select Item & Quantity</div>
                    <div style="font-size: 13px; color: #475569;">Select the line item to return. Enter the return quantity (cannot exceed remaining returnable purchased quantity).</div>
                </li>
                <li style="margin-bottom: 12px;">
                    <div style="font-weight: 800; color: #0f172a; font-size: 14px;"><i class="fa fa-boxes text-danger"></i> 3. Condition & Restock Logic</div>
                    <div style="font-size: 13px; color: #475569;">
                        • <strong>Resellable / Unopened</strong>: Automatically restores stock into <code class="code-tag">tbl_product.p_qty</code>.<br>
                        • <strong>Damaged / Defective</strong>: Records refund without increasing sellable shelf inventory.<br>
                        • <strong>Special Order</strong>: Bypasses catalog restock automatically.
                    </div>
                </li>
                <li style="margin-bottom: 12px;">
                    <div style="font-weight: 800; color: #0f172a; font-size: 14px;"><i class="fa fa-print text-danger"></i> 4. Print Return Slip & View History</div>
                    <div style="font-size: 13px; color: #475569;">System generates official Return Reference (e.g. <code>RET-20260905-XXXX</code>) and prints the official Return Slip. All return records are logged in <a href="returns.php"><code>supplier/returns.php</code></a> and deducted in <a href="sales-report.php"><code>supplier/sales-report.php</code></a>.</div>
                </li>
            </ul>

        </div>
    
    <!-- POS ORDER PLACEMENT -> PAYMENT -> COLLECTION WORKFLOW -->
    <div id="pos-order-placement-workflow" class="manual-box">
        <div class="manual-header" style="background: #f0fdf4; border-color: #bbf7d0;">
            <h3 class="manual-title" style="color: #166534;">
                <i class="fa fa-clipboard-check text-success"></i> POS Order Placement &rarr; Payment &rarr; Customer Collection Workflow
            </h3>
            <span class="label label-success">Standard SOP</span>
        </div>
        <div class="manual-body">
            <p style="font-size: 14px; color: #334155; line-height: 1.7; margin-bottom: 16px;">
                Standard operating procedure for the 4-stage counter sales and yard collection lifecycle:
            </p>

            <!-- Visual Infographic Diagram -->
            <div style="background: #0f172a; border: 2px solid #1e293b; border-radius: 12px; padding: 18px; text-align: center; margin: 15px 0 24px 0;">
                <a href="../assets/uploads/pos_order_workflow_diagram.jpg" target="_blank" title="Click to view full size diagram">
                    <img src="../assets/uploads/pos_order_workflow_diagram.jpg" alt="POS Order Placement Workflow Diagram" class="img-responsive" style="max-width: 1000px; margin: 0 auto; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.3);">
                </a>
                <div style="margin-top: 8px; font-size: 12px; color: #94a3b8;">
                    <i class="fa fa-search-plus text-info"></i> <em>Click diagram to view full high-resolution graphic</em>
                </div>
            </div>

            <!-- Steps Grid -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="step-card" style="border-left: 4px solid #2563eb; background: #f8fafc;">
                        <span class="step-num-badge" style="background: #2563eb;">1</span>
                        <strong style="color: #1e3a8a;">1. Order Preparation</strong>
                        <div style="font-size: 11px; color: #2563eb; font-weight: bold; margin: 4px 0;">Order Processing Staff</div>
                        <p style="font-size: 12px; color: #475569; margin-bottom: 6px;">
                            Staff selects customer, adds items, variants, and submits <strong>"Create PO"</strong>.
                        </p>
                        <span class="label label-warning">PENDING PAYMENT</span>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="step-card" style="border-left: 4px solid #ea580c; background: #f8fafc;">
                        <span class="step-num-badge" style="background: #ea580c;">2</span>
                        <strong style="color: #9a3412;">2. Cashier Review</strong>
                        <div style="font-size: 11px; color: #ea580c; font-weight: bold; margin: 4px 0;">Cashier</div>
                        <p style="font-size: 12px; color: #475569; margin-bottom: 6px;">
                            Cashier opens Pending PO queue and verifies line items and totals with customer.
                        </p>
                        <span class="label label-info">CUSTOMER CONFIRMED</span>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="step-card" style="border-left: 4px solid #16a34a; background: #f8fafc;">
                        <span class="step-num-badge" style="background: #16a34a;">3</span>
                        <strong style="color: #14532d;">3. Payment ("PAID")</strong>
                        <div style="font-size: 11px; color: #16a34a; font-weight: bold; margin: 4px 0;">Cashier</div>
                        <p style="font-size: 12px; color: #475569; margin-bottom: 6px;">
                            Customer pays. Cashier clicks <strong>"PAID"</strong> and prints thermal tax receipt.
                        </p>
                        <span class="label label-success">PAID / READY</span>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="step-card" style="border-left: 4px solid #9333ea; background: #f8fafc;">
                        <span class="step-num-badge" style="background: #9333ea;">4</span>
                        <strong style="color: #6b21a8;">4. Collection</strong>
                        <div style="font-size: 11px; color: #9333ea; font-weight: bold; margin: 4px 0;">Yard / Counter Staff</div>
                        <p style="font-size: 12px; color: #475569; margin-bottom: 6px;">
                            Customer collects items. Staff clicks <strong>"Mark as Collected"</strong>.
                        </p>
                        <span class="label label-primary">COLLECTED / DONE</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- POS ORDER PROCESSING -> PURCHASE ORDER -> CASHIER PAYMENT PROCEDURE -->
    <div id="pos-po-cashier-procedure" class="manual-box">
        <div class="manual-header" style="background: #f0fdfa; border-color: #99f6e4;">
            <h3 class="manual-title" style="color: #0f766e;">
                <i class="fa fa-file-invoice-dollar text-teal"></i> POS Order Processing &rarr; Purchase Order &rarr; Cashier Payment Procedure
            </h3>
            <span class="label label-info" style="background: #0d9488;">SOP Guide</span>
        </div>
        <div class="manual-body">
            <p style="font-size: 14px; color: #334155; line-height: 1.7; margin-bottom: 16px;">
                5-Stage operational procedure from product selection in POS to cashier payment acceptance and yard collection:
            </p>

            <!-- Visual Infographic Diagram -->
            <div style="background: #0f172a; border: 2px solid #1e293b; border-radius: 12px; padding: 18px; text-align: center; margin: 15px 0 24px 0;">
                <a href="../assets/uploads/pos_po_cashier_workflow_diagram.jpg" target="_blank" title="Click to view full size diagram">
                    <img src="../assets/uploads/pos_po_cashier_workflow_diagram.jpg" alt="POS Order Processing to PO to Cashier Payment Diagram" class="img-responsive" style="max-width: 1000px; margin: 0 auto; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.3);">
                </a>
                <div style="margin-top: 8px; font-size: 12px; color: #94a3b8;">
                    <i class="fa fa-search-plus text-teal"></i> <em>Click diagram to view full high-resolution 5-stage flowchart</em>
                </div>
            </div>

            <!-- Steps Grid -->
            <div class="row">
                <div class="col-md-2 col-sm-4">
                    <div class="step-card" style="border-left: 4px solid #2563eb; background: #f8fafc; padding: 12px;">
                        <span class="step-num-badge" style="background: #2563eb;">1</span>
                        <strong style="color: #1e3a8a; font-size: 13px;">1. Order Prep</strong>
                        <p style="font-size: 11.5px; color: #475569; margin: 6px 0 0 0;">
                            Select products &amp; variants into Current Sale.
                        </p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-4">
                    <div class="step-card" style="border-left: 4px solid #0d9488; background: #f8fafc; padding: 12px;">
                        <span class="step-num-badge" style="background: #0d9488;">2</span>
                        <strong style="color: #0f766e; font-size: 13px;">2. Checkout &amp; Edit</strong>
                        <p style="font-size: 11.5px; color: #475569; margin: 6px 0 0 0;">
                            Review &amp; edit items. Click <strong>[SEND PO]</strong>.
                        </p>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="step-card" style="border-left: 4px solid #d97706; background: #f8fafc; padding: 12px;">
                        <span class="step-num-badge" style="background: #d97706;">3</span>
                        <strong style="color: #92400e; font-size: 13px;">3. PO Sent</strong>
                        <p style="font-size: 11.5px; color: #475569; margin: 6px 0 0 0;">
                            Unpaid PO receipt + arrives in Cashier queue.
                        </p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="step-card" style="border-left: 4px solid #16a34a; background: #f8fafc; padding: 12px;">
                        <span class="step-num-badge" style="background: #16a34a;">4</span>
                        <strong style="color: #14532d; font-size: 13px;">4. Cashier &amp; [PAID]</strong>
                        <p style="font-size: 11.5px; color: #475569; margin: 6px 0 0 0;">
                            Cashier takes payment, clicks <strong>[PAID]</strong>, prints Tax Invoice.
                        </p>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="step-card" style="border-left: 4px solid #9333ea; background: #f8fafc; padding: 12px;">
                        <span class="step-num-badge" style="background: #9333ea;">5</span>
                        <strong style="color: #6b21a8; font-size: 13px;">5. Collection</strong>
                        <p style="font-size: 11.5px; color: #475569; margin: 6px 0 0 0;">
                            Customer collects items in yard &rarr; Completed.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</section>

<?php require_once('footer.php'); ?>
