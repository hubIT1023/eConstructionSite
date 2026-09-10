<?php require_once('header.php'); ?>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div class="content-header-left">
            <h1 style="font-weight: 800; color: #0f172a; margin: 0; font-size: 24px;">
                <i class="fa fa-graduation-cap text-primary"></i> Developer &amp; System Architecture Portal
                <small style="font-size: 13px; color: #64748b; display: block; margin-top: 5px; font-weight: 500;">
                    Beginner-Friendly Technical Reference • Interactive HTML Directory Tree • Table ERD Diagram • Reusable Templates • APIs
                </small>
            </h1>
        </div>
        <div class="content-header-right" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="../user-manual.html" target="_blank" class="btn btn-default btn-sm" style="font-weight: 700;">
                <i class="fa fa-external-link text-primary"></i> User Manual
            </a>
            <a href="../supplier/user-manual.php" target="_blank" class="btn btn-info btn-sm" style="font-weight: 700;">
                <i class="fa fa-book"></i> Supplier SOP
            </a>
            <a href="supplier.php" class="btn btn-primary btn-sm" style="font-weight: 700;">
                <i class="fa fa-industry"></i> Manage Suppliers
            </a>
            <button type="button" class="btn btn-default btn-sm" onclick="window.print()" style="font-weight: 700;">
                <i class="fa fa-print"></i> Print Docs
            </button>
        </div>
    </div>
</section>

<style>
/* Modern Clean Developer Documentation Styling */
:root {
    --dev-primary: #0284c7;
    --dev-success: #16a34a;
    --dev-warning: #f59e0b;
    --dev-danger: #dc2626;
    --dev-dark: #0f172a;
    --dev-card-bg: #ffffff;
    --dev-border: #e2e8f0;
}

.dev-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    margin-bottom: 26px;
    overflow: hidden;
    transition: all 0.2s ease;
}
.dev-card:hover {
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}
.dev-card-header {
    background: #f8fafc;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 16px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.dev-card-title {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dev-card-body {
    padding: 24px;
}
.beginner-callout {
    background: #eff6ff;
    border-left: 4px solid #0284c7;
    padding: 14px 18px;
    border-radius: 0 8px 8px 0;
    margin: 16px 0;
    font-size: 13.5px;
    color: #1e3a8a;
    line-height: 1.6;
}
.beginner-tip {
    background: #f0fdf4;
    border-left: 4px solid #16a34a;
    padding: 14px 18px;
    border-radius: 0 8px 8px 0;
    margin: 16px 0;
    font-size: 13.5px;
    color: #14532d;
    line-height: 1.6;
}
.beginner-warning {
    background: #fef2f2;
    border-left: 4px solid #dc2626;
    padding: 14px 18px;
    border-radius: 0 8px 8px 0;
    margin: 16px 0;
    font-size: 13.5px;
    color: #7f1d1d;
    line-height: 1.6;
}
.dev-code-block {
    background: #0f172a;
    color: #f8fafc;
    border-radius: 8px;
    padding: 16px 20px;
    font-family: 'JetBrains Mono', Consolas, 'Courier New', monospace;
    font-size: 12.5px;
    line-height: 1.6;
    overflow-x: auto;
    margin: 14px 0;
    border: 1px solid #1e293b;
}

/* HTML-rendered Directory Tree Components */
.html-tree-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin: 16px 0;
}
.tree-node-card {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.02);
}
.tree-node-header {
    padding: 12px 16px;
    font-size: 14px;
    font-weight: 800;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
}
.tree-node-body {
    padding: 0;
}
.tree-file-row {
    padding: 9px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    transition: background 0.15s ease;
}
.tree-file-row:last-child {
    border-bottom: none;
}
.tree-file-row:hover {
    background: #f8fafc;
}
.tree-file-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.tree-file-name {
    font-family: 'JetBrains Mono', Consolas, monospace;
    font-weight: 700;
    color: #0f172a;
    font-size: 12.5px;
}
.tree-file-desc {
    color: #64748b;
    font-size: 12.5px;
}

.code-tag {
    background: #f1f5f9;
    color: #0f172a;
    padding: 3px 7px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid #cbd5e1;
    display: inline-block;
}
.table-dev {
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}
.table-dev th {
    background: #f8fafc;
    font-weight: 800;
    color: #334155;
    font-size: 12.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
    padding: 12px 14px;
}
.table-dev td {
    padding: 11px 14px;
    vertical-align: middle;
    border-top: 1px solid #f1f5f9;
}
.table-dev tr:hover td {
    background-color: #f8fafc;
}

.step-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #0284c7;
    color: #ffffff;
    font-weight: 800;
    font-size: 13px;
    margin-right: 8px;
}

