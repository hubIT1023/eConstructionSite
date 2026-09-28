# JK-5802H POS Thermal Printer Deployment & Setup Package

This package is configured specifically for the **JK-5802H 58mm POS Thermal Receipt Printer** (and compatible 58mm ESC/POS hardware) for fast deployment across cashier workstations.

---

## 🖨️ JK-5802H Hardware Profile & Specifications

| Parameter | Specification | Application Setting |
| :--- | :--- | :--- |
| **Model** | **JK-5802H** | Primary Target |
| **Roll Width** | **57.5mm ± 0.5mm** (58mm standard) | `57.9mm` / `58mm` |
| **Thermal Head Width** | **48mm** (384 dots @ 203 DPI) | `48mm` Printable Area |
| **Standard Columns** | **32 Characters** (Font A 12×24) | `Courier New, 10pt` |
| **Interface** | USB (`USB001` - `USB004`) / Bluetooth | Auto-Detected |
| **Command Standard** | ESC/POS | Native Monospace Grid |

---

## 📁 Package Contents in `pos_printer/`

| File | Purpose |
| :--- | :--- |
| **`run_setup.bat`** | **1-Click Auto-Detector:** Scans Windows printers, prioritizes `JK-5802H`, and configures 58mm paper size with zero margins. |
| **`configure_pos_printer.ps1`** | **PowerShell Calibration Engine:** Configures Windows PrintTicket DEVMODE for 58mm continuous roll. |
| **`POS58_80_Printer_Driver_V11.3.0.3_Setup.exe`** | **Official Windows Driver Installer:** Full setup wizard for Windows 7/8/10/11. |
| **`POS_Receipt_Printer_Driver_New_Setup.exe`** | **Latest Thermal Printer Installer:** Alternative installer package. |

---

## 🚀 Deploying to Any Cashier PC

1. **Copy Folder:** Copy `pos_printer` to a USB drive or network folder, and paste it onto the cashier PC.
2. **Connect Hardware:** Plug the **JK-5802H** USB cable into the PC and switch it **ON**.
3. **Execute Setup:** Double-click **`run_setup.bat`**.
   - The script auto-detects `JK-5802H` and sets it to **58mm roll (Zero Margins)**.
   - If the printer driver is not yet installed on that PC, the script prompts you to install it with 1 click.

---

## 🌐 1-Time Google Chrome Print Setting (On First Print)

When printing from the POS ([https://econstruction-supply.site/supplier/pos.php](https://econstruction-supply.site/supplier/pos.php)):

* **Destination:** `JK-5802H`
* **Paper size:** `58mm` / `custom` / `5` (57.9 × 210 mm)
* **Margins:** **`None`** (0)
* **Scale:** **`100%`**
* **Headers/Footers:** **`Unchecked`** (OFF)

Chrome saves these settings automatically for all future receipts!
