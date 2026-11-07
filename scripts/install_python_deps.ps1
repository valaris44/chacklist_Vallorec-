# PowerShell script to install project Python dependencies using the bundled project python
# Run as: PowerShell -ExecutionPolicy Bypass -File scripts\install_python_deps.ps1 [C:\full\path\to\python.exe]

param(
    [string]$PythonPath
)

if (-not $PythonPath) {
    # default to the bundled python.exe inside the project (Windows)
    $PythonPath = Join-Path $PSScriptRoot "..\py\python.exe"
}

if (-not (Test-Path $PythonPath)) {
    Write-Host "Project python not found at $PythonPath. Please provide the full path to python.exe as the first argument." -ForegroundColor Yellow
    exit 1
}

Write-Host "Using python: $PythonPath"
& $PythonPath -m pip install --upgrade pip setuptools wheel
& $PythonPath -m pip install openpyxl pillow PyPDF2 reportlab
Write-Host "Installed openpyxl, pillow, PyPDF2 and reportlab (if possible)." -ForegroundColor Green
Write-Host "If Pillow fails to install, consider installing Visual C++ Build Tools or using a Python distribution with prebuilt wheels (3.11 recommended)." -ForegroundColor Yellow
