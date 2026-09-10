# POS Automatic Printer Detection, Selection & Dynamic Thermal Formatting Engine

## 1. Overview
The Supplier POS terminal (`https://econstruction-supply.site/supplier/pos.php`) and Supplier Checkout subsystem (`https://econstruction-supply.site/supplier/checkout.php`) incorporate an advanced **Automatic Printer Detection, Manual Selection, and Dynamic Thermal Layout Engine**.

This subsystem allows order encoders and cashiers to format receipts, purchase orders, and return slips dynamically according to selected continuous paper widths ranging from compact rolls (**58 mm**, **80 mm**, **100 mm**) to extra-wide formats (**300 mm**, **500 mm / 50 cm Default**) and full standard sheets (**210 mm / A4**), while preserving 100% normal responsive HTML on the PC workstation screen.

---

## 2. Automatic Printer Detection Architecture (Non-Faking)

Modern web browsers run inside isolated sandbox security environments that intentionally prevent web scripts from silently enumerating local Windows/OS print drivers without user interaction or dedicated bridge daemons. The POS printer subsystem uses a multi-tier probing strategy:

```text
Supplier POS / Checkout Page Load
               │
               ▼
   Probing Detection Channels
   ├── 1. Chromium Local Printer API (window.queryLocalPrinters)
   ├── 2. WebUSB Connected Hardware (navigator.usb.getDevices)
   ├── 3. WebSerial Serial/COM Ports (navigator.serial.getPorts)
   └── 4. Local Raw ESC/POS Print Daemon (127.0.0.1:9100 / 127.0.0.1:8080)
               │
               ├── Hardware / Service Found
               │       │
               │       ▼
               │   Populate Hardware Target List
               │   Set Status: "● Direct Printer / Service Online (N detected)"
               │
               └── Direct Enumeration Sandboxed / Restricted
                       │
                       ▼
                   Fallback to Browser / OS Print Dialog
                   Set Status: "● Browser / System Print Dialog Active (500 mm Thermal Layout Ready)"
                   (No fabricated or fake printer device names generated)
```

---

## 3. Printer Options & Configuration Controls

The POS terminal provides top-level access via the `[ 🖨️ Printer: 500mm (Thermal) ]` status button in the top action bar, opening the configuration interface:

| Option | Function | Values / Defaults |
| :--- | :--- | :--- |
| **Target Printer** | Target output device | `✓ System Default Printer (via Browser / OS Print Dialog)` or probed hardware devices |
| **Printer Type** | Document formatting mode | `Auto Detect (Recommended)`, `Thermal Printer (POS Continuous Roll)`, `Normal Printer (Laser/Inkjet/A4)` |
| **Paper Width** | Continuous roll or sheet width | **`500 mm / 50 cm (Standard POS Wide - Default)`**, `300 mm`, `210 mm (A4)`, `100 mm`, `80 mm`, `58 mm`, `Custom (mm)` |
| **Custom Width** | Explicit roll millimeter input | Shown when `Custom` selected ($40\text{ mm} - 1000\text{ mm}$) |
| **Copies** | Number of duplicated prints per job | `1` to `5` |
| **Dynamic Layout** | Real-time layout capability indicator | `500 mm (50 cm) Wide Thermal Ready` / `A4 Full Sheet Ready` |
| **Test Print** | Diagnostic calibration roll output | `[ 🖨️ Test Print ]` button |

---

## 4. Strict Screen vs. Print Layout Isolation

**Mandatory Architecture Requirement**: The PC workstation screen must remain a standard responsive HTML web application at all times.

- **Screen View (PC/Tablet)**: Standard Bootstrap 3.3.7 container grid, search input, category filtering pills, high-density product cards, and live sliding current sale cart drawer. No 500 mm width is ever applied to screen elements.
- **Print Payload**: Injected dynamically into an isolated print document:
  ```css
  @media print {
      @page {
          size: 500mm auto;
          margin: 0;
      }
      body {
          width: 500mm !important;
          max-width: 500mm !important;
          margin: 0 auto !important;
          padding: 12mm !important;
          background: #fff !important;
          font-family: 'Courier New', Courier, monospace;
      }
      .pos-print-container {
          width: 100% !important;
          max-width: 500mm !important;
      }
  }
  ```

---

## 5. Dynamic Print Layout Resizing Rules

When the print payload is built, font sizes, margins, padding, and tabular columns dynamically adapt to the active paper width:

1. **Compact Rolls (58 mm - 80 mm)**:
   - Font size: $10.5\text{px} - 11.5\text{px}$ monospace.
   - Table columns: 3 columns (`Item`, `Qty`, `Total`).
   - Page margins: $1\text{mm} - 2\text{mm}$.
2. **Medium Rolls (100 mm)**:
   - Font size: $12\text{px} - 13\text{px}$.
   - Table columns: 4 columns (`Item Description`, `Qty`, `Unit Price`, `Line Total`).
3. **Normal Sheet (210 mm / A4)**:
   - Font size: $13.5\text{px}$, clean sans-serif typography.
   - Full invoice layout with two-column store/customer metadata and multi-column tabular grid.
   - Page margins: $10\text{mm}$.
4. **Extra Wide Continuous Roll (500 mm / 50 cm Default)**:
   - Font size: $14\text{px} - 24\text{px}$ bold headers, $13.5\text{px}$ body.
   - High-contrast 5-column grid: `Item Description & Specifications`, `SKU / Brand`, `Qty`, `Unit Price`, `Line Total`.
   - Generous $12\text{mm}$ continuous roll margins.

---

## 6. Document Types & Read-Only Safety

All POS print jobs are strictly **read-only** and do not alter database transaction records, create duplicate orders, or decrement inventory:

| Document | Payment Status | Primary Actor | Description |
| :--- | :--- | :--- | :--- |
| **Sales Receipt** (`printPOSReceipt`) | **PAID** | Cashier | Proof of payment, tendered cash, change due, and VAT breakdown |
| **Purchase Order** (`printPOSPurchaseOrder`) | **UNPAID** | Order Encoder | Reference order slip instructing customer to proceed to cashier |
| **Return Slip** (`printReturnSlip`) | **REFUNDED** | Cashier / Manager | Official return and refund voucher with manager sign-off |
| **Test Print** (`testPrintPOS`) | **DIAGNOSTIC** | Operator | Calibrated roll test verifying width formatting and printer profile |

---

## 7. Client-Side Persistence & Multi-Tenant Security

- **Settings Storage**: Configuration is saved to browser `localStorage['pos_printer_settings']` so settings persist across page reloads without database overhead.
- **Tenant Isolation**: Server-side PHP session authorization ensures that staff can only render and print orders matching their logged-in `$_SESSION['supplier_user']['supplier_id']`.
