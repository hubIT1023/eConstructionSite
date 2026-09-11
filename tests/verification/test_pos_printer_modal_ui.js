/**
 * Verification Suite for POS Printer Configuration / Setup UI & Integration
 * File: tests/verification/test_pos_printer_modal_ui.js
 * 
 * Verifies that supplier/pos.php complies with:
 * 1. 210mm maximum & default thermal width standard (zero 500mm, 400mm, 250mm)
 * 2. Title: "Printer Configuration / Setup"
 * 3. Printer detection (Auto Detect by default, Refresh Detection)
 * 4. Thermal Font Name selection & custom entry with datalist
 * 5. Thermal Font Size selection & custom entry with 12pt hard minimum
 * 6. Isolated test buttons: 210mm, 80mm, 58mm, 50mm (50mm never touches saved config)
 * 7. Active Configuration summary card at the top
 * 8. Secondary A4 printer & PDF preview only card
 * 9. Print copies (1-5)
 * 10. Backward compatibility with legacy hidden inputs
 */
const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '../../');
const posPath = path.join(ROOT_DIR, 'supplier/pos.php');
const posContent = fs.readFileSync(posPath, 'utf8');

let allPassed = true;
let totalChecks = 0;
let passedChecks = 0;

function assert(condition, message) {
    totalChecks++;
    if (condition) {
        passedChecks++;
        console.log(`  [PASS] ${message}`);
    } else {
        allPassed = false;
        console.error(`  [FAIL] ${message}`);
    }
}

console.log('================================================================');
console.log('POS PRINTER CONFIGURATION / SETUP UI - VERIFICATION SUITE');
console.log('================================================================\n');

