<?php require_once('header.php'); ?>

<?php
// Multi-tenant authorization check
$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$statement_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ?");
$statement_s->execute([$supplier_id]);
$current_supplier_data = $statement_s->fetch(PDO::FETCH_ASSOC);
$store_name = $current_supplier_data['supplier_name'] ?? 'E-Construction Supply Store';
$store_address = $current_supplier_data['supplier_address'] ?? '';
$store_phone = $current_supplier_data['supplier_phone'] ?? '09612735733';
?>

<section class="content-header" style="padding: 15px 20px 10px 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a;">
                <i class="fa fa-print" style="color: #0284c7; margin-right: 6px;"></i> POS Printer Settings
                <small style="font-size: 13px; color: #64748b; font-weight: 600;">Configure Receipt &amp; PO Voucher Printers (58mm, 80mm, A4)</small>
            </h1>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="pos.php" class="btn btn-primary btn-sm" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; border-radius: 4px; padding: 6px 14px;">
                <i class="fa fa-calculator"></i> POS Terminal
            </a>
            <button type="button" class="btn btn-success btn-sm" onclick="savePrinterSettingsPage()" style="font-weight: 700; background-color: #16a34a; border-color: #15803d; border-radius: 4px; padding: 6px 16px;">
                <i class="fa fa-check"></i> Save Settings
            </button>
        </div>
    </div>
</section>

