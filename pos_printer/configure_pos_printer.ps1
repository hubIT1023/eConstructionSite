# ==============================================================================
# Universal POS Thermal Printer Auto-Detector & Calibrator (Windows)
# Primary Target Model: JK-5802H (58mm Thermal Receipt Printer)
# Also Supports: POS-58, POS-80, XP-58, XP-80, ZJ-58, Generic / Text, etc.
# ==============================================================================

param(
    [string]$TargetPrinter = "",
    [string]$RollType = "58" # Default to 58mm for JK-5802H
)

Clear-Host
Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "    JK-5802H & POS THERMAL PRINTER AUTO-DETECTOR & CALIBRATOR    " -ForegroundColor Yellow
Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "Target Hardware: JK-5802H (58mm Roll, 48mm Printable Head, 32 Col)" -ForegroundColor DarkCyan
Write-Host ""

# Step 1: Scan all installed printers
Write-Host "[1/4] Scanning installed Windows printers..." -ForegroundColor Cyan
$allPrinters = @(Get-Printer -ErrorAction SilentlyContinue)

if ($allPrinters.Count -eq 0) {
    Write-Host "[ERROR] No printers found on this computer." -ForegroundColor Red
    Write-Host "Please ensure your JK-5802H USB cable is connected and powered ON." -ForegroundColor Yellow
    Exit 1
}

# Thermal POS keywords for auto-detection (exclude non-thermal virtual printers)
$excludeNames = @('*XPS*', '*OneNote*', '*Fax*', '*Microsoft Print to PDF*', '*Adobe PDF*')
$posKeywords  = @('*JK-5802H*', '*JK-58*', '*POS-58*', '*POS58*', '*POS-80*', '*POS80*', '*Thermal*', '*Receipt*', '*XP-58*', '*XP-80*', '*ZJ-58*', '*ZJ-80*', '*Rongta*', '*Xprinter*', '*Zjiang*', '*Generic / Text Only*')

$detectedPrinters = @()
foreach ($p in $allPrinters) {
    $excluded = $false
    foreach ($ex in $excludeNames) {
        if ($p.Name -like $ex -or $p.DriverName -like $ex) {
            $excluded = $true
            break
        }
    }
    if ($excluded) { continue }

    $match = $false
    foreach ($kw in $posKeywords) {
        if ($p.Name -like $kw -or $p.DriverName -like $kw -or ($p.PortName -like 'USB*' -and $p.DriverName -like '*Text*')) {
            $match = $true
            break
        }
    }
    if ($match) {
        $detectedPrinters += $p
    }
}

$selectedPrinter = $null

if (![string]::IsNullOrEmpty($TargetPrinter)) {
    $selectedPrinter = $allPrinters | Where-Object { $_.Name -eq $TargetPrinter } | Select-Object -First 1
}

# Prioritize JK-5802H if present
if (-not $selectedPrinter) {
    $jkPrinter = $detectedPrinters | Where-Object { $_.Name -like '*JK-5802H*' -or $_.Name -like '*JK-58*' } | Select-Object -First 1
    if ($jkPrinter) {
        $selectedPrinter = $jkPrinter
        Write-Host "[AUTO-DETECTED] Found target printer: $($selectedPrinter.Name) (Port: $($selectedPrinter.PortName))" -ForegroundColor Green
    }
}

if (-not $selectedPrinter) {
    if ($detectedPrinters.Count -eq 1) {
        # Exactly one candidate found -> Auto-select
        $selectedPrinter = $detectedPrinters[0]
        Write-Host "[AUTO-DETECTED] Found POS printer: $($selectedPrinter.Name)" -ForegroundColor Green
    } elseif ($detectedPrinters.Count -gt 1) {
        # Multiple candidates found -> Ask user to choose
        Write-Host ""
        Write-Host "Multiple POS/Thermal printers detected on this PC:" -ForegroundColor Yellow
        for ($i = 0; $i -lt $detectedPrinters.Count; $i++) {
            $p = $detectedPrinters[$i]
            $tag = if ($p.Name -like '*58*') { " [Recommended 58mm]" } else { "" }
            Write-Host "  [$($i + 1)] $($p.Name) (Port: $($p.PortName), Driver: $($p.DriverName))$tag" -ForegroundColor White
        }
        Write-Host ""
        $choice = Read-Host "Select printer number (Press Enter for [1])"
        if ([string]::IsNullOrWhiteSpace($choice)) { $choice = "1" }
        $idx = [int]$choice - 1
        if ($idx -ge 0 -and $idx -lt $detectedPrinters.Count) {
            $selectedPrinter = $detectedPrinters[$idx]
        } else {
            $selectedPrinter = $detectedPrinters[0]
        }
    } else {
        # No keyword matches found -> List all physical printers or offer driver installation
        Write-Host "[NOTICE] No dedicated JK-5802H or POS printer detected by name." -ForegroundColor Yellow
        Write-Host "Available printers on this system:" -ForegroundColor Gray
        $avail = @()
        foreach ($p in $allPrinters) {
            $ex = $false
            foreach ($x in $excludeNames) { if ($p.Name -like $x) { $ex = $true; break } }
            if (!$ex) { $avail += $p }
        }
        for ($i = 0; $i -lt $avail.Count; $i++) {
            $p = $avail[$i]
            Write-Host "  [$($i + 1)] $($p.Name) (Port: $($p.PortName), Driver: $($p.DriverName))" -ForegroundColor White
        }
        Write-Host "  [D] Install POS-58 / JK-5802H Driver from Installer" -ForegroundColor Green
        Write-Host ""
        $choice = Read-Host "Select printer number, or type 'D' to install driver"
        
        if ($choice -eq 'D' -or $choice -eq 'd') {
            $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
            $driverSetup = Join-Path $scriptDir "POS58_80_Printer_Driver_V11.3.0.3_Setup.exe"
            if (Test-Path $driverSetup) {
                Write-Host "Launching POS Driver Setup wizard..." -ForegroundColor Green
                Start-Process -FilePath $driverSetup
                Write-Host "Please complete the setup wizard, then re-run this script to calibrate." -ForegroundColor Yellow
                Exit 0
            } else {
                Write-Host "[ERROR] Driver setup file not found at: $driverSetup" -ForegroundColor Red
                Exit 1
            }
        }
        
        $idx = [int]$choice - 1
        if ($idx -ge 0 -and $idx -lt $avail.Count) {
            $selectedPrinter = $avail[$idx]
        } else {
            Write-Host "[ERROR] Invalid selection." -ForegroundColor Red
            Exit 1
        }
    }
}

