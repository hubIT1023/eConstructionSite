<?php
require_once('header.php');

// Page metadata & current page resolution
$cur_page = 'webpos.php';
?>

<style>
.webpos-landing-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #1e293b;
    background: #f8fafc;
}

/* Hero Section */
.webpos-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    color: #ffffff;
    padding: 70px 0 60px 0;
    position: relative;
    overflow: hidden;
    border-bottom: 3px solid #f59e0b;
}
.webpos-hero::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(circle at 80% 20%, rgba(245, 158, 11, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(37, 99, 235, 0.15) 0%, transparent 50%);
    pointer-events: none;
}
.webpos-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.4);
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 20px;
}
.webpos-hero-title {
    font-size: 38px;
    font-weight: 900;
    line-height: 1.2;
    margin: 0 0 16px 0;
    color: #ffffff;
    letter-spacing: -0.5px;
}
.webpos-hero-title span {
    color: #f59e0b;
    background: linear-gradient(90deg, #fbbf24, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.webpos-hero-sub {
    font-size: 16px;
    color: #cbd5e1;
    line-height: 1.6;
    margin-bottom: 30px;
    max-width: 600px;
}
.webpos-hero-ctas {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}
.btn-webpos-launch {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #0f172a !important;
    font-size: 16px;
    font-weight: 800;
    padding: 14px 28px;
    border-radius: 8px;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 10px 20px -5px rgba(245, 158, 11, 0.4);
    transition: all 0.25s ease;
    border: none;
}
.btn-webpos-launch:hover {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    transform: translateY(-2px);
    box-shadow: 0 14px 24px -5px rgba(245, 158, 11, 0.6);
    color: #000000 !important;
}
.btn-webpos-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: #f8fafc !important;
    font-size: 15px;
    font-weight: 700;
    padding: 13px 24px;
    border-radius: 8px;
    border: 1.5px solid rgba(255, 255, 255, 0.2);
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
}
.btn-webpos-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: #cbd5e1;
    color: #ffffff !important;
    transform: translateY(-2px);
}
.webpos-notice {
    font-size: 12.5px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Feature Grid */
.webpos-section {
    padding: 60px 0;
}
.webpos-section-title {
    text-align: center;
    margin-bottom: 45px;
}
.webpos-section-title h2 {
    font-size: 30px;
    font-weight: 900;
    color: #0f172a;
    margin: 0 0 10px 0;
}
.webpos-section-title p {
    font-size: 15px;
    color: #64748b;
    max-width: 650px;
    margin: 0 auto;
}
.webpos-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    transition: all 0.25s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.webpos-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 20px -3px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}
.webpos-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 20px;
}
.webpos-card h4 {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 10px 0;
}
.webpos-card p {
    font-size: 13.5px;
    color: #64748b;
    line-height: 1.55;
    margin-bottom: 0;
}

/* Workflow Step Box */
.workflow-step-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px;
    position: relative;
    text-align: center;
}
.workflow-number {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #0f172a;
    color: #f59e0b;
    font-weight: 900;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px auto;
    border: 2px solid #f59e0b;
}