<section class="content" style="padding: 15px 20px;">
    
    <!-- Notification alert -->
    <div id="printerSaveAlert" class="alert alert-success" style="display: none; border-radius: 8px; font-weight: 700; font-size: 14px;">
        <i class="fa fa-check-circle"></i> Printer settings saved successfully! Your POS receipts and PO vouchers will now use these preferences.
    </div>

    <div class="row">
        <!-- Left Column: Settings Configuration -->
        <div class="col-md-7 col-lg-8">
            
            <!-- SECTION 1: Connected Printer / Driver Profile -->
            <div class="box box-primary" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 16px;">
                <div class="box-header with-border" style="background: #f8fafc; padding: 12px 18px;">
                    <h3 class="box-title" style="font-size: 15px; font-weight: 800; color: #1e293b;">
                        <i class="fa fa-plug text-primary" style="margin-right: 6px;"></i> 1. Connected Printer / Driver Profile
                    </h3>
                </div>
                <div class="box-body" style="padding: 18px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Select Printer Device</label>
                        <select id="cfgPrinterSelect" class="form-control input-lg" onchange="handleCfgPrinterSelectChange()" style="height: 44px; font-weight: 700; font-size: 14px; color: #0369a1; border-color: #cbd5e1; border-radius: 6px;">
                            <optgroup label="Auto-Detected &amp; System Printers">
                                <option value="system_default" selected>AUTO DETECT (System Default / OS Print Spooler)</option>
                            </optgroup>
                            <optgroup label="Popular 58mm Thermal Printers (POS-58)">
                                <option value="jk_5802h">JK-5802H 58mm Thermal (USB / Bluetooth / COM)</option>
                                <option value="xprinter_58">Xprinter XP-58 / POS-58 (58mm Roll)</option>
                                <option value="generic_58">Generic ESC/POS Thermal 58mm (32 Columns)</option>
                            </optgroup>
                            <optgroup label="Popular 80mm Thermal Printers (POS-80)">
                                <option value="epson_tmt20">Epson TM-T20 / TM-T82 / TM-T88 (80mm ESC/POS)</option>
                                <option value="xprinter_80">Xprinter XP-80 / POS-80 (80mm Roll)</option>
                                <option value="generic_80">Generic ESC/POS Thermal 80mm (48 Columns)</option>
                                <option value="network_escpos">Network LAN / WiFi ESC/POS Thermal (Port 9100)</option>
                            </optgroup>
                            <optgroup label="Standard Document Printers">
                                <option value="generic_a4">Standard Office / Inkjet / Laser (A4 Sheet)</option>
                            </optgroup>
                        </select>
                        <span class="help-block" style="margin-top: 6px; font-size: 12px; color: #64748b;">
                            Select your thermal receipt printer model, or leave as Auto-Detect to use your operating system's default printer.
                        </span>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Print Transmission Engine -->
            <div class="box box-primary" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 16px;">
                <div class="box-header with-border" style="background: #f8fafc; padding: 12px 18px;">
                    <h3 class="box-title" style="font-size: 15px; font-weight: 800; color: #1e293b;">
                        <i class="fa fa-cogs text-primary" style="margin-right: 6px;"></i> 2. Print Transmission Engine
                    </h3>
                </div>
                <div class="box-body" style="padding: 18px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <label style="background: #f0f9ff; border: 2px solid #0284c7; border-radius: 8px; padding: 14px 16px; cursor: pointer; display: flex; gap: 10px; align-items: flex-start; transition: all 0.2s;" id="cfgEngineCardBrowser">
                            <input type="radio" name="cfgPrintEngine" id="cfgEngineBrowser" value="browser" checked onchange="handleCfgEngineChange('browser')" style="margin-top: 3px;">
                            <div>
                                <div style="font-weight: 800; color: #0284c7; font-size: 14px;">🌐 Browser / OS Print Dialog</div>
                                <div style="font-size: 12px; color: #64748b; line-height: 1.4; margin-top: 4px;">High-contrast 1-bit thermal HTML layout with preview. Universal compatibility across all PCs, tablets &amp; browsers.</div>
                            </div>
                        </label>

                        <label style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 14px 16px; cursor: pointer; display: flex; gap: 10px; align-items: flex-start; transition: all 0.2s;" id="cfgEngineCardEscpos">
                            <input type="radio" name="cfgPrintEngine" id="cfgEngineEscpos" value="escpos" onchange="handleCfgEngineChange('escpos')" style="margin-top: 3px;">
                            <div>
                                <div style="font-weight: 800; color: #059669; font-size: 14px;">⚡ Direct ESC/POS Hardware Stream</div>
                                <div style="font-size: 12px; color: #64748b; line-height: 1.4; margin-top: 4px;">Instant zero-dialog hardware printing, deep pitch-black ROM fonts, auto-cutter &amp; cash drawer kick.</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Paper Size & Printable Width -->
            <div class="box box-primary" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 16px;">
                <div class="box-header with-border" style="background: #f8fafc; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="box-title" style="font-size: 15px; font-weight: 800; color: #1e293b;">
                        <i class="fa fa-file-text-o text-primary" style="margin-right: 6px;"></i> 3. Paper Size &amp; Width Presets
                    </h3>
                    <span style="font-size: 12px; color: #64748b; font-weight: 600;">1-Click Quick Select</span>
                </div>
                <div class="box-body" style="padding: 18px;">
                    <!-- 1-Click Preset Cards -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                        <button type="button" class="btn btn-default" id="cfgPresetBtn58" onclick="setCfgPreset('58')" style="text-align: left; padding: 12px 14px; border: 2px solid #0284c7; background: #f0f9ff; border-radius: 8px;">
                            <div style="font-weight: 800; color: #0284c7; font-size: 13.5px;">🟢 58 mm Roll</div>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">53mm Content &bull; 32 Cols<br><strong>(JK-5802H / POS-58)</strong></div>
                        </button>
                        <button type="button" class="btn btn-default" id="cfgPresetBtn80" onclick="setCfgPreset('80')" style="text-align: left; padding: 12px 14px; border: 1.5px solid #cbd5e1; background: #ffffff; border-radius: 8px;">
                            <div style="font-weight: 800; color: #334155; font-size: 13.5px;">🔵 80 mm Roll</div>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">72mm Content &bull; 48 Cols<br><strong>(Epson / POS-80)</strong></div>
                        </button>
                        <button type="button" class="btn btn-default" id="cfgPresetBtnA4" onclick="setCfgPreset('a4')" style="text-align: left; padding: 12px 14px; border: 1.5px solid #cbd5e1; background: #ffffff; border-radius: 8px;">
                            <div style="font-weight: 800; color: #334155; font-size: 13.5px;">⚪ A4 Sheet</div>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">195mm Content &bull; 80 Cols<br><strong>(Full Invoice)</strong></div>
                        </button>
                    </div>

                    <!-- Fine-tuning row -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">Roll Width</label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" id="cfgPaperWidth" class="form-control" style="font-weight: 700; text-align: center; border-radius: 6px;" min="40" max="210" value="58" onchange="updateCfgLivePreview()">
                                <span style="font-size: 12.5px; font-weight: 700; color: #64748b;">mm</span>
                            </div>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">Printable Content Width</label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" id="cfgPrintContentWidth" class="form-control" style="font-weight: 700; text-align: center; color: #0284c7; border-radius: 6px;" min="35" max="210" value="53" oninput="updateCfgLivePreview()">
                                <span style="font-size: 12.5px; font-weight: 700; color: #64748b;">mm</span>
                            </div>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">Character Columns</label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" id="cfgPrintColumns" class="form-control" style="font-weight: 700; text-align: center; border-radius: 6px;" min="24" max="80" value="32" onchange="updateCfgLivePreview()">
                                <span style="font-size: 12.5px; font-weight: 700; color: #64748b;">cols</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Typography & Thermal Density -->
            <div class="box box-primary" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 16px;">
                <div class="box-header with-border" style="background: #f8fafc; padding: 12px 18px;">
                    <h3 class="box-title" style="font-size: 15px; font-weight: 800; color: #1e293b;">
                        <i class="fa fa-font text-primary" style="margin-right: 6px;"></i> 4. Thermal Darkness, Heat &amp; Typography
                    </h3>
                </div>
                <div class="box-body" style="padding: 18px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">ESC/POS Hardware ROM Font</label>
                            <select id="cfgHardwareFont" class="form-control" onchange="updateCfgLivePreview()" style="border-radius: 6px;">
                                <option value="font_a" selected>Font A (12×24 Standard - Sharp &amp; Bold)</option>
                                <option value="font_b">Font B (9×17 Condensed - High Density)</option>
                                <option value="font_a_bold">Font A + Double Strike (Ultra Bold)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">Thermal Head Heat / Darkness</label>
                            <select id="cfgThermalDensity" class="form-control" onchange="updateCfgLivePreview()" style="border-radius: 6px;">
                                <option value="100">100% Normal Heat</option>
                                <option value="120" selected>120% Dark / High Contrast (Recommended)</option>
                                <option value="140">140% Ultra Dark / Deep Pitch Black</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">Browser Print Font Family</label>
                        <select id="cfgThermalFontName" class="form-control" onchange="updateCfgLivePreview()" style="border-radius: 6px;">
                            <option value="Courier New" selected>Courier New (Standard POS Monospace)</option>
                            <option value="Consolas">Consolas</option>
                            <option value="Lucida Console">Lucida Console</option>
                            <option value="Liberation Mono">Liberation Mono</option>
                            <option value="Arial">Arial (Clean Sans-Serif)</option>
                            <option value="Tahoma">Tahoma</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">Thermal Font Size (pt)</label>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="number" id="cfgThermalFontSize" class="form-control" style="font-weight: 700; text-align: center; border-radius: 6px;" min="8" max="24" step="0.5" value="10.5" oninput="updateCfgLivePreview()">
                                <b style="font-size: 13px; color: #334155;">pt</b>
                            </div>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">Line Spacing / Height</label>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="number" id="cfgThermalLineHeight" class="form-control" style="font-weight: 700; text-align: center; border-radius: 6px;" min="1.0" max="2.0" step="0.05" value="1.25" oninput="updateCfgLivePreview()">
                                <span style="font-size: 12px; color: #64748b;">(Default: 1.25)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Live Thermal Receipt Preview & Action Buttons -->
        <div class="col-md-5 col-lg-4">
            
            <div class="box box-info" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="box-header with-border" style="background: #f8fafc; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="box-title" style="font-size: 15px; font-weight: 800; color: #1e293b;">
                        <i class="fa fa-eye text-info" style="margin-right: 6px;"></i> Live Receipt Preview
                    </h3>
                    <span id="cfgLiveWidthBadge" class="badge" style="background: #0284c7; font-size: 11px; padding: 3px 8px;">53 mm</span>
                </div>
                <div class="box-body" style="padding: 16px; background: #e2e8f0; text-align: center;">
                    
                    <!-- Paper Preview Container -->
                    <div id="cfgReceiptPreviewBox" style="display: inline-block; background: #ffffff; color: #000; width: 230px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); border-radius: 4px; padding: 12px 10px; text-align: left; font-family: 'Courier New', monospace; font-size: 10.5pt; line-height: 1.25;">
                        <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                        <div style="text-align: center;">
                            <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars(!empty($store_name) ? strtoupper($store_name) : 'SAM & INRI CONSTRUCTION'); ?></div>
                            <div style="font-size: 8.5pt;">Tel: <?php echo htmlspecialchars($store_phone); ?></div>
                            <div style="font-size: 9.5pt; font-weight: bold; text-transform: uppercase; margin-top: 2px;">OFFICIAL SALES INVOICE</div>
                            <div style="font-size: 8.5pt; font-weight: bold; text-transform: uppercase;">(PAID)</div>
                        </div>
                        <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                        <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 8.5pt; margin-bottom: 2px;">
                            <tr><td style="width: 32%; font-weight: bold;">INVOICE :</td><td style="font-weight: bold;">INV-SAMPLE-01</td></tr>
                            <tr><td style="font-weight: bold;">CASHIER :</td><td>Admin Staff</td></tr>
                            <tr><td style="font-weight: bold;">DATE    :</td><td><?php echo date('Y-m-d H:i'); ?></td></tr>
                        </table>
                        <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                        <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 8.5pt;">
                            <thead>
                                <tr><th style="text-align: left;">ITEM</th><th style="text-align: right;">AMOUNT</th></tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="2" style="font-weight: bold; padding-top: 2px;">Portland Cement 40kg</td></tr>
                                <tr><td style="padding-left: 6px;">2 pcs @ 250.00</td><td style="text-align: right;">500.00</td></tr>
                                <tr><td colspan="2" style="font-weight: bold; padding-top: 2px;">Steel Bar 10mm</td></tr>
                                <tr><td style="padding-left: 6px;">1 pc @ 180.00</td><td style="text-align: right;">180.00</td></tr>
                            </tbody>
                        </table>
                        <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                        <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 8.5pt;">
                            <tr><td>Subtotal:</td><td style="text-align: right;">680.00</td></tr>
                            <tr style="font-weight: bold;"><td>TOTAL DUE:</td><td style="text-align: right;">PHP 680.00</td></tr>
                            <tr><td>Tendered:</td><td style="text-align: right;">1,000.00</td></tr>
                            <tr><td>Change:</td><td style="text-align: right;">320.00</td></tr>
                        </table>
                        <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                        <div style="text-align: center; font-weight: bold; padding: 2px 0; font-size: 8pt; text-transform: uppercase;">
                            THANK YOU FOR YOUR BUSINESS!
                        </div>
                        <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                    </div>

                </div>
                <div class="box-footer" style="padding: 14px 18px; background: #fff; border-top: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 8px;">
                    <button type="button" class="btn btn-primary btn-block btn-lg" onclick="triggerTestPrint()" style="font-weight: 800; font-size: 15px; border-radius: 6px; background-color: #0284c7; border-color: #0369a1;">
                        <i class="fa fa-print"></i> Test Print Sample Receipt
                    </button>
                    <button type="button" class="btn btn-success btn-block btn-lg" onclick="savePrinterSettingsPage()" style="font-weight: 800; font-size: 15px; border-radius: 6px; background-color: #16a34a; border-color: #15803d;">
                        <i class="fa fa-save"></i> Save Printer Preferences
                    </button>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Hidden Thermal Printing Container -->