.badge-endpoint {
    display: inline-block;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 800;
    border-radius: 4px;
    font-family: monospace;
}
.badge-post { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge-get { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.badge-put { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
.badge-delete { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

/* Sticky Navigation Bar */
.section-nav-sticky {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: #ffffff;
    padding: 12px 16px;
    margin-bottom: 24px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border: 1px solid #cbd5e1;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.search-input-box {
    border-radius: 6px;
    border: 1.5px solid #cbd5e1;
    padding: 6px 12px;
    font-size: 13px;
    outline: none;
    width: 240px;
    transition: all 0.2s ease;
}
.search-input-box:focus {
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.quick-chip {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid #e2e8f0;
    color: #475569;
    background: #f8fafc;
}
.quick-chip:hover, .quick-chip.active {
    background: #0284c7;
    color: #ffffff;
    border-color: #0284c7;
    text-decoration: none;
}

.diagram-container {
    background: #0b1329;
    border: 2px solid #1e293b;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    margin-bottom: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
.diagram-container img {
    border-radius: 8px;
    width: 100%;
    max-width: 1100px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    transition: transform 0.2s ease;
}
.diagram-container img:hover {
    transform: scale(1.01);
}
</style>

<section class="content">

    <!-- Sticky Navigation & Quick Search Bar -->
    <div class="section-nav-sticky">
        <div style="display: flex; gap: 6px; flex-wrap: wrap; flex-grow: 1;">
            <a href="#quick-start" class="quick-chip"><i class="fa fa-play text-primary"></i> 1. Quick Start</a>
            <a href="#about-app" class="quick-chip"><i class="fa fa-info-circle text-info"></i> 2. What is eConstruction?</a>
            <a href="#file-structure-tree" class="quick-chip"><i class="fa fa-sitemap text-success"></i> 3. HTML Directory Tree</a>
            <a href="#file-catalog" class="quick-chip"><i class="fa fa-folder-open text-warning"></i> 4. File Catalog</a>
            <a href="#database-schema" class="quick-chip"><i class="fa fa-database text-success"></i> 5. 40 Tables &amp; ERD</a>
            <a href="#pos-discount-engine" class="quick-chip"><i class="fa fa-percent text-danger"></i> 6. POS Sale &amp; Discounts</a>
            <a href="#approval-hierarchy" class="quick-chip"><i class="fa fa-sitemap text-primary"></i> 7. Roles &amp; RBAC Matrix</a>
            <a href="#pricing-engine" class="quick-chip"><i class="fa fa-line-chart text-info"></i> 8. Pricing Math</a>
            <a href="#code-templates" class="quick-chip"><i class="fa fa-code text-success"></i> 9. Reusable Templates</a>
            <a href="#api-reference" class="quick-chip" style="background: #0284c7; color: #fff; border-color: #0284c7;"><i class="fa fa-cogs"></i> 10. REST &amp; AJAX APIs</a>
            <a href="#c4-architecture" class="quick-chip"><i class="fa fa-cubes text-primary"></i> 11. C4 Models</a>
            <a href="#business-rules" class="quick-chip"><i class="fa fa-gavel text-warning"></i> 12. Business Rules</a>
            <a href="#gap-analysis" class="quick-chip"><i class="fa fa-exclamation-triangle text-danger"></i> 13. Gap Analysis</a>
            <a href="#adr-records" class="quick-chip"><i class="fa fa-balance-scale text-purple"></i> 14. ADRs</a>
            <a href="#ai-development" class="quick-chip"><i class="fa fa-robot text-teal"></i> 15. AI Guidelines</a>
            <a href="#test-cases" class="quick-chip"><i class="fa fa-check-square-o text-primary"></i> 16. QA Tests</a>
        </div>
        <div>
            <input type="text" id="docSearch" class="search-input-box" placeholder="🔍 Search files, templates, tables..." onkeyup="filterDocumentation()">
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 1: BEGINNER'S QUICK START GUIDE                 -->
    <!-- ======================================================= -->
    <div id="quick-start" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #f0fdf4; border-color: #bbf7d0;">
            <h3 class="dev-card-title" style="color: #166534;">
                <i class="fa fa-compass text-success"></i> 1. Beginner Developer Quick Start: How This App Works
            </h3>
            <span class="label label-success" style="font-size: 11px;">Beginner Friendly</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 14px; color: #334155; line-height: 1.7; margin-bottom: 16px;">
                Welcome to the <strong>eConstructionSite</strong> codebase! If you are new to this project, this section will give you a clear mental model of how everything connects in under 3 minutes.
            </p>

            <div class="row">
                <div class="col-md-4">
                    <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 18px; height: 100%;">
                        <div style="font-size: 26px; color: #0284c7; margin-bottom: 8px;"><i class="fa fa-sitemap"></i></div>
                        <h4 style="font-weight: 800; font-size: 15px; margin: 0 0 8px 0; color: #0f172a;">1. The 3 Main Web Portals</h4>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin: 0;">
                            • <strong>Customer Storefront (<code>/</code>)</strong>: Public online store where customers browse building supplies, add to cart, and checkout.<br>
                            • <strong>Supplier Portal &amp; POS (<code>/supplier</code>)</strong>: Where store owners manage products, inventory, staff, and run the physical cash register POS.<br>
                            • <strong>Super Admin (<code>/admin</code>)</strong>: Platform owner portal for approving new suppliers, global settings, and platform oversight.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 18px; height: 100%;">
                        <div style="font-size: 26px; color: #16a34a; margin-bottom: 8px;"><i class="fa fa-database"></i></div>
                        <h4 style="font-weight: 800; font-size: 15px; margin: 0 0 8px 0; color: #0f172a;">2. The Database Connection</h4>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin: 0;">
                            The application uses a <strong>PostgreSQL</strong> database named <code>ecomDB</code>. Every page starts by including:
                            <br><code class="code-tag" style="margin-top: 5px;">require_once('inc/config.php');</code><br>
                            This creates a global PDO connection variable <code>$pdo</code> ready for SQL queries.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 18px; height: 100%;">
                        <div style="font-size: 26px; color: #f59e0b; margin-bottom: 8px;"><i class="fa fa-lock"></i></div>
                        <h4 style="font-weight: 800; font-size: 15px; margin: 0 0 8px 0; color: #0f172a;">3. The Golden Multi-Tenancy Rule</h4>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin: 0;">
                            Each supplier has their own isolated store. When writing SQL queries in <code>/supplier/</code>, <strong>ALWAYS filter by <code>supplier_id</code></strong>:
                            <br><code class="code-tag" style="margin-top: 5px;">WHERE supplier_id = :supplier_id</code><br>
                            This prevents Supplier A from seeing Supplier B's products or sales!
                        </p>
                    </div>
                </div>
            </div>

            <div class="beginner-callout" style="margin-top: 20px;">
                <strong><i class="fa fa-lightbulb-o"></i> Beginner Coding Example: How to query the database in PHP</strong>
                <div class="dev-code-block" style="margin-top: 10px; margin-bottom: 5px;">
<span style="color: #94a3b8;">// 1. Prepare your SQL statement with named placeholders (:supplier_id)</span>
$statement = $pdo->prepare(<span style="color: #38bdf8;">"SELECT * FROM tbl_product WHERE supplier_id = :supplier_id AND p_qty > 0 ORDER BY p_id DESC"</span>);

<span style="color: #94a3b8;">// 2. Execute by passing the values safely (Prevents SQL Injection!)</span>
$statement->execute([
    <span style="color: #a78bfa;">':supplier_id'</span> => $_SESSION[<span style="color: #a78bfa;">'supplier'</span>][<span style="color: #a78bfa;">'id'</span>]
]);

<span style="color: #94a3b8;">// 3. Fetch the results as an associative array</span>
$products = $statement->fetchAll(PDO::FETCH_ASSOC);

<span style="color: #94a3b8;">// 4. Loop through each item</span>
<span style="color: #fb923c;">foreach</span> ($products <span style="color: #fb923c;">as</span> $row) {
    <span style="color: #4ade80;">echo</span> <span style="color: #38bdf8;">"Product: "</span> . htmlspecialchars($row[<span style="color: #a78bfa;">'p_name'</span>]) . <span style="color: #38bdf8;">" - Price: ₱"</span> . number_format($row[<span style="color: #a78bfa;">'p_current_price'</span>], 2) . <span style="color: #38bdf8;">"&lt;br&gt;"</span>;
}
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 2: ABOUT THE APPLICATION                        -->
    <!-- ======================================================= -->
    <div id="about-app" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #eff6ff; border-color: #bfdbfe;">
            <h3 class="dev-card-title" style="color: #1e40af;">
                <i class="fa fa-cubes text-primary"></i> 2. About the Application &amp; Technology Stack
            </h3>
            <span class="label label-primary">System Overview</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 14.5px; color: #334155; line-height: 1.7; margin-bottom: 16px;">
                <strong>eConstruction Supply</strong> (<a href="https://econstruction-supply.site" target="_blank">https://econstruction-supply.site</a>) is a complete multi-vendor marketplace, Point of Sale (POS) register, and warehouse inventory system tailored for the building and construction materials industry in Western Visayas, Philippines.
            </p>

            <!-- Tech Stack Table -->
            <h4 style="font-weight: 800; font-size: 14px; margin: 20px 0 10px 0; color: #0f172a;">Technology Stack &amp; Infrastructure</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-dev" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th width="180">Component</th>
                            <th width="240">Technology</th>
                            <th>What It Does &amp; Why It's Used</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Server Host</strong></td>
                            <td>Ubuntu 22.04 LTS Droplet</td>
                            <td>Cloud virtual machine hosted on DigitalOcean at IP <code>170.64.251.19</code>.</td>
                        </tr>
                        <tr>
                            <td><strong>Web Server &amp; Proxy</strong></td>
                            <td>Nginx + Apache Web Container</td>
                            <td>Nginx handles HTTPS/SSL termination and forwards traffic to the Docker PHP-Apache container (<code>econstructionsite-web</code>).</td>
                        </tr>
                        <tr>
                            <td><strong>Backend Language</strong></td>
                            <td>PHP 7.4 / 8.x + PDO</td>
                            <td>Clean, session-backed server-side scripting with parameterized PDO database calls and JSON REST APIs.</td>
                        </tr>
                        <tr>
                            <td><strong>Database Engine</strong></td>
                            <td>PostgreSQL 15 (<code>ecomDB</code>)</td>
                            <td>High-reliability relational database running in a Docker container (<code>econstructionsite-db</code>).</td>
                        </tr>
                        <tr>
                            <td><strong>User Interface (UI)</strong></td>
                            <td>Bootstrap 3.4 + AdminLTE + jQuery</td>
                            <td>Responsive layout, modal dialogues, instant AJAX search, thermal receipt formatting, and mobile compatibility.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 3: VISUAL FILE STRUCTURE & HTML DIRECTORY TREE  -->
    <!-- ======================================================= -->
    <div id="file-structure-tree" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #f0fdf4; border-color: #bbf7d0;">
            <h3 class="dev-card-title" style="color: #166534;">
                <i class="fa fa-sitemap text-success"></i> 3. Visual Architecture Diagram &amp; HTML Directory Tree Explorer
            </h3>
            <span class="label label-success">HTML Tree Explorer</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                Below is the high-resolution architecture diagram, followed by the <strong>readable HTML Directory Tree Explorer</strong>. Every file is color-coded with its module badge and functional explanation.
            </p>

            <!-- Embedded Architecture Infographic Image -->
            <div class="diagram-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <span style="color: #38bdf8; font-weight: 700; font-size: 14px;">
                        <i class="fa fa-image"></i> eConstructionSite Architecture &amp; File Structure Infographic
                    </span>
                    <a href="../assets/uploads/file_structure_diagram.jpg" target="_blank" class="btn btn-info btn-xs" style="font-weight: 700;">
                        <i class="fa fa-search-plus"></i> View Full Resolution
                    </a>
                </div>
                <a href="../assets/uploads/file_structure_diagram.jpg" target="_blank" title="Click to open full-size image">
                    <img src="../assets/uploads/file_structure_diagram.jpg" alt="eConstructionSite Software Architecture &amp; File Structure" class="img-responsive">
                </a>
                <p style="color: #94a3b8; font-size: 12px; margin-top: 10px; margin-bottom: 0;">
                    <i class="fa fa-info-circle"></i> Modular 5-tier architecture connecting Storefront, Admin Governance, Tenant POS, Payment Gateways, and PostgreSQL (40 Tables).
                </p>
            </div>

            <!-- READABLE HTML DIRECTORY TREE EXPLORER -->
            <h4 style="font-weight: 800; font-size: 15px; margin: 24px 0 12px 0; color: #0f172a;">
                <i class="fa fa-folder-open text-warning"></i> Interactive Codebase Directory Explorer (HTML Format)
            </h4>

            <div class="html-tree-container">

                <!-- Root Core & Containers -->
                <div class="tree-node-card">
                    <div class="tree-node-header" style="background: #f8fafc;">
                        <span><i class="fa fa-folder text-warning"></i> <code>/</code> (Project Root &amp; Containers)</span>
                        <span class="label label-default">Root Core</span>
                    </div>
                    <div class="tree-node-body">
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-cubes text-info"></i>
                                <span class="tree-file-name">docker-compose.yml</span>
                                <span class="label label-info" style="font-size: 10px;">Docker</span>
                            </div>
                            <span class="tree-file-desc">Multi-container orchestration for Apache PHP Web + PostgreSQL 15 DB</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-file-code-o text-primary"></i>
                                <span class="tree-file-name">Dockerfile</span>
                                <span class="label label-info" style="font-size: 10px;">Docker</span>
                            </div>
                            <span class="tree-file-desc">PHP Apache container build recipe with PDO PostgreSQL extensions</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-database text-success"></i>
                                <span class="tree-file-name">init-db/01-schema.sql</span>
                                <span class="label label-success" style="font-size: 10px;">Schema</span>
                            </div>
                            <span class="tree-file-desc">Master PostgreSQL initialization schema creating all 40 relational tables</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-globe text-primary"></i>
                                <span class="tree-file-name">index.php</span>
                                <span class="label label-primary" style="font-size: 10px;">Storefront</span>
                            </div>
                            <span class="tree-file-desc">Public homepage displaying hero sliders, materials showcase, and hot deals</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-shopping-cart text-warning"></i>
                                <span class="tree-file-name">cart.php</span>
                                <span class="label label-primary" style="font-size: 10px;">Storefront</span>
                            </div>
                            <span class="tree-file-desc">Shopping cart review, quantity editor, and subtotal calculation</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-credit-card text-success"></i>
                                <span class="tree-file-name">checkout.php</span>
                                <span class="label label-primary" style="font-size: 10px;">Storefront</span>
                            </div>
                            <span class="tree-file-desc">Customer checkout with 60 Santa Barbara barangay delivery options</span>
                        </div>
                    </div>
                </div>

                <!-- Admin Subsystem -->
                <div class="tree-node-card">
                    <div class="tree-node-header" style="background: #eff6ff;">
                        <span style="color: #1e40af;"><i class="fa fa-folder text-primary"></i> <code>/admin/</code> (Super Admin Subsystem)</span>
                        <span class="label label-primary">Back-Office Governance</span>
                    </div>
                    <div class="tree-node-body">
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-dashboard text-primary"></i>
                                <span class="tree-file-name">admin/index.php</span>
                                <span class="label label-primary" style="font-size: 10px;">Dashboard</span>
                            </div>
                            <span class="tree-file-desc">Platform executive dashboard (sales volume, registered suppliers, customer stats)</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-book text-info"></i>
                                <span class="tree-file-name">admin/dev-documentation.php</span>
                                <span class="label label-info" style="font-size: 10px;">Dev Portal</span>
                            </div>
                            <span class="tree-file-desc">Master developer manual, architecture roadmap, 40 DB tables, and REST API guide</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-building text-warning"></i>
                                <span class="tree-file-name">admin/supplier.php</span>
                                <span class="label label-warning" style="font-size: 10px;">Suppliers</span>
                            </div>
                            <span class="tree-file-desc">Supplier store onboarding, commission rates, and storewide discount policies</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-sitemap text-success"></i>
                                <span class="tree-file-name">admin/top-category.php</span>
                                <span class="label label-success" style="font-size: 10px;">Taxonomy</span>
                            </div>
                            <span class="tree-file-desc">Level 1 Categories (Building Materials, Infrastructure, Tools &amp; Safety)</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-cubes text-info"></i>
                                <span class="tree-file-name">admin/product.php</span>
                                <span class="label label-info" style="font-size: 10px;">Catalog</span>
                            </div>
                            <span class="tree-file-desc">Master catalog audit, Section 13 Price Rule evaluations, and product controls</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-cogs text-default"></i>
                                <span class="tree-file-name">admin/inc/config.php</span>
                                <span class="label label-danger" style="font-size: 10px;">Database</span>
                            </div>
                            <span class="tree-file-desc">Initializes global PostgreSQL PDO connection (<code>$pdo</code>) and constants</span>
                        </div>
                    </div>
                </div>

                <!-- Supplier Subsystem & POS -->
                <div class="tree-node-card">
                    <div class="tree-node-header" style="background: #f0fdf4;">
                        <span style="color: #166534;"><i class="fa fa-folder text-success"></i> <code>/supplier/</code> (Tenant Merchant &amp; POS Subsystem)</span>
                        <span class="label label-success">Store &amp; POS Engine</span>
                    </div>
                    <div class="tree-node-body">
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-calculator text-success"></i>
                                <span class="tree-file-name">supplier/pos.php</span>
                                <span class="label label-success" style="font-size: 10px;">POS Core</span>
                            </div>
                            <span class="tree-file-desc">Point of Sale register: barcode scanner, dynamic variants, and discount locking</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-money text-success"></i>
                                <span class="tree-file-name">supplier/checkout.php</span>
                                <span class="label label-success" style="font-size: 10px;">POS Checkout</span>
                            </div>
                            <span class="tree-file-desc">POS Checkout: Cash tender, split payment, invoice printing, and cart persistence</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-percent text-danger"></i>
                                <span class="tree-file-name">supplier/discount-request.php</span>
                                <span class="label label-danger" style="font-size: 10px;">Anti-Fraud</span>
                            </div>
                            <span class="tree-file-desc">Cashier discount request modal that locks terminal until supervisor authorizes</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-check-circle text-info"></i>
                                <span class="tree-file-name">supplier/ajax-approve-discount.php</span>
                                <span class="label label-info" style="font-size: 10px;">REST API</span>
                            </div>
                            <span class="tree-file-desc">AJAX endpoint for supervisors to authorize discount requests via PIN or queue</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-line-chart text-warning"></i>
                                <span class="tree-file-name">supplier/product.php</span>
                                <span class="label label-warning" style="font-size: 10px;">Pricing</span>
                            </div>
                            <span class="tree-file-desc">Store inventory editor enforcing Section 13 formulas ($Ca$, $\text{₱}$, $C$, $N$)</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-refresh text-success"></i>
                                <span class="tree-file-name">supplier/restock.php</span>
                                <span class="label label-success" style="font-size: 10px;">Restock</span>
                            </div>
                            <span class="tree-file-desc">Inventory restock modal with automated New Price ($N$) to Current Price ($C$) rollover</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-users text-primary"></i>
                                <span class="tree-file-name">supplier/supplier-user.php</span>
                                <span class="label label-primary" style="font-size: 10px;">Staff RBAC</span>
                            </div>
                            <span class="tree-file-desc">Staff user management with automated employee IDs (<code>WCS-CSH-001</code>)</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Subsystem -->
                <div class="tree-node-card">
                    <div class="tree-node-header" style="background: #faf5ff;">
                        <span style="color: #7e22ce;"><i class="fa fa-folder text-purple"></i> <code>/payment/</code> (Payment Gateway Processors)</span>
                        <span class="label label-info" style="background: #7e22ce;">Gateways</span>
                    </div>
                    <div class="tree-node-body">
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-university text-info"></i>
                                <span class="tree-file-name">payment/bank/process.php</span>
                                <span class="label label-info" style="font-size: 10px;">Bank</span>
                            </div>
                            <span class="tree-file-desc">Validates direct bank deposit reference and creates <code>tbl_payment</code> record</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-handshake-o text-warning"></i>
                                <span class="tree-file-name">payment/otc/process.php</span>
                                <span class="label label-warning" style="font-size: 10px;">OTC</span>
                            </div>
                            <span class="tree-file-desc">Processes walk-in Over The Counter cash transaction vouchers</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-paypal text-primary"></i>
                                <span class="tree-file-name">payment/paypal/process.php</span>
                                <span class="label label-primary" style="font-size: 10px;">PayPal</span>
                            </div>
                            <span class="tree-file-desc">Instant Payment Notification (IPN) webhook listener for online orders</span>
                        </div>
                        <div class="tree-file-row">
                            <div class="tree-file-left">
                                <i class="fa fa-file-text-o text-success"></i>
                                <span class="tree-file-name">payment/po/process.php</span>
                                <span class="label label-success" style="font-size: 10px;">PO Terms</span>
                            </div>
                            <span class="tree-file-desc">Generates credit-line Purchase Orders for verified general contractors</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 4: COMPLETE CODEBASE FILE CATALOG               -->
    <!-- ======================================================= -->
    <div id="file-catalog" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #fefce8; border-color: #fef08a;">
            <h3 class="dev-card-title" style="color: #854d0e;">
                <i class="fa fa-folder-open text-warning"></i> 4. Detailed Codebase File Catalog &amp; Module Scope
            </h3>
            <span class="label label-warning">File Catalog</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                Below is the tabular index of all files with their module classification and practical developer guidance on when to edit each file.
            </p>

            <!-- 4.1 Customer Storefront -->
            <h4 style="font-weight: 800; font-size: 15px; color: #0369a1; margin: 20px 0 10px 0;">
                <i class="fa fa-globe"></i> 4.1 Customer Storefront &amp; Public Marketplace (Root Directory <code>/</code>)
            </h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="240">File Name</th>
                            <th width="150">Module Area</th>
                            <th>Description &amp; When a Developer Should Edit This</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>index.php</code></td><td>Homepage</td><td>The main landing page. Edit to customize hero banners, featured materials, and deals.</td></tr>
                        <tr><td><code>header.php</code> / <code>footer.php</code></td><td>Core Layout</td><td>Global HTML header and footer included across all storefront pages. Contains navigation menu &amp; cart icon.</td></tr>
                        <tr><td><code>product.php</code></td><td>Product Page</td><td>Individual product display page showing sizes, colors, price, stock status, and add-to-cart button.</td></tr>
                        <tr><td><code>product-category.php</code></td><td>Catalog Filter</td><td>Lists products by category (Top, Mid, End) with live search and price sorting.</td></tr>
                        <tr><td><code>cart.php</code></td><td>Shopping Cart</td><td>Displays customer cart items, calculates subtotals, allows quantity updates, and links to checkout.</td></tr>
                        <tr><td><code>checkout.php</code></td><td>Customer Checkout</td><td>Handles shipping/billing addresses, Santa Barbara barangay delivery selection, and payment options.</td></tr>
                        <tr><td><code>customer-order.php</code></td><td>Customer Account</td><td>Displays customer's past orders, payment status, receipts, and delivery tracking.</td></tr>
                        <tr><td><code>customer-return.php</code></td><td>RMA Returns</td><td>Allows customers to request an item return/refund with photo upload and reason selection.</td></tr>
                        <tr><td><code>login.php</code> / <code>registration.php</code></td><td>Authentication</td><td>Customer login, registration, password hashing, and account activation.</td></tr>
                        <tr><td><code>search.php</code></td><td>Search Engine</td><td>Keyword search results across all supplier products.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- 4.2 Super Admin Portal -->
            <h4 style="font-weight: 800; font-size: 15px; color: #1e40af; margin: 28px 0 10px 0;">
                <i class="fa fa-user-secret"></i> 4.2 Super Administrator Portal (<code>/admin</code>)
            </h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="240">File Name</th>
                            <th width="150">Module Area</th>
                            <th>Description &amp; When a Developer Should Edit This</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>admin/index.php</code></td><td>Dashboard</td><td>Master executive dashboard showing total platform sales, registered suppliers, and customer counts.</td></tr>
                        <tr><td><code>admin/supplier.php</code> (+ <code>add</code>/<code>edit</code>)</td><td>Supplier Master</td><td>Allows Super Admin to onboard suppliers, set discount limits, and activate/deactivate accounts.</td></tr>
                        <tr><td><code>admin/top-category.php</code> (+ <code>mid</code>/<code>end</code>)</td><td>Category Taxonomy</td><td>Admin files to create and edit Top, Mid, and End product categories.</td></tr>
                        <tr><td><code>admin/product.php</code> (+ <code>edit</code>/<code>delete</code>)</td><td>Master Catalog</td><td>Platform-wide catalog audit, Section 13 Price Rule evaluations, and master product management.</td></tr>
                        <tr><td><code>admin/order.php</code></td><td>Order Audit</td><td>Master audit log of all transactions across all suppliers with status changing capabilities.</td></tr>
                        <tr><td><code>admin/customer.php</code></td><td>Customer Accounts</td><td>View registered customers, activate/deactivate user accounts, and review customer messages.</td></tr>
                        <tr><td><code>admin/shipping-cost.php</code></td><td>Logistics Setup</td><td>Set base shipping fees per municipality and flat-rate delivery charges.</td></tr>
                        <tr><td><code>admin/settings.php</code></td><td>System Settings</td><td>Upload website logo, favicon, change site contact email, and set currency symbol (<code>₱</code>).</td></tr>
                        <tr><td><code>admin/dev-documentation.php</code></td><td>Developer Portal</td><td>This file! The comprehensive master system documentation and developer handbook.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- 4.3 Supplier Merchant & POS Subsystem -->
            <h4 style="font-weight: 800; font-size: 15px; color: #166534; margin: 28px 0 10px 0;">
                <i class="fa fa-industry"></i> 4.3 Supplier Merchant &amp; POS Subsystem (<code>/supplier</code>)
            </h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="240">File Name</th>
                            <th width="150">Module Area</th>
                            <th>Description &amp; When a Developer Should Edit This</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>supplier/pos.php</code></td><td>POS Register</td><td>In-store Point of Sale terminal: barcode scanning, cart calculation, Special Orders, and discount requests.</td></tr>
                        <tr><td><code>supplier/checkout.php</code></td><td>POS Checkout</td><td>Supplier checkout screen: cash tender, change calculation, split payments, and receipt generation.</td></tr>
                        <tr><td><code>supplier/ajax-approve-discount.php</code></td><td>Discount Approvals</td><td>AJAX endpoint for supervisors to authorize or reject cashiers' item discount requests.</td></tr>
                        <tr><td><code>supplier/ajax-check-discount.php</code></td><td>Discount Polling</td><td>AJAX polling endpoint that informs the POS terminal when a supervisor has approved a discount.</td></tr>
                        <tr><td><code>supplier/product.php</code> (+ <code>add</code>/<code>edit</code>)</td><td>Inventory &amp; Pricing</td><td>Supplier catalog editor enforcing Section 13 price formulas ($Ca$, $\text{₱}$, $C$, $N$) and safety stock levels ($S$).</td></tr>
                        <tr><td><code>supplier/restock.php</code></td><td>Restock &amp; Rollover</td><td>Inventory restock modal that adds new quantity ($NQ$) and rolls over New Price ($N$) into Current Price ($C$).</td></tr>
                        <tr><td><code>supplier/supplier-user.php</code> (+ <code>add</code>/<code>edit</code>)</td><td>Staff &amp; RBAC</td><td>Manage store staff (Admin, Manager, Supervisor, Cashier, etc.) and auto-generate employee IDs.</td></tr>
                        <tr><td><code>supplier/returns.php</code></td><td>RMA Management</td><td>Review customer return requests, inspect uploaded proof photos, approve refunds, and restock items.</td></tr>
                        <tr><td><code>supplier/sales-report.php</code></td><td>Sales Analytics</td><td>Financial reporting, daily cash totals, product sales ranking, and CSV export.</td></tr>
                        <tr><td><code>supplier/quote.php</code></td><td>B2B Quotes (RFQ)</td><td>Review quotation requests from contractors and issue formal priced quotations.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- 4.4 Payments & Core Includes -->
            <h4 style="font-weight: 800; font-size: 15px; color: #7c2d12; margin: 28px 0 10px 0;">
                <i class="fa fa-credit-card"></i> 4.4 Payment Gateways &amp; Core Includes (<code>/payment</code> &amp; <code>inc/</code>)
            </h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="240">File Name</th>
                            <th width="150">Module Area</th>
                            <th>Description &amp; When a Developer Should Edit This</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>payment/bank/process.php</code></td><td>Bank Transfer</td><td>Processes direct bank deposits and stores bank transaction reference numbers.</td></tr>
                        <tr><td><code>payment/otc/process.php</code></td><td>Over The Counter</td><td>Processes walk-in Over-The-Counter cash payments.</td></tr>
                        <tr><td><code>payment/paypal/process.php</code></td><td>PayPal Gateway</td><td>Handles PayPal API checkout initialization and IPN order capture.</td></tr>
                        <tr><td><code>payment/po/process.php</code></td><td>Purchase Order (PO)</td><td>Processes B2B credit-term Purchase Orders for registered construction contractors.</td></tr>
                        <tr><td><code>admin/inc/config.php</code></td><td>Database Config</td><td>Establishes the global PostgreSQL PDO database connection (<code>$pdo</code>) and system constants.</td></tr>
                        <tr><td><code>admin/inc/functions.php</code></td><td>Helper Functions</td><td>Core helper functions: role normalization, input sanitization, security validation, and currency formatters.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 5: DATABASE SCHEMA & TABLE RELATIONSHIP ERD     -->
    <!-- ======================================================= -->
    <div id="database-schema" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #f0fdf4; border-color: #bbf7d0;">
            <h3 class="dev-card-title" style="color: #166534;">
                <i class="fa fa-database text-success"></i> 5. Complete Database Schema &amp; Table Relationship Diagram (ERD)
            </h3>
            <span class="label label-success">PostgreSQL (40 Tables)</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                The platform database (<code>ecomDB</code>) contains <strong>40 relational tables</strong>. Below is the full Entity Relationship Diagram (ERD) illustrating Primary/Foreign Key links, followed by the complete table reference catalog.
            </p>

            <!-- Table Relationship ERD Diagram Image -->
            <div class="diagram-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <span style="color: #38bdf8; font-weight: 700; font-size: 14px;">
                        <i class="fa fa-code-fork"></i> eConstructionSite Database Entity Relationship Diagram (ERD)
                    </span>
                    <a href="../assets/uploads/table_erd_diagram.jpg" target="_blank" class="btn btn-info btn-xs" style="font-weight: 700;">
                        <i class="fa fa-search-plus"></i> View Full Resolution ERD
                    </a>
                </div>
                <a href="../assets/uploads/table_erd_diagram.jpg" target="_blank" title="Click to open full-size ERD diagram">
                    <img src="../assets/uploads/table_erd_diagram.jpg" alt="eConstructionSite PostgreSQL Database Entity Relationship Diagram (ERD)" class="img-responsive">
                </a>
                <p style="color: #94a3b8; font-size: 12px; margin-top: 10px; margin-bottom: 0;">
                    <i class="fa fa-info-circle"></i> Complete schema relationships mapping Suppliers, Products &amp; Variants, Orders &amp; Payments, Customer RFQs, and Barangay Delivery.
                </p>
            </div>

            <!-- Foreign Key Relationship Cheatsheet -->
            <h4 style="font-weight: 800; font-size: 14px; margin: 24px 0 10px 0; color: #0f172a;">
                <i class="fa fa-key text-warning"></i> Key Foreign Key Cardinality &amp; Relationship Matrix
            </h4>
            <div class="table-responsive">
                <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="220">Primary / Parent Table</th>
                            <th width="100">Cardinality</th>
                            <th width="220">Foreign / Child Table</th>
                            <th>Relationship Purpose &amp; Cascade Logic</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>tbl_supplier (id)</code></td>
                            <td><span class="label label-primary">1 : N</span></td>
                            <td><code>tbl_product (supplier_id)</code></td>
                            <td>Isolates store catalog inventory to the owning supplier tenant.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_supplier (id)</code></td>
                            <td><span class="label label-primary">1 : N</span></td>
                            <td><code>tbl_supplier_user (supplier_id)</code></td>
                            <td>Maps staff employees, roles, and POS login credentials to their store.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_top_category (tcat_id)</code></td>
                            <td><span class="label label-info">1 : N</span></td>
                            <td><code>tbl_mid_category (tcat_id)</code></td>
                            <td>Hierarchical category taxonomy: Tier-1 root to Tier-2 groupings.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_mid_category (mcat_id)</code></td>
                            <td><span class="label label-info">1 : N</span></td>
                            <td><code>tbl_end_category (mcat_id)</code></td>
                            <td>Hierarchical category taxonomy: Tier-2 groupings to Tier-3 leaf nodes.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_end_category (ecat_id)</code></td>
                            <td><span class="label label-info">1 : N</span></td>
                            <td><code>tbl_product (ecat_id)</code></td>
                            <td>Assigns specific construction products to their appropriate leaf category.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_product (p_id)</code></td>
                            <td><span class="label label-success">1 : N</span></td>
                            <td><code>tbl_product_size (product_id)</code></td>
                            <td>Many-to-Many junction mapping products to dimensional sizes (<code>tbl_size</code>).</td>
                        </tr>
                        <tr>
                            <td><code>tbl_product (p_id)</code></td>
                            <td><span class="label label-success">1 : N</span></td>
                            <td><code>tbl_product_color (product_id)</code></td>
                            <td>Many-to-Many junction mapping products to color finishes (<code>tbl_color</code>).</td>
                        </tr>
                        <tr>
                            <td><code>tbl_customer (cust_id)</code></td>
                            <td><span class="label label-warning">1 : N</span></td>
                            <td><code>tbl_payment (customer_id)</code></td>
                            <td>Links customer accounts to order receipts and transactions.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_payment (payment_id)</code></td>
                            <td><span class="label label-danger">1 : N</span></td>
                            <td><code>tbl_order (payment_id)</code></td>
                            <td>Master-Detail relationship: payment header contains multiple order line items.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_product (p_id)</code></td>
                            <td><span class="label label-danger">1 : N</span></td>
                            <td><code>tbl_order (product_id)</code></td>
                            <td>Identifies which catalog product was purchased in each line item.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_product (p_id)</code></td>
                            <td><span class="label label-danger">1 : N</span></td>
                            <td><code>tbl_discount_requests (product_id)</code></td>
                            <td>Associates POS cashier discount requests to specific catalog items.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_returns (id)</code></td>
                            <td><span class="label label-danger">1 : N</span></td>
                            <td><code>tbl_return_items (return_id)</code></td>
                            <td>RMA master header containing individual returned line items.</td>
                        </tr>
                        <tr>
                            <td><code>tbl_town (town_id)</code></td>
                            <td><span class="label label-default">1 : N</span></td>
                            <td><code>tbl_brgy (town_id)</code></td>
                            <td>Maps Western Visayas municipalities to 60 Santa Barbara delivery barangays.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 5.1 Multi-Tenancy & Staff Tables -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #0f172a; margin: 26px 0 10px 0;">
                <i class="fa fa-building text-primary"></i> 5.1 Supplier Stores &amp; Staff (3 Tables)
            </h4>
            <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th width="220">Table Name</th>
                        <th width="80">Rows</th>
                        <th width="240">Key Columns</th>
                        <th>What It Stores in Plain English</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>tbl_supplier</code></td>
                        <td><span class="badge" style="background: #0284c7;">14</span></td>
                        <td><code>id (PK)</code>, <code>supplier_name</code>, <code>supplier_email</code>, <code>supplier_phone</code>, <code>status</code></td>
                        <td>The master record for each supplier store (e.g. Western Construction Supply). Stores contact info and store discount rules.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_supplier_user</code></td>
                        <td><span class="badge" style="background: #0284c7;">31</span></td>
                        <td><code>id (PK)</code>, <code>supplier_id (FK)</code>, <code>employee_id</code>, <code>email</code>, <code>role</code>, <code>pos_access</code></td>
                        <td>Employee login accounts for suppliers (Admins, Managers, Supervisors, Cashiers, Encoders).</td>
                    </tr>
                    <tr>
                        <td><code>tbl_supplier_employee_sequence</code></td>
                        <td><span class="badge" style="background: #0284c7;">14</span></td>
                        <td><code>supplier_id (PK)</code>, <code>last_sequence</code></td>
                        <td>A counter that auto-generates clean, sequential employee ID numbers for each supplier (e.g. <code>EMP-001</code>, <code>EMP-002</code>).</td>
                    </tr>
                </tbody>
            </table>

            <!-- 5.2 Products & Catalog Tables -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #0f172a; margin: 26px 0 10px 0;">
                <i class="fa fa-cubes text-info"></i> 5.2 Products, Categories &amp; Specifications (9 Tables)
            </h4>
            <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th width="220">Table Name</th>
                        <th width="80">Rows</th>
                        <th width="240">Key Columns</th>
                        <th>What It Stores in Plain English</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>tbl_product</code></td>
                        <td><span class="badge" style="background: #0284c7;">133</span></td>
                        <td><code>p_id (PK)</code>, <code>supplier_id (FK)</code>, <code>p_name</code>, <code>p_capital_price</code>, <code>p_markup</code>, <code>p_current_price</code>, <code>p_qty</code>, <code>p_s_level</code></td>
                        <td>Master product inventory. Stores product name, Section 13 pricing parameters ($Ca, \text{₱}, C, N$), stock quantity ($Q$), and safety stock ($S$).</td>
                    </tr>
                    <tr>
                        <td><code>tbl_top_category</code></td>
                        <td><span class="badge" style="background: #0284c7;">3</span></td>
                        <td><code>tcat_id (PK)</code>, <code>tcat_name</code></td>
                        <td>Level 1 Categories (e.g. Building Materials, Infrastructure, Tools &amp; Safety).</td>
                    </tr>
                    <tr>
                        <td><code>tbl_mid_category</code></td>
                        <td><span class="badge" style="background: #0284c7;">7</span></td>
                        <td><code>mcat_id (PK)</code>, <code>tcat_id (FK)</code>, <code>mcat_name</code></td>
                        <td>Level 2 Subcategories (e.g. Steel, Concrete, Roofing, Electrical, Plumbing).</td>
                    </tr>
                    <tr>
                        <td><code>tbl_end_category</code></td>
                        <td><span class="badge" style="background: #0284c7;">22</span></td>
                        <td><code>ecat_id (PK)</code>, <code>mcat_id (FK)</code>, <code>ecat_name</code></td>
                        <td>Level 3 Leaf Categories (e.g. Rebar, Deformed Bars, PVC Pipes) directly linked to products.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_size</code></td>
                        <td><span class="badge" style="background: #0284c7;">144</span></td>
                        <td><code>size_id (PK)</code>, <code>size_name</code></td>
                        <td>Dictionary of dimensions, thicknesses, and lengths (e.g. 10mm x 6m, 12mm x 6m).</td>
                    </tr>
                    <tr>
                        <td><code>tbl_color</code></td>
                        <td><span class="badge" style="background: #0284c7;">29</span></td>
                        <td><code>color_id (PK)</code>, <code>color_name</code></td>
                        <td>Dictionary of colors and surface finishes (e.g. Red Oxide, Galvanized, Blue).</td>
                    </tr>
                    <tr>
                        <td><code>tbl_product_size</code></td>
                        <td><span class="badge" style="background: #0284c7;">167</span></td>
                        <td><code>id (PK)</code>, <code>p_id (FK)</code>, <code>size_id (FK)</code></td>
                        <td>Links which sizes are available for each product.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_product_color</code></td>
                        <td><span class="badge" style="background: #0284c7;">2</span></td>
                        <td><code>id (PK)</code>, <code>p_id (FK)</code>, <code>color_id (FK)</code></td>
                        <td>Links which colors are available for each product.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_product_photo</code></td>
                        <td><span class="badge" style="background: #64748b;">0</span></td>
                        <td><code>id (PK)</code>, <code>p_id (FK)</code>, <code>photo</code></td>
                        <td>Additional gallery photos for products.</td>
                    </tr>
                </tbody>
            </table>

            <!-- 5.3 Orders, Payments, Discounts & Returns -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #0f172a; margin: 26px 0 10px 0;">
                <i class="fa fa-shopping-cart text-success"></i> 5.3 Orders, Payments, POS Discounts &amp; Returns (5 Tables)
            </h4>
            <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th width="220">Table Name</th>
                        <th width="80">Rows</th>
                        <th width="240">Key Columns</th>
                        <th>What It Stores in Plain English</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>tbl_payment</code></td>
                        <td><span class="badge" style="background: #0284c7;">55</span></td>
                        <td><code>id (PK)</code>, <code>payment_id</code>, <code>customer_email</code>, <code>paid_amount</code>, <code>payment_method</code>, <code>payment_status</code>, <code>supplier_id</code></td>
                        <td>Master receipt/invoice for an order. Stores the payment method (Cash, Bank, OTC, PO, PayPal), total amount paid, and status.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_order</code></td>
                        <td><span class="badge" style="background: #0284c7;">86</span></td>
                        <td><code>id (PK)</code>, <code>payment_id (FK)</code>, <code>product_id</code>, <code>quantity</code>, <code>unit_price</code>, <code>item_type</code></td>
                        <td>Individual items inside an order. Every item in the customer's cart becomes a row in this table.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_discount_requests</code></td>
                        <td><span class="badge" style="background: #0284c7;">97</span></td>
                        <td><code>id (PK)</code>, <code>supplier_id (FK)</code>, <code>cashier_id</code>, <code>requested_discount_percent</code>, <code>status</code>, <code>approver_id</code></td>
                        <td>Real-time audit log of cashier discount requests at the POS terminal, including approval status (<code>pending</code>, <code>approved</code>, <code>rejected</code>).</td>
                    </tr>
                    <tr>
                        <td><code>tbl_returns</code></td>
                        <td><span class="badge" style="background: #0284c7;">6</span></td>
                        <td><code>id (PK)</code>, <code>order_id</code>, <code>customer_id</code>, <code>status</code>, <code>refund_amount</code></td>
                        <td>Customer RMA return requests. Tracks inspection status and refund approval.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_return_items</code></td>
                        <td><span class="badge" style="background: #0284c7;">6</span></td>
                        <td><code>id (PK)</code>, <code>return_id (FK)</code>, <code>product_id</code>, <code>qty_returned</code>, <code>action_type</code></td>
                        <td>Specific items returned and whether they were restocked into inventory or discarded.</td>
                    </tr>
                </tbody>
            </table>

            <!-- 5.4 Customers, Accounts & B2B Quotes -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #0f172a; margin: 26px 0 10px 0;">
                <i class="fa fa-users text-warning"></i> 5.4 Customers, Accounts &amp; B2B Quotes (6 Tables)
            </h4>
            <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th width="220">Table Name</th>
                        <th width="80">Rows</th>
                        <th width="240">Key Columns</th>
                        <th>What It Stores in Plain English</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>tbl_customer</code></td>
                        <td><span class="badge" style="background: #0284c7;">12</span></td>
                        <td><code>cust_id (PK)</code>, <code>cust_name</code>, <code>cust_email</code>, <code>cust_phone</code>, <code>cust_password</code>, <code>cust_status</code></td>
                        <td>Registered retail customers and construction contractor buyer accounts.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_customer_message</code></td>
                        <td><span class="badge" style="background: #0284c7;">7</span></td>
                        <td><code>id (PK)</code>, <code>customer_id (FK)</code>, <code>subject</code>, <code>message</code></td>
                        <td>Customer support inquiries and messages sent to suppliers or admins.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_user</code></td>
                        <td><span class="badge" style="background: #0284c7;">2</span></td>
                        <td><code>id (PK)</code>, <code>full_name</code>, <code>email</code>, <code>password</code>, <code>role</code></td>
                        <td>Super Admin accounts for accessing the <code>/admin</code> portal.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_quote</code></td>
                        <td><span class="badge" style="background: #64748b;">0</span></td>
                        <td><code>quote_id (PK)</code>, <code>customer_id</code>, <code>supplier_id</code>, <code>total_amount</code>, <code>status</code></td>
                        <td>B2B Request for Quote (RFQ) headers from commercial builders asking for custom bulk prices.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_quote_item</code></td>
                        <td><span class="badge" style="background: #64748b;">0</span></td>
                        <td><code>id (PK)</code>, <code>quote_id (FK)</code>, <code>product_id</code>, <code>quantity</code>, <code>quoted_price</code></td>
                        <td>Individual items and quantities requested inside a B2B quotation.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_rating</code></td>
                        <td><span class="badge" style="background: #64748b;">0</span></td>
                        <td><code>rt_id (PK)</code>, <code>p_id (FK)</code>, <code>cust_id (FK)</code>, <code>rating</code>, <code>comment</code></td>
                        <td>Star reviews (1-5) and customer feedback on products.</td>
                    </tr>
                </tbody>
            </table>

            <!-- 5.5 Geography, Logistics & Delivery -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #0f172a; margin: 26px 0 10px 0;">
                <i class="fa fa-map-marker text-danger"></i> 5.5 Geography, Barangay Delivery &amp; Shipping (5 Tables)
            </h4>
            <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th width="220">Table Name</th>
                        <th width="80">Rows</th>
                        <th width="240">Key Columns</th>
                        <th>What It Stores in Plain English</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>tbl_brgy</code></td>
                        <td><span class="badge" style="background: #0284c7;">60</span></td>
                        <td><code>brgy_id (PK)</code>, <code>town_id (FK)</code>, <code>brgy_name</code></td>
                        <td>List of all 60 barangays in Santa Barbara / Iloilo used for exact delivery fee calculation at checkout.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_town</code></td>
                        <td><span class="badge" style="background: #0284c7;">43</span></td>
                        <td><code>town_id (PK)</code>, <code>country_id (FK)</code>, <code>town_name</code></td>
                        <td>Municipalities and cities across Iloilo and Western Visayas.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_country</code></td>
                        <td><span class="badge" style="background: #0284c7;">44</span></td>
                        <td><code>country_id (PK)</code>, <code>country_name</code></td>
                        <td>Countries list (Default: Philippines).</td>
                    </tr>
                    <tr>
                        <td><code>tbl_shipping_cost</code></td>
                        <td><span class="badge" style="background: #0284c7;">6</span></td>
                        <td><code>shipping_cost_id (PK)</code>, <code>country_id</code>, <code>amount</code></td>
                        <td>Zone-specific delivery fee rates configured in Admin.</td>
                    </tr>
                    <tr>
                        <td><code>tbl_shipping_cost_all</code></td>
                        <td><span class="badge" style="background: #0284c7;">1</span></td>
                        <td><code>shipping_cost_all_id (PK)</code>, <code>amount</code></td>
                        <td>Fallback flat shipping fee when no specific zone fee applies.</td>
                    </tr>
                </tbody>
            </table>

            <!-- 5.6 CMS, Pages, Media & System -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #0f172a; margin: 26px 0 10px 0;">
                <i class="fa fa-cogs text-info"></i> 5.6 CMS, Website Content, Media &amp; Localization (12 Tables)
            </h4>
            <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th width="220">Table Name</th>
                        <th width="80">Rows</th>
                        <th width="240">Key Columns</th>
                        <th>What It Stores in Plain English</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>tbl_settings</code></td><td><span class="badge" style="background: #0284c7;">1</span></td><td><code>id</code>, <code>logo</code>, <code>favicon</code>, <code>currency</code></td><td>Global website settings, site logo, favicon, contact details, currency symbol.</td></tr>
                    <tr><td><code>tbl_language</code></td><td><span class="badge" style="background: #0284c7;">163</span></td><td><code>id</code>, <code>name</code>, <code>value</code></td><td>Dictionary of 163 UI text translation strings and labels.</td></tr>
                    <tr><td><code>tbl_page</code></td><td><span class="badge" style="background: #0284c7;">1</span></td><td><code>id</code>, <code>about_title</code>, <code>faq_title</code></td><td>CMS titles and SEO meta descriptions for static pages.</td></tr>
                    <tr><td><code>tbl_slider</code></td><td><span class="badge" style="background: #0284c7;">3</span></td><td><code>id</code>, <code>photo</code>, <code>heading</code>, <code>button_text</code></td><td>Hero banner sliders on the homepage.</td></tr>
                    <tr><td><code>tbl_service</code></td><td><span class="badge" style="background: #0284c7;">6</span></td><td><code>id</code>, <code>title</code>, <code>content</code>, <code>photo</code></td><td>Feature highlights on the homepage (e.g. Fast Site Delivery, Best Wholesale Prices).</td></tr>
                    <tr><td><code>tbl_faq</code></td><td><span class="badge" style="background: #0284c7;">5</span></td><td><code>faq_id</code>, <code>faq_title</code>, <code>faq_content</code></td><td>Frequently Asked Questions and answers displayed on the FAQ page.</td></tr>
                    <tr><td><code>tbl_post</code></td><td><span class="badge" style="background: #0284c7;">11</span></td><td><code>post_id</code>, <code>post_title</code>, <code>post_content</code></td><td>Construction blog articles, news, and technical guides.</td></tr>
                    <tr><td><code>tbl_photo</code></td><td><span class="badge" style="background: #0284c7;">6</span></td><td><code>id</code>, <code>caption</code>, <code>photo</code></td><td>Website photo gallery images.</td></tr>
                    <tr><td><code>tbl_video</code></td><td><span class="badge" style="background: #0284c7;">3</span></td><td><code>id</code>, <code>title</code>, <code>iframe_code</code></td><td>Embedded YouTube/Vimeo construction videos.</td></tr>
                    <tr><td><code>tbl_social</code></td><td><span class="badge" style="background: #0284c7;">16</span></td><td><code>social_id</code>, <code>social_name</code>, <code>social_url</code></td><td>Social media links (Facebook, Twitter, Instagram, LinkedIn).</td></tr>
                    <tr><td><code>tbl_subscriber</code></td><td><span class="badge" style="background: #0284c7;">5</span></td><td><code>subs_id</code>, <code>subs_email</code></td><td>Newsletter email subscribers.</td></tr>
                    <tr><td><code>tbl_message</code></td><td><span class="badge" style="background: #64748b;">0</span></td><td><code>id</code>, <code>subject</code>, <code>message</code></td><td>Platform-wide broadcast notification messages.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 6: POS SALE & DISCOUNT WORKFLOW                 -->
    <!-- ======================================================= -->
    <div id="pos-discount-engine" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #fee2e2; border-color: #fecaca;">
            <h3 class="dev-card-title" style="color: #991b1b;">
                <i class="fa fa-percent text-danger"></i> 6. POS Sale, Checkout &amp; Discount Security Workflow
            </h3>
            <span class="label label-danger">Anti-Fraud Engine</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 14px; color: #334155; line-height: 1.7; margin-bottom: 16px;">
                The POS terminal at <code>supplier/pos.php</code> and checkout at <code>supplier/checkout.php</code> include an anti-fraud security lock. Cashiers cannot arbitrarily lower prices without supervisor approval.
            </p>

            <div class="row" style="margin-bottom: 20px;">
                <div class="col-md-3">
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 16px; height: 100%;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <span class="step-badge">1</span>
                            <h5 style="margin: 0; font-weight: 800; color: #0f172a;">Add Items to POS</h5>
                        </div>
                        <p style="font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.5;">
                            Cashier scans barcodes or searches products. Quantities and variants are placed into the cart session.
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 16px; height: 100%;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <span class="step-badge" style="background: #f59e0b;">2</span>
                            <h5 style="margin: 0; font-weight: 800; color: #0f172a;">Request Discount</h5>
                        </div>
                        <p style="font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.5;">
                            Cashier inputs requested discount percentage. If it exceeds 0%, an authorization request is created in <code>tbl_discount_requests</code>.
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 16px; height: 100%;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <span class="step-badge" style="background: #dc2626;">3</span>
                            <h5 style="margin: 0; font-weight: 800; color: #0f172a;">Payment Locked</h5>
                        </div>
                        <p style="font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.5;">
                            The "Proceed to Checkout" button is locked. An AJAX polling loop checks every 3 seconds for supervisor action.
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 16px; height: 100%;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <span class="step-badge" style="background: #16a34a;">4</span>
                            <h5 style="margin: 0; font-weight: 800; color: #0f172a;">Supervisor Unlocks</h5>
                        </div>
                        <p style="font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.5;">
                            Supervisor enters PIN or approves in queue. Cart unlocks, discount applies, and cashier completes transaction.
                        </p>
                    </div>
                </div>
            </div>

            <div class="beginner-tip">
                <strong><i class="fa fa-info-circle"></i> Cart Persistence Feature:</strong>
                When a cashier is on <code>supplier/checkout.php</code> and clicks <strong>"Back to POS / Add More Items"</strong>, the cart is safely stored in <code>$_SESSION['pos_cart']</code>. The cashier can add more items on POS and return to checkout without losing any existing items!
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 7: ROLES & PERMISSIONS (RBAC)                   -->
    <!-- ======================================================= -->
    <div id="approval-hierarchy" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #eff6ff; border-color: #bfdbfe;">
            <h3 class="dev-card-title" style="color: #1e40af;">
                <i class="fa fa-sitemap text-primary"></i> 7. User Roles &amp; Permission Matrix (RBAC)
            </h3>
            <span class="label label-primary">Access Control</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                The platform provides 7 distinct staff roles. Below is the permission matrix showing what each role is allowed to perform:
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-dev text-center" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th class="text-left" width="220">Permission / Action</th>
                            <th>Admin</th>
                            <th>Manager</th>
                            <th>Supervisor</th>
                            <th>Cashier</th>
                            <th>Order Staff</th>
                            <th>Operator</th>
                            <th>Encoder</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-left"><strong>Access POS Terminal</strong></td>
                            <td><i class="fa fa-check text-success font-weight-bold"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                        </tr>
                        <tr>
                            <td class="text-left"><strong>Request POS Item Discount</strong></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                        </tr>
                        <tr>
                            <td class="text-left"><strong>Approve Discounts &gt; 5%</strong></td>
                            <td><i class="fa fa-check text-success font-weight-bold"></i> (Up to 20%)</td>
                            <td><i class="fa fa-check text-success font-weight-bold"></i> (Up to 15%)</td>
                            <td><i class="fa fa-check text-success font-weight-bold"></i> (Up to 10%)</td>
                            <td><i class="fa fa-times text-danger"></i></td>
                            <td><i class="fa fa-times text-danger"></i></td>
                            <td><i class="fa fa-times text-danger"></i></td>
                            <td><i class="fa fa-times text-danger"></i></td>
                        </tr>
                        <tr>
                            <td class="text-left"><strong>Edit Product Capital Price ($Ca$)</strong></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                        </tr>
                        <tr>
                            <td class="text-left"><strong>Perform Inventory Restock</strong></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="text-left"><strong>Manage Store Staff Accounts</strong></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-check text-success"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                            <td><i class="fa fa-times text-muted"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 8: SECTION 13 PRICING & STOCK MATHEMATICS       -->
    <!-- ======================================================= -->
    <div id="pricing-engine" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #fdf2f8; border-color: #fbcfe8;">
            <h3 class="dev-card-title" style="color: #9d174d;">
                <i class="fa fa-line-chart text-danger"></i> 8. Section 13 Pricing Rules &amp; Dynamic Stock Math
            </h3>
            <span class="label label-danger">Pricing Rules</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 14px; color: #334155; line-height: 1.7; margin-bottom: 16px;">
                The platform uses a deterministic formula system to handle product pricing, percentage markups, and restock price rollovers.
            </p>

            <div class="row">
                <div class="col-md-6">
                    <h4 style="font-weight: 800; font-size: 14px; color: #0f172a; margin: 0 0 10px 0;">Formula &amp; Variable Definitions</h4>
                    <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                        <thead>
                            <tr>
                                <th width="100">Symbol</th>
                                <th width="160">Database Field</th>
                                <th>Plain-English Meaning</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>$Ca$</strong></td><td><code>p_capital_price</code></td><td>Capital / Wholesale purchase cost per unit.</td></tr>
                            <tr><td><strong>$\text{₱}$</strong></td><td><code>p_markup</code></td><td>Markup percentage added to capital (e.g. 20%).</td></tr>
                            <tr><td><strong>$C$</strong></td><td><code>p_current_price</code></td><td>Current retail price displayed to customers. Formula: <code>$C = Ca \times (1 + \text{₱} / 100)$</code></td></tr>
                            <tr><td><strong>$N$</strong></td><td><code>p_new_price</code></td><td>Scheduled new price for the next incoming batch.</td></tr>
                            <tr><td><strong>$Q$</strong></td><td><code>p_qty</code></td><td>Current warehouse stock on hand.</td></tr>
                            <tr><td><strong>$S$</strong></td><td><code>p_s_level</code></td><td>Safety stock threshold. If $Q \le S$, a red alert badge is displayed!</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h4 style="font-weight: 800; font-size: 14px; color: #0f172a; margin: 0 0 10px 0;">Automated Restock Rollover Rule</h4>
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                        <p style="font-size: 13px; color: #334155; line-height: 1.6; margin: 0 0 10px 0;">
                            When new inventory arrives and the supplier clicks <strong>Restock</strong>:
                        </p>
                        <ol style="font-size: 13px; color: #475569; padding-left: 20px; line-height: 1.6; margin: 0;">
                            <li>New Quantity ($NQ$) is added to current stock ($Q = Q + NQ$).</li>
                            <li>If a New Price ($N$) was scheduled, it immediately becomes the active Current Price ($C = N$).</li>
                            <li>A historical audit entry is recorded in stock history logs.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 9: REUSABLE CLASSES, FUNCTIONS & CODE TEMPLATES -->
    <!-- ======================================================= -->
    <div id="code-templates" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #ecfdf5; border-color: #a7f3d0;">
            <h3 class="dev-card-title" style="color: #065f46;">
                <i class="fa fa-code text-success"></i> 9. Reusable Classes, Functions &amp; Page Templates Library
            </h3>
            <span class="label label-success">Developer Templates</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 14px; color: #334155; line-height: 1.7; margin-bottom: 18px;">
                Below is the standard, production-ready code templates library formatted in readable HTML with syntax highlighting. Copy and paste these boilerplates whenever you build a new Storefront Page, Admin Control Page, Supplier Tenant Module, or AJAX Endpoint!
            </p>

            <!-- Template 1: Storefront Public Page -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #0369a1; margin: 20px 0 8px 0;">
                <i class="fa fa-file-code-o"></i> 9.1 Standard Storefront Public Page Boilerplate (<code>/your-page.php</code>)
            </h4>
            <p style="font-size: 12.5px; color: #64748b; margin-bottom: 8px;">Use this boilerplate for new customer-facing pages (e.g., promotions, warranty info, project galleries).</p>
            <div class="dev-code-block">
<span style="color: #38bdf8;">&lt;?php</span>
<span style="color: #94a3b8;">// 1. Load global storefront header (includes session, PDO config, and navigation menu)</span>
<span style="color: #fb923c;">require_once</span>(<span style="color: #a78bfa;">'header.php'</span>);

<span style="color: #94a3b8;">// 2. Fetch data safely using parameterized PDO query</span>
$statement = $pdo->prepare(<span style="color: #38bdf8;">"SELECT * FROM tbl_product WHERE p_is_featured = 1 AND p_qty > 0 ORDER BY p_id DESC LIMIT 8"</span>);
$statement->execute();
$featured_products = $statement->fetchAll(PDO::FETCH_ASSOC);
<span style="color: #38bdf8;">?&gt;</span>

<span style="color: #94a3b8;">&lt;!-- Page Banner / Breadcrumb --&gt;</span>
&lt;div class=<span style="color: #a78bfa;">"page-banner"</span> style=<span style="color: #a78bfa;">"background: #0f172a; padding: 40px 0; color: #fff;"</span>&gt;
    &lt;div class=<span style="color: #a78bfa;">"container"</span>&gt;
        &lt;h1 style=<span style="color: #a78bfa;">"font-weight: 800; margin: 0;"</span>&gt;Featured Construction Materials&lt;/h1&gt;
    &lt;/div&gt;
&lt;/div&gt;

<span style="color: #94a3b8;">&lt;!-- Main Content Container --&gt;</span>
&lt;div class=<span style="color: #a78bfa;">"page"</span> style=<span style="color: #a78bfa;">"padding: 40px 0;"</span>&gt;
    &lt;div class=<span style="color: #a78bfa;">"container"</span>&gt;
        &lt;div class=<span style="color: #a78bfa;">"row"</span>&gt;
            <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">foreach</span> ($featured_products <span style="color: #fb923c;">as</span> $prod): <span style="color: #38bdf8;">?&gt;</span>
                &lt;div class=<span style="color: #a78bfa;">"col-md-3 col-sm-6"</span> style=<span style="color: #a78bfa;">"margin-bottom: 25px;"</span>&gt;
                    &lt;div class=<span style="color: #a78bfa;">"product-card"</span> style=<span style="color: #a78bfa;">"border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;"</span>&gt;
                        &lt;h4 style=<span style="color: #a78bfa;">"font-weight: 700; font-size: 15px;"</span>&gt;<span style="color: #38bdf8;">&lt;?=</span> htmlspecialchars($prod[<span style="color: #a78bfa;">'p_name'</span>]) <span style="color: #38bdf8;">?&gt;</span>&lt;/h4&gt;
                        &lt;p style=<span style="color: #a78bfa;">"color: #0284c7; font-weight: 800;"</span>&gt;₱<span style="color: #38bdf8;">&lt;?=</span> number_format((float)$prod[<span style="color: #a78bfa;">'p_current_price'</span>], 2) <span style="color: #38bdf8;">?&gt;</span>&lt;/p&gt;
                        &lt;a href=<span style="color: #a78bfa;">"product.php?id=&lt;?= $prod['p_id'] ?&gt;"</span> class=<span style="color: #a78bfa;">"btn btn-primary btn-sm btn-block"</span>&gt;View Product&lt;/a&gt;
                    &lt;/div&gt;
                &lt;/div&gt;
            <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">endforeach</span>; <span style="color: #38bdf8;">?&gt;</span>
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;

<span style="color: #38bdf8;">&lt;?php</span>
<span style="color: #94a3b8;">// 3. Load global storefront footer</span>
<span style="color: #fb923c;">require_once</span>(<span style="color: #a78bfa;">'footer.php'</span>);
<span style="color: #38bdf8;">?&gt;</span>
            </div>

            <!-- Template 2: Admin Subsystem Page -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #1e40af; margin: 28px 0 8px 0;">
                <i class="fa fa-file-code-o"></i> 9.2 Standard Super Admin Page Boilerplate (<code>/admin/your-admin-page.php</code>)
            </h4>
            <p style="font-size: 12.5px; color: #64748b; margin-bottom: 8px;">Use this boilerplate for new administrative panels with RBAC security, CSRF protection, and AdminLTE layout.</p>
            <div class="dev-code-block">
<span style="color: #38bdf8;">&lt;?php</span>
<span style="color: #94a3b8;">// 1. Include admin header (validates admin session authentication)</span>
<span style="color: #fb923c;">require_once</span>(<span style="color: #a78bfa;">'header.php'</span>);

<span style="color: #94a3b8;">// 2. Handle POST submissions with CSRF token verification</span>
$success_message = <span style="color: #a78bfa;">''</span>;
$error_message = <span style="color: #a78bfa;">''</span>;

<span style="color: #fb923c;">if</span> (isset($_POST[<span style="color: #a78bfa;">'form_submit'</span>])) {
    <span style="color: #94a3b8;">// Validate CSRF token</span>
    <span style="color: #fb923c;">if</span> (!isset($_POST[<span style="color: #a78bfa;">'csrf_token'</span>]) || $_POST[<span style="color: #a78bfa;">'csrf_token'</span>] !== $_SESSION[<span style="color: #a78bfa;">'csrf_token'</span>]) {
        $error_message = <span style="color: #a78bfa;">'Security validation failed. Please refresh and try again.'</span>;
    } <span style="color: #fb923c;">else</span> {
        $setting_val = trim($_POST[<span style="color: #a78bfa;">'setting_value'</span>]);
        $statement = $pdo->prepare(<span style="color: #38bdf8;">"UPDATE tbl_settings SET footer_about = :val WHERE id = 1"</span>);
        $statement->execute([<span style="color: #a78bfa;">':val'</span> => $setting_val]);
        $success_message = <span style="color: #a78bfa;">'Settings updated successfully!'</span>;
    }
}
<span style="color: #38bdf8;">?&gt;</span>

<span style="color: #94a3b8;">&lt;!-- Admin Content Header --&gt;</span>
&lt;section class=<span style="color: #a78bfa;">"content-header"</span>&gt;
    &lt;h1 style=<span style="color: #a78bfa;">"font-weight: 800;"</span>&gt;&lt;i class=<span style="color: #a78bfa;">"fa fa-cogs"</span>&gt;&lt;/i&gt; Custom Admin Module&lt;/h1&gt;
&lt;/section&gt;

<span style="color: #94a3b8;">&lt;!-- Main Content Box --&gt;</span>
&lt;section class=<span style="color: #a78bfa;">"content"</span>&gt;
    <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">if</span> ($success_message): <span style="color: #38bdf8;">?&gt;</span>
        &lt;div class=<span style="color: #a78bfa;">"alert alert-success alert-dismissible"</span>&gt;<span style="color: #38bdf8;">&lt;?=</span> $success_message <span style="color: #38bdf8;">?&gt;</span>&lt;/div&gt;
    <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">endif</span>; <span style="color: #38bdf8;">?&gt;</span>
    <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">if</span> ($error_message): <span style="color: #38bdf8;">?&gt;</span>
        &lt;div class=<span style="color: #a78bfa;">"alert alert-danger alert-dismissible"</span>&gt;<span style="color: #38bdf8;">&lt;?=</span> $error_message <span style="color: #38bdf8;">?&gt;</span>&lt;/div&gt;
    <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">endif</span>; <span style="color: #38bdf8;">?&gt;</span>

    &lt;div class=<span style="color: #a78bfa;">"box box-primary"</span> style=<span style="color: #a78bfa;">"border-radius: 8px;"</span>&gt;
        &lt;div class=<span style="color: #a78bfa;">"box-header with-border"</span>&gt;
            &lt;h3 class=<span style="color: #a78bfa;">"box-title"</span> style=<span style="color: #a78bfa;">"font-weight: 700;"</span>&gt;Module Configuration&lt;/h3&gt;
        &lt;/div&gt;
        &lt;form action=<span style="color: #a78bfa;">""</span> method=<span style="color: #a78bfa;">"POST"</span> class=<span style="color: #a78bfa;">"form-horizontal"</span>&gt;
            &lt;input type=<span style="color: #a78bfa;">"hidden"</span> name=<span style="color: #a78bfa;">"csrf_token"</span> value=<span style="color: #a78bfa;">"&lt;?= $_SESSION['csrf_token'] ?&gt;"</span>&gt;
            &lt;div class=<span style="color: #a78bfa;">"box-body"</span>&gt;
                &lt;div class=<span style="color: #a78bfa;">"form-group"</span>&gt;
                    &lt;label class=<span style="color: #a78bfa;">"col-sm-3 control-label"</span>&gt;Setting Value&lt;/label&gt;
                    &lt;div class=<span style="color: #a78bfa;">"col-sm-6"</span>&gt;
                        &lt;input type=<span style="color: #a78bfa;">"text"</span> name=<span style="color: #a78bfa;">"setting_value"</span> class=<span style="color: #a78bfa;">"form-control"</span> required&gt;
                    &lt;/div&gt;
                &lt;/div&gt;
            &lt;/div&gt;
            &lt;div class=<span style="color: #a78bfa;">"box-footer"</span>&gt;
                &lt;button type=<span style="color: #a78bfa;">"submit"</span> name=<span style="color: #a78bfa;">"form_submit"</span> class=<span style="color: #a78bfa;">"btn btn-success"</span>&gt;&lt;i class=<span style="color: #a78bfa;">"fa fa-save"</span>&gt;&lt;/i&gt; Save Changes&lt;/button&gt;
            &lt;/div&gt;
        &lt;/form&gt;
    &lt;/div&gt;
&lt;/section&gt;

<span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">require_once</span>(<span style="color: #a78bfa;">'footer.php'</span>); <span style="color: #38bdf8;">?&gt;</span>
            </div>

            <!-- Template 3: Supplier Subsystem Page -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #166534; margin: 28px 0 8px 0;">
                <i class="fa fa-file-code-o"></i> 9.3 Standard Supplier Tenant Page Boilerplate (<code>/supplier/your-supplier-page.php</code>)
            </h4>
            <p style="font-size: 12.5px; color: #64748b; margin-bottom: 8px;">Use this boilerplate for new store management pages with strict multi-tenancy parameterization.</p>
            <div class="dev-code-block">
<span style="color: #38bdf8;">&lt;?php</span>
<span style="color: #94a3b8;">// 1. Include supplier header (authenticates staff user and resolves $supplier_id)</span>
<span style="color: #fb923c;">require_once</span>(<span style="color: #a78bfa;">'header.php'</span>);

<span style="color: #94a3b8;">// 2. Multi-tenancy isolation: Always query by logged-in $supplier_id!</span>
$supplier_id = (int)$_SESSION[<span style="color: #a78bfa;">'supplier_user'</span>][<span style="color: #a78bfa;">'supplier_id'</span>];

<span style="color: #94a3b8;">// 3. Fetch products belonging strictly to this supplier tenant</span>
$stmt = $pdo->prepare(<span style="color: #38bdf8;">"SELECT p_id, p_name, p_capital_price, p_markup, p_current_price, p_qty, p_s_level 
                      FROM tbl_product 
                      WHERE supplier_id = :supplier_id 
                      ORDER BY p_id DESC"</span>);
$stmt->execute([<span style="color: #a78bfa;">':supplier_id'</span> => $supplier_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
<span style="color: #38bdf8;">?&gt;</span>

&lt;section class=<span style="color: #a78bfa;">"content-header"</span>&gt;
    &lt;h1 style=<span style="color: #a78bfa;">"font-weight: 800;"</span>&gt;&lt;i class=<span style="color: #a78bfa;">"fa fa-cubes"</span>&gt;&lt;/i&gt; Supplier Inventory Manager&lt;/h1&gt;
&lt;/section&gt;

&lt;section class=<span style="color: #a78bfa;">"content"</span>&gt;
    &lt;div class=<span style="color: #a78bfa;">"box box-info"</span> style=<span style="color: #a78bfa;">"border-radius: 8px;"</span>&gt;
        &lt;div class=<span style="color: #a78bfa;">"box-body table-responsive"</span>&gt;
            &lt;table class=<span style="color: #a78bfa;">"table table-bordered table-striped"</span>&gt;
                &lt;thead&gt;
                    &lt;tr&gt;
                        &lt;th&gt;#&lt;/th&gt;
                        &lt;th&gt;Product Name&lt;/th&gt;
                        &lt;th&gt;Capital Price ($Ca$)&lt;/th&gt;
                        &lt;th&gt;Current Price ($C$)&lt;/th&gt;
                        &lt;th&gt;Stock ($Q$)&lt;/th&gt;
                        &lt;th&gt;Status&lt;/th&gt;
                    &lt;/tr&gt;
                &lt;/thead&gt;
                &lt;tbody&gt;
                    <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">foreach</span> ($items <span style="color: #fb923c;">as</span> $idx => $row): <span style="color: #38bdf8;">?&gt;</span>
                        &lt;tr&gt;
                            &lt;td&gt;<span style="color: #38bdf8;">&lt;?=</span> $idx + 1 <span style="color: #38bdf8;">?&gt;</span>&lt;/td&gt;
                            &lt;td&gt;&lt;strong&gt;<span style="color: #38bdf8;">&lt;?=</span> htmlspecialchars($row[<span style="color: #a78bfa;">'p_name'</span>]) <span style="color: #38bdf8;">?&gt;</span>&lt;/strong&gt;&lt;/td&gt;
                            &lt;td&gt;₱<span style="color: #38bdf8;">&lt;?=</span> number_format((float)$row[<span style="color: #a78bfa;">'p_capital_price'</span>], 2) <span style="color: #38bdf8;">?&gt;</span>&lt;/td&gt;
                            &lt;td style=<span style="color: #a78bfa;">"color: #16a34a; font-weight: 700;"</span>&gt;₱<span style="color: #38bdf8;">&lt;?=</span> number_format((float)$row[<span style="color: #a78bfa;">'p_current_price'</span>], 2) <span style="color: #38bdf8;">?&gt;</span>&lt;/td&gt;
                            &lt;td&gt;<span style="color: #38bdf8;">&lt;?=</span> (int)$row[<span style="color: #a78bfa;">'p_qty'</span>] <span style="color: #38bdf8;">?&gt;</span>&lt;/td&gt;
                            &lt;td&gt;
                                <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">if</span> ($row[<span style="color: #a78bfa;">'p_qty'</span>] &lt;= $row[<span style="color: #a78bfa;">'p_s_level'</span>]): <span style="color: #38bdf8;">?&gt;</span>
                                    &lt;span class=<span style="color: #a78bfa;">"label label-danger"</span>&gt;Low Stock Alert&lt;/span&gt;
                                <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">else</span>: <span style="color: #38bdf8;">?&gt;</span>
                                    &lt;span class=<span style="color: #a78bfa;">"label label-success"</span>&gt;Healthy&lt;/span&gt;
                                <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">endif</span>; <span style="color: #38bdf8;">?&gt;</span>
                            &lt;/td&gt;
                        &lt;/tr&gt;
                    <span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">endforeach</span>; <span style="color: #38bdf8;">?&gt;</span>
                &lt;/tbody&gt;
            &lt;/table&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/section&gt;

<span style="color: #38bdf8;">&lt;?php</span> <span style="color: #fb923c;">require_once</span>(<span style="color: #a78bfa;">'footer.php'</span>); <span style="color: #38bdf8;">?&gt;</span>
            </div>

            <!-- Template 4: AJAX REST API Endpoint -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #7c2d12; margin: 28px 0 8px 0;">
                <i class="fa fa-file-code-o"></i> 9.4 Standard Asynchronous AJAX / JSON API Endpoint (<code>/supplier/ajax-example.php</code>)
            </h4>
            <p style="font-size: 12.5px; color: #64748b; margin-bottom: 8px;">Use this boilerplate for new asynchronous JSON endpoints called by jQuery/fetch scripts.</p>
            <div class="dev-code-block">
<span style="color: #38bdf8;">&lt;?php</span>
<span style="color: #94a3b8;">// 1. Initialize output buffer and session</span>
ob_start();
session_start();
<span style="color: #fb923c;">require_once</span>(<span style="color: #a78bfa;">'inc/config.php'</span>);
<span style="color: #fb923c;">require_once</span>(<span style="color: #a78bfa;">'inc/functions.php'</span>);

<span style="color: #94a3b8;">// 2. Set strict JSON response headers</span>
header(<span style="color: #a78bfa;">'Content-Type: application/json; charset=utf-8'</span>);

<span style="color: #94a3b8;">// 3. Authenticate session</span>
<span style="color: #fb923c;">if</span> (!isset($_SESSION[<span style="color: #a78bfa;">'supplier_user'</span>])) {
    <span style="color: #4ade80;">echo</span> json_encode([<span style="color: #a78bfa;">'status'</span> => <span style="color: #a78bfa;">'error'</span>, <span style="color: #a78bfa;">'message'</span> => <span style="color: #a78bfa;">'Unauthorized access'</span>]);
    <span style="color: #fb923c;">exit</span>;
}

$supplier_id = (int)$_SESSION[<span style="color: #a78bfa;">'supplier_user'</span>][<span style="color: #a78bfa;">'supplier_id'</span>];
$action = isset($_POST[<span style="color: #a78bfa;">'action'</span>]) ? trim($_POST[<span style="color: #a78bfa;">'action'</span>]) : <span style="color: #a78bfa;">''</span>;

<span style="color: #fb923c;">try</span> {
    <span style="color: #fb923c;">if</span> ($action === <span style="color: #a78bfa;">'search'</span>) {
        $keyword = isset($_POST[<span style="color: #a78bfa;">'q'</span>]) ? trim($_POST[<span style="color: #a78bfa;">'q'</span>]) : <span style="color: #a78bfa;">''</span>;
        $stmt = $pdo->prepare(<span style="color: #38bdf8;">"SELECT p_id, p_name, p_current_price, p_qty 
                              FROM tbl_product 
                              WHERE supplier_id = :sid AND (p_name ILIKE :q OR p_id::text = :exact_q) 
                              LIMIT 10"</span>);
        $stmt->execute([
            <span style="color: #a78bfa;">':sid'</span> => $supplier_id,
            <span style="color: #a78bfa;">':q'</span> => <span style="color: #a78bfa;">'%'</span> . $keyword . <span style="color: #a78bfa;">'%'</span>,
            <span style="color: #a78bfa;">':exact_q'</span> => $keyword
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        <span style="color: #4ade80;">echo</span> json_encode([<span style="color: #a78bfa;">'status'</span> => <span style="color: #a78bfa;">'success'</span>, <span style="color: #a78bfa;">'data'</span> => $results]);
        <span style="color: #fb923c;">exit</span>;
    }

    <span style="color: #4ade80;">echo</span> json_encode([<span style="color: #a78bfa;">'status'</span> => <span style="color: #a78bfa;">'error'</span>, <span style="color: #a78bfa;">'message'</span> => <span style="color: #a78bfa;">'Invalid action'</span>]);
} <span style="color: #fb923c;">catch</span> (Exception $e) {
    <span style="color: #4ade80;">echo</span> json_encode([<span style="color: #a78bfa;">'status'</span> => <span style="color: #a78bfa;">'error'</span>, <span style="color: #a78bfa;">'message'</span> => $e->getMessage()]);
}
<span style="color: #38bdf8;">?&gt;</span>
            </div>

            <!-- Core Functions Quick Reference -->
            <h4 style="font-weight: 800; font-size: 14.5px; color: #0f172a; margin: 30px 0 10px 0;">
                <i class="fa fa-book text-primary"></i> 9.5 Core Helper Functions Reference Library
            </h4>
            <table class="table table-bordered table-dev" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th width="260">Function Signature</th>
                        <th width="150">Defined In</th>
                        <th>Description &amp; Return Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>normalize_supplier_role($role)</code></td>
                        <td><code>inc/functions.php</code></td>
                        <td>Normalizes raw database string into standard role constant (<code>ADMIN</code>, <code>MANAGER</code>, <code>SUPERVISOR</code>, <code>CASHIER</code>, <code>OPERATOR</code>, <code>ENCODER</code>).</td>
                    </tr>
                    <tr>
                        <td><code>is_admin_or_manager_role($role)</code></td>
                        <td><code>inc/functions.php</code></td>
                        <td>Returns <code>true</code> if role has administrative governance over the tenant store.</td>
                    </tr>
                    <tr>
                        <td><code>is_supplier_approver()</code></td>
                        <td><code>inc/functions.php</code></td>
                        <td>Returns <code>true</code> if the currently logged-in user is authorized to approve POS discount requests.</td>
                    </tr>
                    <tr>
                        <td><code>has_pos_access($user)</code></td>
                        <td><code>inc/functions.php</code></td>
                        <td>Checks user role and <code>pos_access</code> database flag to allow/block terminal entry.</td>
                    </tr>
                    <tr>
                        <td><code>ensure_supplier_user_schema($pdo)</code></td>
                        <td><code>inc/functions.php</code></td>
                        <td>Auto-migrates and guarantees all Postgres schema columns exist dynamically at runtime.</td>
                    </tr>
                    <tr>
                        <td><code>parseConstructionProductDetails($raw_name)</code></td>
                        <td><code>inc/functions.php</code></td>
                        <td>Regex parser that extracts color, finish, diameter ($D$), voltage ($V$), and power ($W$) from raw strings.</td>
                    </tr>
                    <tr>
                        <td><code>send_system_email($to, $subject, $html)</code></td>
                        <td><code>inc/functions.php</code></td>
                        <td>Dispatches HTML email via SMTP or fallback <code>mail()</code> and logs to <code>assets/uploads/emails.log</code>.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- SECTION 10: REST & AJAX API REFERENCE                   -->
    <!-- ======================================================= -->
    <div id="api-reference" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #f0f9ff; border-color: #bae6fd;">
            <h3 class="dev-card-title" style="color: #0369a1;">
                <i class="fa fa-cogs text-info"></i> 10. REST &amp; AJAX API Endpoints Reference
            </h3>
            <span class="label label-info">JSON APIs</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                Below are the core AJAX / REST API endpoints used by JavaScript for dynamic page updates without refreshing:
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="80">Method</th>
                            <th width="280">Endpoint URL</th>
                            <th width="220">Parameters</th>
                            <th>Description &amp; Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge-endpoint badge-post">POST</span></td>
                            <td><code>supplier/ajax-approve-discount.php</code></td>
                            <td><code>request_id</code>, <code>action</code>, <code>pin</code></td>
                            <td>Supervisor authorizes or rejects a pending POS discount request. Returns <code>{"status": "success"}</code>.</td>
                        </tr>
                        <tr>
                            <td><span class="badge-endpoint badge-get">GET</span></td>
                            <td><code>supplier/ajax-check-discount.php</code></td>
                            <td><code>request_id</code></td>
                            <td>Polled by POS terminal every 3s to check if discount has been approved.</td>
                        </tr>
                        <tr>
                            <td><span class="badge-endpoint badge-get">GET</span></td>
                            <td><code>ajax-get-town.php</code></td>
                            <td><code>country_id</code></td>
                            <td>Returns JSON list of municipalities in Western Visayas for dynamic dropdowns.</td>
                        </tr>
                        <tr>
                            <td><span class="badge-endpoint badge-get">GET</span></td>
                            <td><code>ajax-get-brgy.php</code></td>
                            <td><code>town_id</code></td>
                            <td>Returns JSON list of 60 Santa Barbara barangays to recalculate delivery fee.</td>
                        </tr>
                        <tr>
                            <td><span class="badge-endpoint badge-post">POST</span></td>
                            <td><code>supplier/ajax-search-product.php</code></td>
                            <td><code>keyword</code>, <code>supplier_id</code></td>
                            <td>Live barcode and name search powering the POS product picker.</td>
                        </tr>
                        <tr>
                            <td><span class="badge-endpoint badge-post">POST</span></td>
                            <td><code>supplier/ajax-pos-hold.php</code></td>
                            <td><code>cart_data</code>, <code>customer_name</code></td>
                            <td>Saves a currently open POS sale to the hold list so another customer can be served.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- ====================================
         11. C4 ARCHITECTURE MODELS
         ==================================== -->
    <div id="c4-architecture" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #f0fdf4; border-color: #bbf7d0;">
            <h3 class="dev-card-title" style="color: #166534;">
                <i class="fa fa-sitemap text-success"></i> 11. C4 Architecture Models (Context, Container &amp; Components)
            </h3>
            <span class="label label-success">C4 Model</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                The platform is architected according to the C4 Model hierarchy to define system context, runtime containers, and internal components:
            </p>

            <div class="row">
                <div class="col-md-6">
                    <div class="box box-solid" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div class="box-header with-border" style="background: #f8fafc;">
                            <h4 class="box-title" style="font-weight: 700; font-size: 14px;"><i class="fa fa-globe text-primary"></i> C4 Level 1: System Context</h4>
                        </div>
                        <div class="box-body" style="font-size: 13px; line-height: 1.6;">
                            <ul style="padding-left: 20px;">
                                <li><strong>Public Customer:</strong> Browses multi-supplier catalog, puts building materials in cart, checks out via Stripe, PayPal, or Bank Transfer.</li>
                                <li><strong>Supplier Staff / Cashier:</strong> Manages store catalog, executes in-store counter POS sales, requests restock from central depots.</li>
                                <li><strong>SaaS Super Admin:</strong> Manages global supplier onboarding, sets discount ceilings, oversees system activity logs.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="box box-solid" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div class="box-header with-border" style="background: #f8fafc;">
                            <h4 class="box-title" style="font-weight: 700; font-size: 14px;"><i class="fa fa-cubes text-success"></i> C4 Level 2: Container Model</h4>
                        </div>
                        <div class="box-body" style="font-size: 13px; line-height: 1.6;">
                            <ul style="padding-left: 20px;">
                                <li><strong>Web Server Container (<code>econstructionsite-web</code>):</strong> Apache 2.4 + PHP 7.4 running MVC controllers, storefront, admin back-office, and AJAX APIs.</li>
                                <li><strong>Database Container (<code>econstructionsite-db</code>):</strong> PostgreSQL 15 running <code>ecomDB</code> on port 5432 with 40 relational tables.</li>
                                <li><strong>Storage Volume (<code>/assets/uploads/</code>):</strong> Persistent file storage for product photos, sliders, and PDF invoices.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================
         12. AUTHORITATIVE BUSINESS RULES MATRIX
         ==================================== -->
    <div id="business-rules" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #fffbeb; border-color: #fde68a;">
            <h3 class="dev-card-title" style="color: #92400e;">
                <i class="fa fa-gavel text-warning"></i> 12. Authoritative Business Rules &amp; Enforcement Matrix
            </h3>
            <span class="label label-warning">Business Rules</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                Below is the verified business rules catalog governing tenant isolation, discounts, stock, and orders:
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="140">Rule ID</th>
                            <th width="180">Category</th>
                            <th>Rule Specification &amp; Business Logic</th>
                            <th width="200">Enforcement Location</th>
                            <th width="90">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge" style="background: #ef4444;">BR-TENANT-001</span></td>
                            <td><strong>Tenant Isolation</strong></td>
                            <td>Supplier staff may only query, edit, or delete data (products, orders, staff, stock) belonging to their authenticated <code>supplier_id</code>.</td>
                            <td><code>supplier/inc/config.php</code> &amp; SQL queries</td>
                            <td><span class="label label-success">Verified</span></td>
                        </tr>
                        <tr>
                            <td><span class="badge" style="background: #f59e0b;">BR-DISCOUNT-001</span></td>
                            <td><strong>Discount Ceiling</strong></td>
                            <td>No supplier discount rule or POS manual discount override may exceed <code>tbl_supplier.discount_ceiling_percentage</code>.</td>
                            <td><code>supplier/checkout.php</code> &amp; <code>discount-*.php</code></td>
                            <td><span class="label label-success">Verified</span></td>
                        </tr>
                        <tr>
                            <td><span class="badge" style="background: #f59e0b;">BR-DISCOUNT-002</span></td>
                            <td><strong>Supervisor PIN Approval</strong></td>
                            <td>POS discounts exceeding cashier limits (>5%) require supervisor PIN authorization before checkout can proceed.</td>
                            <td><code>supplier/pos.php</code> (Modal Check)</td>
                            <td><span class="label label-success">Verified</span></td>
                        </tr>
                        <tr>
                            <td><span class="badge" style="background: #10b981;">BR-INVENTORY-001</span></td>
                            <td><strong>Atomic Stock Deductions</strong></td>
                            <td>Both online marketplace checkouts and in-store POS transactions decrement inventory from the same central <code>tbl_product.p_qty</code> column.</td>
                            <td><code>checkout.php</code> &amp; <code>supplier/checkout.php</code></td>
                            <td><span class="label label-success">Verified</span></td>
                        </tr>
                        <tr>
                            <td><span class="badge" style="background: #3b82f6;">BR-ORDER-001</span></td>
                            <td><strong>Immutable Activity Log</strong></td>
                            <td>Every lifecycle state transition (Pending &rarr; Processing &rarr; Shipped &rarr; Delivered &rarr; Cancelled) writes an immutable record to <code>tbl_order_activity_log</code>.</td>
                            <td><code>supplier/order.php</code> &amp; <code>admin/order.php</code></td>
                            <td><span class="label label-success">Verified</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ====================================
         13. GAP ANALYSIS & SECURITY FINDINGS
         ==================================== -->
    <div id="gap-analysis" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #fef2f2; border-color: #fecaca;">
            <h3 class="dev-card-title" style="color: #991b1b;">
                <i class="fa fa-exclamation-triangle text-danger"></i> 13. System Gap Analysis &amp; Security Findings
            </h3>
            <span class="label label-danger">Audit &amp; Gaps</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                Documented discrepancies between current implementation and target enterprise state:
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="130">Finding ID</th>
                            <th width="150">Area</th>
                            <th>Current State Finding &amp; Evidence</th>
                            <th width="100">Severity</th>
                            <th>Recommended Target State Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>GAP-SEC-001</code></td>
                            <td>Authentication</td>
                            <td>Legacy <code>MD5</code> password hashing in <code>tbl_user</code>, <code>tbl_customer</code>, <code>tbl_supplier_users</code>.</td>
                            <td><span class="label label-danger">HIGH</span></td>
                            <td>Migrate to PHP <code>password_hash()</code> with Argon2id / Bcrypt algorithm.</td>
                        </tr>
                        <tr>
                            <td><code>GAP-DB-001</code></td>
                            <td>Database Constraints</td>
                            <td>Foreign key links are maintained logically in queries; explicit DDL <code>ON DELETE CASCADE</code> constraints not yet formalized.</td>
                            <td><span class="label label-warning">MEDIUM</span></td>
                            <td>Add explicit foreign key constraints in next PostgreSQL migration.</td>
                        </tr>
                        <tr>
                            <td><code>GAP-INV-001</code></td>
                            <td>Multi-Depot Stock</td>
                            <td>Stock is tracked globally per product (<code>p_qty</code>) rather than per physical branch depot.</td>
                            <td><span class="label label-info">LOW</span></td>
                            <td>Introduce <code>tbl_store_inventory</code> table for granular branch inventory balances.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ====================================
         14. ARCHITECTURE DECISION RECORDS (ADRS)
         ==================================== -->
    <div id="adr-records" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #faf5ff; border-color: #e9d5ff;">
            <h3 class="dev-card-title" style="color: #6b21a8;">
                <i class="fa fa-balance-scale text-purple"></i> 14. Architecture Decision Records (ADRs)
            </h3>
            <span class="label label-primary" style="background: #9333ea;">ADR History</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                Historical record of significant architectural decisions implemented in the platform:
            </p>

            <div class="row">
                <div class="col-md-4">
                    <div class="box box-solid" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div class="box-header with-border" style="background: #f8fafc;">
                            <h5 style="font-weight: 700; margin: 0;">ADR-001: PostgreSQL 15 Migration</h5>
                        </div>
                        <div class="box-body" style="font-size: 12.5px;">
                            <p><strong>Status:</strong> <span class="label label-success">Accepted</span></p>
                            <p>Migrated entire database from MySQL 5.7 to PostgreSQL 15 (<code>ecomDB</code>) for concurrency and JSON construction spec support.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="box box-solid" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div class="box-header with-border" style="background: #f8fafc;">
                            <h5 style="font-weight: 700; margin: 0;">ADR-002: Tenant Discount Ceilings</h5>
                        </div>
                        <div class="box-body" style="font-size: 12.5px;">
                            <p><strong>Status:</strong> <span class="label label-success">Accepted</span></p>
                            <p>Enforce <code>discount_ceiling_percentage</code> across store discount rules and cashier POS overrides to protect platform margins.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="box box-solid" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div class="box-header with-border" style="background: #f8fafc;">
                            <h5 style="font-weight: 700; margin: 0;">ADR-003: Immutable Activity Logging</h5>
                        </div>
                        <div class="box-body" style="font-size: 12.5px;">
                            <p><strong>Status:</strong> <span class="label label-success">Accepted</span></p>
                            <p>Record all order state transitions and cashier discount approvals in <code>tbl_order_activity_log</code> for dispute resolution.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================
         15. AI CODING AGENT DEVELOPMENT RULES
         ==================================== -->
    <div id="ai-development" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #f0fdfa; border-color: #99f6e4;">
            <h3 class="dev-card-title" style="color: #0f766e;">
                <i class="fa fa-robot text-teal"></i> 15. AI Coding Agent Development Guidelines &amp; Rules
            </h3>
            <span class="label label-info" style="background: #0d9488;">AI Guidelines</span>
        </div>
        <div class="dev-card-body">
            <div class="callout callout-info" style="border-left-color: #0d9488; background-color: #ccfbf1; color: #115e59; border-radius: 6px;">
                <h4 style="font-weight: 700;"><i class="fa fa-lightbulb-o"></i> Instructions for Future AI Assistants</h4>
                <p style="font-size: 13px;">
                    When extending or modifying the eConstruction Supply codebase, follow these non-negotiable principles:
                </p>
            </div>

            <ol style="font-size: 13.5px; color: #334155; line-height: 1.8; padding-left: 20px;">
                <li><strong>Consult Documentation First:</strong> Always read the corresponding domain files in <code>Documentation/</code> before writing code.</li>
                <li><strong>Enforce Multi-Tenant Scoping:</strong> Every supplier query must explicitly filter by <code>supplier_id = ?</code> bound to <code>$_SESSION['supplier']['id']</code>.</li>
                <li><strong>PostgreSQL 15 PDO Binding:</strong> Use standard PostgreSQL syntax and parameterized prepared statements (never interpolate raw variables).</li>
                <li><strong>Preserve Existing Signatures:</strong> Do not break or delete existing helper functions in <code>/admin/inc/</code> or <code>/supplier/inc/</code>.</li>
                <li><strong>Log Lifecycle Events:</strong> Any order status update must insert a record into <code>tbl_order_activity_log</code>.</li>
                <li><strong>Verify Deployment:</strong> Always run <code>docker exec econstructionsite-web php -l &lt;filepath&gt;</code> before concluding.</li>
            </ol>
        </div>
    </div>

    <!-- ====================================
         16. REQUIREMENTS TRACEABILITY & QA TEST CASES
         ==================================== -->
    <div id="test-cases" class="dev-card filterable-item">
        <div class="dev-card-header" style="background: #f1f5f9; border-color: #cbd5e1;">
            <h3 class="dev-card-title" style="color: #334155;">
                <i class="fa fa-check-square-o text-primary"></i> 16. Requirements Traceability Matrix &amp; QA Test Suite
            </h3>
            <span class="label label-default" style="background: #64748b; color: #fff;">QA &amp; Tests</span>
        </div>
        <div class="dev-card-body">
            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                Key test cases verifying tenant isolation, discount ceilings, and order integrity:
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-dev" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th width="120">Test ID</th>
                            <th width="160">Feature Area</th>
                            <th>Test Description &amp; Execution Steps</th>
                            <th>Expected Behavior</th>
                            <th width="90">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>TC-TENANT-01</code></td>
                            <td>Tenant Isolation</td>
                            <td>Authenticate as Supplier A; attempt direct GET request to edit Supplier B's product ID.</td>
                            <td>Access denied / redirected; Supplier B details never exposed.</td>
                            <td><span class="label label-success">PASSED</span></td>
                        </tr>
                        <tr>
                            <td><code>TC-POS-02</code></td>
                            <td>Discount Ceiling</td>
                            <td>Store ceiling = 20%; Cashier requests 25% discount override with valid Manager PIN.</td>
                            <td>Rejected by system: *"Requested discount exceeds approved store ceiling"*.</td>
                            <td><span class="label label-success">PASSED</span></td>
                        </tr>
                        <tr>
                            <td><code>TC-STOCK-01</code></td>
                            <td>Atomic Deduction</td>
                            <td>Place POS sale for 5 units of product with <code>p_qty = 20</code>.</td>
                            <td><code>p_qty</code> atomically decremented to 15 in <code>tbl_product</code>.</td>
                            <td><span class="label label-success">PASSED</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Live Search JavaScript -->
<script>
function filterDocumentation() {
    var query = document.getElementById('docSearch').value.toLowerCase().trim();
    var cards = document.querySelectorAll('.filterable-item');
    var rows = document.querySelectorAll('.table-dev tbody tr, .tree-file-row');

    if (query === '') {
        cards.forEach(function(card) { card.style.display = ''; });
        rows.forEach(function(row) { row.style.display = ''; });
        return;
    }

    rows.forEach(function(row) {
        var text = row.innerText.toLowerCase();
        if (text.indexOf(query) !== -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    cards.forEach(function(card) {
        var text = card.innerText.toLowerCase();
        if (text.indexOf(query) !== -1) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php require_once('footer.php'); ?>