// 1. Modal Uniqueness & Title
console.log('--- 1. Modal Uniqueness & Header Title ---');
const modalMatches = posContent.match(/id=["']posPrinterModal["']/g);
assert(modalMatches && modalMatches.length === 1, 'Exactly one #posPrinterModal in pos.php (no duplicates)');

const modalStart = posContent.indexOf('id="posPrinterModal"');
const modalEnd = posContent.indexOf('</div>', posContent.indexOf('class="modal-footer"', modalStart) + 400);
const modalBlock = posContent.substring(modalStart, modalEnd);

assert(
    modalBlock.includes('Printer Configuration / Setup') &&
    modalBlock.includes('Configure thermal, A4 and PDF output'),
    'Modal title is "Printer Configuration / Setup" with subtitle "Configure thermal, A4 and PDF output"'
);

// 2. Detection Section
console.log('\n--- 2. Printer Detection Section ---');
assert(
    modalBlock.includes('PRINTER DETECTION') &&
    modalBlock.includes('Auto Detect Active') &&
    modalBlock.includes('refreshPOSPrinters()') &&
    modalBlock.includes('Refresh Detection'),
    'Detection section shows Auto Detect by default and includes Refresh Detection button'
);

// 3. Active Configuration Card (Top Placement)
console.log('\n--- 3. Active Configuration Card (Top Placement) ---');
assert(
    modalBlock.includes('ACTIVE CONFIGURATION') &&
    modalBlock.includes('id="posActiveWidthVal"') &&
    modalBlock.includes('id="posActiveFontNameVal"') &&
    modalBlock.includes('id="posActiveFontVal"') &&
    modalBlock.includes('id="posActiveA4Val"') &&
    modalBlock.includes('id="posLayoutDesc"'),
    'Active Configuration Card is present with live width, font name, font size, A4 and layout badges'
);

const topCardIndex = modalBlock.indexOf('ACTIVE CONFIGURATION');
const section1Index = modalBlock.indexOf('PRIMARY — THERMAL PRINTER') !== -1 ? modalBlock.indexOf('PRIMARY — THERMAL PRINTER') : modalBlock.indexOf('PRIMARY &mdash; THERMAL PRINTER');
assert(
    topCardIndex !== -1 && section1Index !== -1 && topCardIndex < section1Index,
    'Active Configuration Card is positioned at the top of the modal body, before Primary Thermal section'
);

// 4. Primary Thermal Printer (Max 210mm, Presets, Typography)
console.log('\n--- 4. Primary Thermal Printer Section (210mm Max & Typography) ---');
assert(
    modalBlock.includes('id="posPrinterSelect"') &&
    modalBlock.includes('id="posPaperWidth"'),
    'Primary Thermal Section contains posPrinterSelect and posPaperWidth'
);

assert(
    modalBlock.includes('<option value="210">210 mm — Maximum Thermal Width</option>') &&
    modalBlock.includes('<option value="80" selected>80 mm — Standard 3-inch POS Thermal</option>') &&
    modalBlock.includes('<option value="58">58 mm — Standard 2-inch POS Thermal</option>') &&
    modalBlock.includes('<option value="custom">Custom Width</option>'),
    'posPaperWidth includes 210mm max, 80mm standard 3-inch, 58mm standard 2-inch, and custom options matching prototype'
);

assert(
    !modalBlock.includes('<option value="500"') &&
    !modalBlock.includes('500 mm Roll') &&
    !modalBlock.includes('500mm Roll') &&
    !modalBlock.includes('<option value="400"') &&
    !modalBlock.includes('<option value="250"'),
    'Strict standard: ZERO 500mm, 400mm, or 250mm options inside #posPrinterModal'
);

assert(
    modalBlock.includes('id="posCustomWidthGroup"') &&
    modalBlock.includes('id="posCustomWidthInput"') &&
    modalBlock.includes('max="210"'),
    'Custom width input group enforces max="210"'
);

// 5. Thermal Font Selection (Name & Size)
console.log('\n--- 5. Thermal Font Name & Size Options ---');
assert(
    modalBlock.includes('id="posThermalFontName"') &&
    modalBlock.includes('id="posThermalFontList"'),
    'Thermal Font Name includes font select with datalist fallback (#posThermalFontList)'
);

assert(
    modalBlock.includes('<option value="Courier New"') &&
    modalBlock.includes('<option value="Consolas"') &&
    modalBlock.includes('<option value="Lucida Console"') &&
    modalBlock.includes('<option value="Arial"') &&
    modalBlock.includes('<option value="Tahoma"') &&
    modalBlock.includes('<option value="Verdana"'),
    'Font options include standard monospace and clean typography (Courier New, Consolas, Lucida Console, Arial, etc.)'
);

assert(
    modalBlock.includes('adjustThermalFontSize(-1)') &&
    modalBlock.includes('adjustThermalFontSize(1)') &&
    modalBlock.includes('setThermalFontSize(12)') &&
    modalBlock.includes('setThermalFontSize(14)') &&
    modalBlock.includes('setThermalFontSize(16)') &&
    modalBlock.includes('setThermalFontSize(18)') &&
    modalBlock.includes('setThermalFontSize(20)'),
    'Thermal font size includes stepper (− / +) and quick-select buttons (12, 14, 16, 18, 20 pt) matching prototype'
);

assert(
    modalBlock.includes('id="posThermalFontSizeBadge"'),
    'Live font size badge is present alongside the font size control'
);

assert(
    modalBlock.includes('id="posThermalCustomFontSize"') &&
    modalBlock.includes('min="12"'),
    'Custom font size input strictly enforces min="12"'
);

assert(
    modalBlock.includes('id="posThermalBoldImportant"'),
    'Thermal bold important text checkbox is present'
);

// 6. Dedicated Test Profiles (210mm, 80mm, 58mm, 50mm)
console.log('\n--- 6. Dedicated Test Profiles (210mm, 80mm, 58mm, 50mm) ---');
assert(
    modalBlock.includes("onclick=\"testPrintPOS('thermal210')\"") &&
    modalBlock.includes('Test 210 mm'),
    'Dedicated Test 210 mm button present'
);

assert(
    modalBlock.includes("onclick=\"testPrintPOS('thermal80')\"") &&
    modalBlock.includes('Test 80 mm'),
    'Dedicated Test 80 mm button present'
);

assert(
    modalBlock.includes("onclick=\"testPrintPOS('thermal58')\"") &&
    modalBlock.includes('Test 58 mm'),
    'Dedicated Test 58 mm button present'
);

assert(
    modalBlock.includes("onclick=\"testPrintPOS('thermal50')\"") &&
    modalBlock.includes('Test 50 mm'),
    'Dedicated Test 50 mm button present (must run isolated print job)'
);

// 7. Section 2: Secondary Standard A4 Printer
console.log('\n--- 7. Section 2: Secondary Standard A4 Printer ---');
assert(
    modalBlock.includes('id="posA4PrinterSelect"') &&
    modalBlock.includes('A4 (210 &times; 297 mm)') &&
    modalBlock.includes('id="posA4Orientation"') &&
    modalBlock.includes('id="posOrientPortrait"') &&
    modalBlock.includes('id="posOrientLandscape"'),
    'Secondary A4 Section includes posA4PrinterSelect, standard A4 dimensions, and orientation buttons (Portrait / Landscape)'
);

assert(
    modalBlock.includes('Thermal roll width and thermal font settings do not alter A4 print layout'),
    'Section 2 explicitly notes thermal settings do not alter A4 layout'
);

assert(
    modalBlock.includes('onclick="testPrintA4PDF()"') &&
    modalBlock.includes('Test A4 Print'),
    'Section 2 includes Test A4 Print button'
);

// 8. Section 3: Preview Only PDF
console.log('\n--- 8. Section 3: Preview Only PDF ---');
assert(
    modalBlock.includes('PDF PREVIEW / EXPORT') &&
    (modalBlock.includes('PDF is used for preview/export only') || modalBlock.includes('PDF is for preview/export only')) &&
    modalBlock.includes('It is not a physical printer') &&
    modalBlock.includes('Preview PDF'),
    'PDF is clearly designated as Preview Only and never a physical printer'
);

// 9. Section 4: Print Copies & Footer Actions
console.log('\n--- 9. Section 4: Print Copies & Footer Actions ---');
assert(
    modalBlock.includes('id="posPrintCopies"') &&
    modalBlock.includes('adjustPrintCopies(-1)') &&
    modalBlock.includes('adjustPrintCopies(1)') &&
    modalBlock.includes('min="1"') &&
    modalBlock.includes('max="5"'),
    'Print copies includes stepper allowing 1 to 5 copies'
);

assert(
    modalBlock.includes('data-dismiss="modal"') &&
    (modalBlock.includes('Close') || modalBlock.includes('Cancel')) &&
    modalBlock.includes('onclick="savePOSPrinterSettings()"') &&
    modalBlock.includes('Save Configuration'),
    'Modal footer includes Close/Cancel and Save Configuration buttons'
);

// 10. Backward Compatibility Hidden Elements
console.log('\n--- 10. Backward Compatibility Hidden Elements ---');
assert(
    modalBlock.includes('id="posPrinterType"') &&
    modalBlock.includes('id="posA4PrintWidthInput"') &&
    modalBlock.includes('id="posA4PrintWidthRange"') &&
    modalBlock.includes('id="posPdfWidthBadgeVal"') &&
    modalBlock.includes('id="posModeRadioThermal"') &&
    modalBlock.includes('id="posModeRadioNormal"') &&
    modalBlock.includes('id="posModeRadioPdf"'),
    'All legacy hidden elements are preserved for backward compatibility'
);

// 11. JavaScript Engine Handlers & 210mm / Font Size Normalization
console.log('\n--- 11. JavaScript Engine Handlers & Normalization ---');
assert(
    posContent.includes('const MAX_THERMAL_WIDTH_MM = 210;') &&
    posContent.includes('const MIN_THERMAL_FONT_SIZE = 12;') &&
    (posContent.includes('paperWidthMm: 210,') || posContent.includes('paperWidthMm: 80,')) &&
    posContent.includes("thermalFontName: 'Courier New',"),
    'JavaScript defines MAX_THERMAL_WIDTH_MM = 210, MIN_THERMAL_FONT_SIZE = 12, default font and valid default paper width'
);

assert(
    posContent.includes('posPrinterSettings.paperWidthMm > MAX_THERMAL_WIDTH_MM') &&
    (posContent.includes('posPrinterSettings.paperWidthMm = MAX_THERMAL_WIDTH_MM;') || posContent.includes('posPrinterSettings.paperWidthMm = 210;')),
    'getPOSPrintSettings auto-normalizes any thermal paper width > 210mm to 210mm'
);

assert(
    posContent.includes('posPrinterSettings.thermalMinFontSize < 12') &&
    posContent.includes('posPrinterSettings.thermalDefaultFontSize < 12'),
    'getPOSPrintSettings auto-normalizes font sizes < 12 pt to 12 pt'
);

assert(
    posContent.includes('Math.min(MAX_THERMAL_WIDTH_MM, Math.max(40, customW))'),
    'savePOSPrinterSettings strictly clamps custom thermal width between 40mm and 210mm'
);

assert(
    posContent.includes("format === 'thermal50'") &&
    posContent.includes('testPrintThermalPaidOrder(50'),
    'testPrintPOS routes thermal50 to testPrintThermalPaidOrder(50) without altering saved config'
);

assert(
    posContent.includes('function generatePaidOrderThermalHTML(orderData, requestedWidthMm') &&
    posContent.includes('paperWidthMm'),
    'generatePaidOrderThermalHTML handles paperWidthMm and contentWidthMm dimensions'
);

// 12. Top Status Button
console.log('\n--- 12. POS Top Header Printer Status Button ---');
assert(
    posContent.includes('id="posPrinterStatusBtn"') &&
    posContent.includes('Printer Setup') &&
    posContent.includes('Printer Configuration / Setup (Auto Detect, 210mm / 80mm / 58mm / A4)'),
    'Header status button shows Printer Setup label and proper title'
);

// 13. Document Dialogs (Receipt, PO, Return) Free of 500mm Buttons
console.log('\n--- 13. Document Dialogs Free of 500mm Options ---');
const successModalBlock = posContent.substring(posContent.indexOf('id="posSuccessModal"'), posContent.indexOf('id="posSuccessModal"') + 3500);
assert(
    !successModalBlock.includes('>500mm<') &&
    !successModalBlock.includes('>500 mm<') &&
    !successModalBlock.includes('value="500"'),
    'Success Receipt modal has no 500mm button options'
);

const poSuccessModalBlock = posContent.substring(posContent.indexOf('id="posPOSuccessModal"'), posContent.indexOf('id="posPOSuccessModal"') + 3500);
assert(
    !poSuccessModalBlock.includes('>500mm<') &&
    !poSuccessModalBlock.includes('>500 mm<') &&
    !poSuccessModalBlock.includes('value="500"'),
    'PO Success modal has no 500mm button options'
);

// 14. Print / Content Width & Auto Adjust System
console.log('\n--- 14. Print / Content Width & Auto Adjust System ---');
assert(
    modalBlock.includes('id="posPrintContentWidth"') &&
    modalBlock.includes('id="posAutoAdjustContentWidth"') &&
    modalBlock.includes('id="posSuggestedContentWidth"') &&
    modalBlock.includes('id="posActiveContentWidthVal"'),
    'Modal UI includes dedicated Content Width input, Auto Adjust checkbox, Suggested badge, and Active card badge'
);

assert(
    posContent.includes('function getRecommendedContentWidth(paperWidthMm)') &&
    posContent.includes('return 44;') &&
    posContent.includes('return 48;') &&
    posContent.includes('return 72;') &&
    posContent.includes('return 120;'),
    'getRecommendedContentWidth provides exact calibrated defaults (58mm -> 48mm, 80mm -> 72mm, 210mm -> 120mm)'
);

assert(
    posContent.includes('contentWidthMm = Math.min(paperWidthMm,') || posContent.includes('Math.min(posPrinterSettings.paperWidthMm'),
    'Content Width is strictly capped so that Content Width <= Paper Width'
);

// 15. Thermal Receipt Printout Layout Matching Attached Image
console.log('\n--- 15. Receipt Layout Matching Attached Image Standard ---');
assert(
    posContent.includes('PAID ORDER') &&
    posContent.includes('Order No:') &&
    posContent.includes('Date:') &&
    posContent.includes('Customer:') &&
    posContent.includes('ITEM') &&
    posContent.includes('QTY') &&
    posContent.includes('AMOUNT') &&
    posContent.includes('Subtotal') &&
    posContent.includes('TOTAL') &&
    posContent.includes('PAYMENT STATUS:') &&
    posContent.includes('Payment Method:') &&
    posContent.includes('Thank you'),
    'generatePaidOrderThermalHTML contains all structured elements matching attached receipt image'
);

assert(
    posContent.includes('1pt dashed #000') || posContent.includes('dashed #000'),
    'Thermal receipt layout uses clean dashed divider lines matching sample receipt'
);

// 16. Paid Orders Page Parity Check
console.log('\n--- 16. Paid Orders Page Parity Check ---');
const paidOrdersPath = path.join(ROOT_DIR, 'supplier/paid-orders.php');
const paidOrdersContent = fs.readFileSync(paidOrdersPath, 'utf8');

assert(
    paidOrdersContent.includes('const MAX_THERMAL_WIDTH_MM = 210;') &&
    paidOrdersContent.includes('const MIN_THERMAL_FONT_SIZE = 12;'),
    'paid-orders.php defines MAX_THERMAL_WIDTH_MM = 210 and MIN_THERMAL_FONT_SIZE = 12'
);

assert(
    !paidOrdersContent.includes('500mm') &&
    !paidOrdersContent.includes('8.5pt') &&
    !paidOrdersContent.includes('font-size: 8.5pt'),
    'paid-orders.php has zero legacy 500mm and zero 8.5pt font references'
);

// 17. JavaScript Syntax Validation for Printer Engine
console.log('\n--- 17. JavaScript Syntax Validation ---');
const pJsStart = posContent.indexOf('const MAX_THERMAL_WIDTH_MM = 210;');
const pJsEnd = posContent.indexOf('// POS RETURN WORKFLOW JAVASCRIPT ENGINE', pJsStart);
const printerJsBlock = posContent.substring(pJsStart, pJsEnd);
let jsParsed = false;
try {
    new Function(printerJsBlock);
    jsParsed = true;
} catch (e) {
    console.error('JS Syntax Error in printer script block:', e.message);
}
assert(jsParsed, 'Printer configuration and print engine JavaScript parses cleanly with 0 syntax errors');

console.log('\n================================================================');
console.log(`RESULTS: ${passedChecks}/${totalChecks} checks passed.`);
if (allPassed) {
    console.log('STATUS: ALL PRINTER CONFIGURATION / SETUP UI VERIFICATION CHECKS PASSED!');
} else {
    console.error('STATUS: VERIFICATION FAILED - PLEASE REVIEW LOGGED ERRORS.');
    process.exit(1);
}
console.log('================================================================');