<div id="cfgPrintArea" style="display: none;"></div>

<script>
// Load Settings on Page Mount
document.addEventListener('DOMContentLoaded', function() {
    loadCfgSettingsFromStorage();
});

function loadCfgSettingsFromStorage() {
    let s = {
        printerId: 'jk_5802h',
        printEngine: 'browser',
        paperWidthMm: 58,
        printContentWidthMm: 53,
        printColumns: 32,
        hardwareFont: 'font_a',
        thermalDensity: 120,
        thermalFontName: 'Courier New',
        thermalDefaultFontSize: 10.5,
        thermalLineHeight: 1.25
    };

    try {
        const saved = localStorage.getItem('pos_printer_settings');
        if (saved) {
            s = Object.assign(s, JSON.parse(saved));
        }
    } catch(e) {}

    // Populate form fields
    const select = document.getElementById('cfgPrinterSelect');
    if (select && s.printerId) select.value = s.printerId;

    if (s.printEngine === 'escpos') {
        document.getElementById('cfgEngineEscpos').checked = true;
        handleCfgEngineChange('escpos');
    } else {
        document.getElementById('cfgEngineBrowser').checked = true;
        handleCfgEngineChange('browser');
    }

    document.getElementById('cfgPaperWidth').value = s.paperWidthMm || 58;
    document.getElementById('cfgPrintContentWidth').value = s.printContentWidthMm || 53;
    document.getElementById('cfgPrintColumns').value = s.printColumns || 32;
    document.getElementById('cfgHardwareFont').value = s.hardwareFont || 'font_a';
    document.getElementById('cfgThermalDensity').value = s.thermalDensity || 120;
    document.getElementById('cfgThermalFontName').value = s.thermalFontName || 'Courier New';
    document.getElementById('cfgThermalFontSize').value = s.thermalDefaultFontSize || 10.5;
    document.getElementById('cfgThermalLineHeight').value = s.thermalLineHeight || 1.25;

    // Highlight preset button
    highlightPresetBtn(s.paperWidthMm);

    updateCfgLivePreview();
}

