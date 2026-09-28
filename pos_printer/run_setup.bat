@echo off
title Universal POS Thermal Printer Auto-Setup
echo ================================================================
echo  Universal POS Thermal Printer Auto-Detector ^& Calibrator
echo ================================================================
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0configure_pos_printer.ps1"
echo.
echo Press any key to exit...
pause >nul