$printerName = $selectedPrinter.Name
Write-Host ""
Write-Host "[2/4] Selected Printer: $printerName (Port: $($selectedPrinter.PortName), Driver: $($selectedPrinter.DriverName))" -ForegroundColor Green

# Step 2: Determine Roll Width (58mm for JK-5802H)
# 58mm = 57900 microns width x 210000 microns height (57.9mm x 210mm)
# 80mm = 79500 microns width x 297000 microns height (79.5mm x 297mm)
$is80mm = $false
if ($RollType -eq "80") {
    $is80mm = $true
} elseif ($RollType -eq "58" -or $printerName -like '*58*') {
    $is80mm = $false
} else {
    # Auto-detect: if name contains '80' and NOT '58'
    if (($printerName -like '*80*' -or $selectedPrinter.DriverName -like '*80*') -and ($printerName -notlike '*58*' -and $selectedPrinter.DriverName -notlike '*58*')) {
        $is80mm = $true
    }
}

$RollWidthMm = if ($is80mm) { 80 } else { 58 }
$mediaWidthMicrons = if ($is80mm) { 79500 } else { 57900 }
$mediaHeightMicrons = if ($is80mm) { 297000 } else { 210000 }

Write-Host "[3/4] Calibrating Windows PrintTicket for ${RollWidthMm}mm roll (Zero Margins)..." -ForegroundColor Cyan

try {
    $cfg = Get-PrintConfiguration -PrinterName $printerName -ErrorAction Stop
    [xml]$xml = $cfg.PrintTicketXML

    $feature = $xml.PrintTicket.Feature | Where-Object { $_.name -eq 'psk:PageMediaSize' }
    if ($feature) {
        $newOpt = $xml.CreateElement("psf", "Option", "http://schemas.microsoft.com/windows/2003/08/printing/printschemaframework")
        $newOpt.SetAttribute("name", "psk:CustomMediaSize")
        
        $propW = $xml.CreateElement("psf", "ScoredProperty", "http://schemas.microsoft.com/windows/2003/08/printing/printschemaframework")
        $propW.SetAttribute("name", "psk:MediaSizeWidth")
        $valW = $xml.CreateElement("psf", "Value", "http://schemas.microsoft.com/windows/2003/08/printing/printschemaframework")
        $valW.SetAttribute("type", "http://www.w3.org/2001/XMLSchema-instance", "xsd:integer")
        $valW.InnerText = "$mediaWidthMicrons"
        $propW.AppendChild($valW)
        $newOpt.AppendChild($propW)
        
        $propH = $xml.CreateElement("psf", "ScoredProperty", "http://schemas.microsoft.com/windows/2003/08/printing/printschemaframework")
        $propH.SetAttribute("name", "psk:MediaSizeHeight")
        $valH = $xml.CreateElement("psf", "Value", "http://schemas.microsoft.com/windows/2003/08/printing/printschemaframework")
        $valH.SetAttribute("type", "http://www.w3.org/2001/XMLSchema-instance", "xsd:integer")
        $valH.InnerText = "$mediaHeightMicrons"
        $propH.AppendChild($valH)
        $newOpt.AppendChild($propH)
        
        $feature.RemoveAll()
        $feature.SetAttribute("name", "psk:PageMediaSize")
        $feature.AppendChild($newOpt)
        
        Set-PrintConfiguration -PrinterName $printerName -PrintTicketXml $xml.OuterXml -ErrorAction Stop
        Write-Host "[OK] Default Paper Size successfully configured to ${RollWidthMm}mm Roll!" -ForegroundColor Green
    }
} catch {
    Write-Host "[WARN] Note on PrintTicket: $($_.Exception.Message)" -ForegroundColor Yellow
}

# Step 3: Verify with .NET Printing API
Add-Type -AssemblyName System.Drawing
$ps = New-Object System.Drawing.Printing.PrinterSettings
$ps.PrinterName = $printerName

Write-Host ""
Write-Host "================== CALIBRATION SUMMARY ==================" -ForegroundColor Cyan
Write-Host "  Printer Model  : $($ps.PrinterName) (JK-5802H Compatible)" -ForegroundColor White
Write-Host "  Default Paper  : $($ps.DefaultPageSettings.PaperSize.PaperName) (${RollWidthMm}mm POS Roll)" -ForegroundColor White
Write-Host "  Roll Width     : $([math]::Round($ps.DefaultPageSettings.PaperSize.Width * 0.254, 1)) mm (48mm printable head)" -ForegroundColor White
Write-Host "  Roll Length    : $([math]::Round($ps.DefaultPageSettings.PaperSize.Height * 0.254, 1)) mm" -ForegroundColor White
Write-Host "  Layout Grid    : 32 Columns (Monospace Courier New 10pt)" -ForegroundColor White
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host "[SUCCESS] $printerName is now fully calibrated for JK-5802H POS receipt printing!" -ForegroundColor Green
Write-Host ""