function highlightPresetBtn(w) {
    const p58 = document.getElementById('cfgPresetBtn58');
    const p80 = document.getElementById('cfgPresetBtn80');
    const pA4 = document.getElementById('cfgPresetBtnA4');

    if (p58) { p58.style.borderColor = '#cbd5e1'; p58.style.background = '#ffffff'; }
    if (p80) { p80.style.borderColor = '#cbd5e1'; p80.style.background = '#ffffff'; }
    if (pA4) { pA4.style.borderColor = '#cbd5e1'; pA4.style.background = '#ffffff'; }

    if (w >= 180) {
        if (pA4) { pA4.style.borderColor = '#0284c7'; pA4.style.background = '#f0f9ff'; }
    } else if (w >= 75) {
        if (p80) { p80.style.borderColor = '#0284c7'; p80.style.background = '#f0f9ff'; }
    } else {
        if (p58) { p58.style.borderColor = '#0284c7'; p58.style.background = '#f0f9ff'; }
    }
}

function setCfgPreset(preset) {
    if (preset === '80') {
        document.getElementById('cfgPaperWidth').value = 80;
        document.getElementById('cfgPrintContentWidth').value = 72;
        document.getElementById('cfgPrintColumns').value = 48;
        document.getElementById('cfgThermalFontSize').value = 11.5;
        document.getElementById('cfgThermalLineHeight').value = 1.25;
        highlightPresetBtn(80);
    } else if (preset === 'a4') {
        document.getElementById('cfgPaperWidth').value = 210;
        document.getElementById('cfgPrintContentWidth').value = 195;
        document.getElementById('cfgPrintColumns').value = 80;
        document.getElementById('cfgThermalFontSize').value = 12;
        document.getElementById('cfgThermalLineHeight').value = 1.30;
        highlightPresetBtn(210);
    } else {
        document.getElementById('cfgPaperWidth').value = 58;
        document.getElementById('cfgPrintContentWidth').value = 53;
        document.getElementById('cfgPrintColumns').value = 32;
        document.getElementById('cfgThermalFontSize').value = 10.5;
        document.getElementById('cfgThermalLineHeight').value = 1.25;
        highlightPresetBtn(58);
    }
    updateCfgLivePreview();
}