/* Hardware Badges */
.hardware-badge {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px 18px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 13.5px;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

/* Call to Action Banner */
.webpos-cta-banner {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 16px;
    padding: 45px 35px;
    color: #ffffff;
    text-align: center;
    margin: 20px 0 60px 0;
    border: 2px solid #334155;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
}
</style>

<div class="webpos-landing-wrapper">

    <!-- ========================================================================= -->
    <!-- 1. HERO SECTION                                                           -->
    <!-- ========================================================================= -->
    <div class="webpos-hero">
        <div class="container">
            <div class="row" style="display: flex; align-items: center; flex-wrap: wrap;">
                <div class="col-md-7 col-sm-12">
                    <div class="webpos-badge">
                        <i class="fa fa-calculator text-warning"></i> Supplier Cloud WebPOS System
                    </div>
                    <h1 class="webpos-hero-title">
                        Intelligent WebPOS Built for <span>Construction & Hardware</span> Merchants
                    </h1>
                    <p class="webpos-hero-sub">
                        Fast counter checkout, multi-level construction variant selectors, live marketplace online order queueing, thermal receipting, and real-time capital-first margin reporting in one cloud terminal.
                    </p>
                    <div class="webpos-hero-ctas">
                        <a href="supplier/login.php" class="btn-webpos-launch">
                            <i class="fa fa-calculator"></i> Launch WebPOS Terminal
                        </a>
                        <a href="supplier-registration.php" class="btn-webpos-secondary">
                            <i class="fa fa-store"></i> Become a Supplier Partner
                        </a>
                    </div>
                    <div class="webpos-notice">
                        <i class="fa fa-lock text-warning"></i>
                        <span><strong>Restricted Portal:</strong> Dedicated for verified suppliers, cashiers, supervisors, and store operators.</span>
                    </div>
                </div>

                <div class="col-md-5 col-sm-12 text-center" style="margin-top: 25px;">
                    <!-- Terminal Mockup Card -->
                    <div style="background: rgba(15, 23, 42, 0.85); border: 2px solid #334155; border-radius: 16px; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); text-align: left; backdrop-filter: blur(10px);">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 12px; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 10px; height: 10px; border-radius: 50%; background: #ef4444;"></div>
                                <div style="width: 10px; height: 10px; border-radius: 50%; background: #f59e0b;"></div>
                                <div style="width: 10px; height: 10px; border-radius: 50%; background: #10b981;"></div>
                                <span style="font-family: monospace; font-size: 12px; color: #94a3b8; margin-left: 6px;">WebPOS Terminal v2.4</span>
                            </div>
                            <span class="label label-success" style="font-size: 10px; padding: 3px 8px; background-color: #10b981;">ONLINE LIVE</span>
                        </div>

                        <div style="background: #1e293b; border-radius: 8px; padding: 14px; margin-bottom: 12px; border: 1px solid #334155;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #94a3b8;">
                                <span>Active Register:</span>
                                <strong style="color: #f8fafc;">Register #1 • Main Counter</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #94a3b8; margin-top: 4px;">
                                <span>Pending Online Queue:</span>
                                <strong style="color: #f59e0b;"><i class="fa fa-shopping-cart"></i> Auto-Synced</strong>
                            </div>
                        </div>

                        <div style="background: #0f172a; border-radius: 8px; padding: 16px; border: 1px dashed #475569; text-align: center;">
                            <i class="fa fa-barcode fa-3x" style="color: #60a5fa; margin-bottom: 8px;"></i>
                            <div style="font-size: 13px; font-weight: 700; color: #f8fafc;">Ready for Barcode Scan or SKU Entry</div>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Instant variant resolution (Size, Length, Finish)</div>
                        </div>

                        <div style="margin-top: 18px; text-align: center;">
                            <a href="supplier/login.php" class="btn btn-warning btn-block" style="background: #f59e0b; border-color: #d97706; color: #000; font-weight: 800; padding: 10px; border-radius: 6px;">
                                <i class="fa fa-sign-in"></i> Access Supplier Console
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. CORE FEATURES DESIGNED FOR CONSTRUCTION HARDWARE                       -->
    <!-- ========================================================================= -->
    <div class="webpos-section">
        <div class="container">
            <div class="webpos-section-title">
                <h2>Engineered Exclusively for Building Materials & Trade POS</h2>
                <p>Unlike generic retail POS systems, WebPOS is tailored for heavy materials, variable sizes, multi-tender payment terms, and direct online marketplace routing.</p>
            </div>

            <div class="row" style="display: flex; flex-wrap: wrap; gap: 0;">
                <!-- Feature 1 -->
                <div class="col-md-4 col-sm-6" style="margin-bottom: 24px;">
                    <div class="webpos-card">
                        <div class="webpos-icon-box" style="background: #eff6ff; color: #2563eb;">
                            <i class="fa fa-cubes"></i>
                        </div>
                        <h4>Construction Variant Matrix</h4>
                        <p>Instantly select sizes, lengths, diameters, and surface finishes for deformed rebars, tubular steel, PVC pipes, plywood, and cement with real-time price adjustment.</p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-md-4 col-sm-6" style="margin-bottom: 24px;">
                    <div class="webpos-card">
                        <div class="webpos-icon-box" style="background: #fef3c7; color: #d97706;">
                            <i class="fa fa-shopping-cart"></i>
                        </div>
                        <h4>Marketplace Order Auto-Sync</h4>
                        <p>Customer and contractor orders placed through the eConstruction Marketplace immediately appear in your sidebar queue ready for counter pickup or delivery checkout.</p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-md-4 col-sm-6" style="margin-bottom: 24px;">
                    <div class="webpos-card">
                        <div class="webpos-icon-box" style="background: #dcfce7; color: #16a34a;">
                            <i class="fa fa-print"></i>
                        </div>
                        <h4>Thermal Receipt & PO Vouchers</h4>
                        <p>Generate clean 80mm/58mm thermal receipts, PO payment vouchers, and delivery receipts with customizable store details, cashier names, and itemized breakdowns.</p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="col-md-4 col-sm-6" style="margin-bottom: 24px;">
                    <div class="webpos-card">
                        <div class="webpos-icon-box" style="background: #f1f5f9; color: #475569;">
                            <i class="fa fa-credit-card"></i>
                        </div>
                        <h4>Credit Terms & Multi-Tender</h4>
                        <p>Accept Cash, Bank Transfer, Checks, and B2B Terms (15-Day / 30-Day Credit lines) with automated ledger tracking and overdue account alerts.</p>
                    </div>
                </div>

                <!-- Feature 5 -->
                <div class="col-md-4 col-sm-6" style="margin-bottom: 24px;">
                    <div class="webpos-card">
                        <div class="webpos-icon-box" style="background: #fae8ff; color: #a855f7;">
                            <i class="fa fa-users"></i>
                        </div>
                        <h4>Role-Based Access Control</h4>
                        <p>Dedicated operational views for Cashiers, Supervisors (discount authorization & approvals), Encoders, and Order Processing Staff to maintain strict audit integrity.</p>
                    </div>
                </div>

                <!-- Feature 6 -->
                <div class="col-md-4 col-sm-6" style="margin-bottom: 24px;">
                    <div class="webpos-card">
                        <div class="webpos-icon-box" style="background: #fee2e2; color: #dc2626;">
                            <i class="fa fa-line-chart"></i>
                        </div>
                        <h4>Capital-First Sales Margins</h4>
                        <p>View net profit, capital recovery, cashier drawer reconciliations, and item sales turnover without manually crunching spreadsheets.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. HOW IT WORKS                                                           -->
    <!-- ========================================================================= -->
    <div class="webpos-section" style="background: #ffffff; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
        <div class="container">
            <div class="webpos-section-title">
                <h2>Simple 3-Step Counter Workflow</h2>
                <p>Run your store operations smoothly without complicated setup or hardware lock-in.</p>
            </div>

            <div class="row">
                <div class="col-md-4 col-sm-12" style="margin-bottom: 20px;">
                    <div class="workflow-step-box">
                        <div class="workflow-number">1</div>
                        <h4 style="font-weight: 800; color: #0f172a; font-size: 17px; margin-bottom: 8px;">Log In to Terminal</h4>
                        <p style="font-size: 13.5px; color: #64748b; margin-bottom: 0;">Access your tenant account with your cashier or supervisor credentials from any browser.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12" style="margin-bottom: 20px;">
                    <div class="workflow-step-box">
                        <div class="workflow-number">2</div>
                        <h4 style="font-weight: 800; color: #0f172a; font-size: 17px; margin-bottom: 8px;">Scan or Select Items</h4>
                        <p style="font-size: 13.5px; color: #64748b; margin-bottom: 0;">Add items via barcode scanner, category navigation, or load pending marketplace online orders.</p>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12" style="margin-bottom: 20px;">
                    <div class="workflow-step-box">
                        <div class="workflow-number">3</div>
                        <h4 style="font-weight: 800; color: #0f172a; font-size: 17px; margin-bottom: 8px;">Collect & Print Voucher</h4>
                        <p style="font-size: 13.5px; color: #64748b; margin-bottom: 0;">Accept payment, issue thermal receipt, and sync inventory automatically across channels.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 4. HARDWARE COMPATIBILITY                                                 -->
    <!-- ========================================================================= -->
    <div class="webpos-section">
        <div class="container">
            <div class="webpos-section-title">
                <h2>Universal Hardware & Device Support</h2>
                <p>WebPOS runs seamlessly in standard modern browsers across touchscreens, tablets, and POS hardware.</p>
            </div>

            <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; text-align: center;">
                <div class="hardware-badge">
                    <i class="fa fa-print text-primary"></i> 80mm / 58mm Thermal Printers
                </div>
                <div class="hardware-badge">
                    <i class="fa fa-barcode text-primary"></i> USB & Wireless Barcode Scanners
                </div>
                <div class="hardware-badge">
                    <i class="fa fa-tablet text-primary"></i> iPad & Android Touch Tablets
                </div>
                <div class="hardware-badge">
                    <i class="fa fa-desktop text-primary"></i> Windows & Mac Desktop PCs
                </div>
                <div class="hardware-badge">
                    <i class="fa fa-money text-primary"></i> Standard RJ11/RJ12 Cash Drawers
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 5. CALL TO ACTION BANNER                                                  -->
    <!-- ========================================================================= -->
    <div class="container">
        <div class="webpos-cta-banner">
            <h2 style="font-size: 32px; font-weight: 900; color: #ffffff; margin: 0 0 12px 0;">
                Ready to Process Counter Sales with WebPOS?
            </h2>
            <p style="font-size: 15px; color: #cbd5e1; max-width: 550px; margin: 0 auto 28px auto;">
                Sign in to your authorized supplier terminal or register your store to start selling across the eConstruction network.
            </p>
            <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
                <a href="supplier/login.php" class="btn-webpos-launch">
                    <i class="fa fa-sign-in"></i> Supplier WebPOS Login
                </a>
                <a href="supplier-registration.php" class="btn-webpos-secondary">
                    <i class="fa fa-user-plus"></i> Supplier Registration
                </a>
                <a href="contact.php" class="btn-webpos-secondary">
                    <i class="fa fa-envelope-o"></i> Support & Inquiries
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once('footer.php'); ?>