function handleCfgEngineChange(engine) {
    const cardBrowser = document.getElementById('cfgEngineCardBrowser');
    const cardEscpos = document.getElementById('cfgEngineCardEscpos');
    if (engine === 'escpos') {
        if (cardEscpos) { cardEscpos.style.borderColor = '#059669'; cardEscpos.style.background = '#ecfdf5'; }
        if (cardBrowser) { cardBrowser.style.borderColor = '#cbd5e1'; cardBrowser.style.background = '#f8fafc'; }
    } else {
        if (cardBrowser) { cardBrowser.style.borderColor = '#0284c7'; cardBrowser.style.background = '#f0f9ff'; }
        if (cardEscpos) { cardEscpos.style.borderColor = '#cbd5e1'; cardEscpos.style.background = '#f8fafc'; }
    }
}

function handleCfgPrinterSelectChange() {
    const val = document.getElementById('cfgPrinterSelect').value;
    if (val === 'epson_tmt20' || val === 'xprinter_80' || val === 'generic_80' || val === 'network_escpos') {
        setCfgPreset('80');
    } else if (val === 'generic_a4') {
        setCfgPreset('a4');
    } else if (val === 'jk_5802h' || val === 'xprinter_58' || val === 'generic_58') {
        setCfgPreset('58');
    }
}

function updateCfgLivePreview() {
    const previewBox = document.getElementById('cfgReceiptPreviewBox');
    const badge = document.getElementById('cfgLiveWidthBadge');
    if (!previewBox) return;

    const paperW = parseInt(document.getElementById('cfgPaperWidth').value, 10) || 58;
    const contentW = parseFloat(document.getElementById('cfgPrintContentWidth').value) || 53;
    const fontName = document.getElementById('cfgThermalFontName').value || 'Courier New';
    const fontSize = parseFloat(document.getElementById('cfgThermalFontSize').value) || 10.5;
    const lineHeight = parseFloat(document.getElementById('cfgThermalLineHeight').value) || 1.25;

    // Scale visual width for preview container
    let visualWidth = 230;
    if (paperW >= 180) visualWidth = 340;
    else if (paperW >= 75) visualWidth = 280;

    previewBox.style.width = visualWidth + 'px';
    previewBox.style.fontFamily = fontName + ', monospace';
    previewBox.style.lineHeight = lineHeight;
    if (badge) badge.innerText = contentW + ' mm';
}

function savePrinterSettingsPage() {
    const payload = {
        printerId: document.getElementById('cfgPrinterSelect').value,
        printEngine: document.querySelector('input[name="cfgPrintEngine"]:checked').value,
        paperWidthMm: parseInt(document.getElementById('cfgPaperWidth').value, 10) || 58,
        printContentWidthMm: parseFloat(document.getElementById('cfgPrintContentWidth').value) || 53,
        printColumns: parseInt(document.getElementById('cfgPrintColumns').value, 10) || 32,
        hardwareFont: document.getElementById('cfgHardwareFont').value,
        thermalDensity: parseInt(document.getElementById('cfgThermalDensity').value, 10) || 120,
        thermalFontName: document.getElementById('cfgThermalFontName').value || 'Courier New',
        thermalDefaultFontSize: parseFloat(document.getElementById('cfgThermalFontSize').value) || 10.5,
        thermalLineHeight: parseFloat(document.getElementById('cfgThermalLineHeight').value) || 1.25
    };

    localStorage.setItem('pos_printer_settings', JSON.stringify(payload));

    const alertEl = document.getElementById('printerSaveAlert');
    if (alertEl) {
        alertEl.style.display = 'block';
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(function() {
            $(alertEl).fadeOut(400);
        }, 3500);
    }
}

function triggerTestPrint() {
    savePrinterSettingsPage();
    const previewBox = document.getElementById('cfgReceiptPreviewBox');
    if (!previewBox) return;

    const printWin = window.open('', '_blank', 'width=400,height=600');
    if (!printWin) {
        alert('Please allow popups to test print.');
        return;
    }

    const contentW = parseFloat(document.getElementById('cfgPrintContentWidth').value) || 53;
    const fontName = document.getElementById('cfgThermalFontName').value || 'Courier New';
    const fontSize = parseFloat(document.getElementById('cfgThermalFontSize').value) || 10.5;
    const lineHeight = parseFloat(document.getElementById('cfgThermalLineHeight').value) || 1.25;

    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Test Print Receipt</title>
            <style>
                @page { margin: 0; size: auto; }
                body {
                    margin: 0;
                    padding: 4px;
                    width: ${contentW}mm;
                    max-width: ${contentW}mm;
                    font-family: '${fontName}', monospace;
                    font-size: ${fontSize}pt;
                    line-height: ${lineHeight};
                    color: #000;
                    background: #fff;
                    -webkit-print-color-adjust: exact;
                }
            </style>
        </head>
        <body>
            ${previewBox.innerHTML}
        </body>
        </html>
    `);
    printWin.document.close();
    printWin.focus();
    setTimeout(function() {
        printWin.print();
        printWin.close();
    }, 400);
}
</script>

<?php require_once('footer.php'); ?>
