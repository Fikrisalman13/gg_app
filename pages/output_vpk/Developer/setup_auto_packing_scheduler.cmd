@echo off
setlocal EnableExtensions
set "TASK_NAME=GG Output Packing Otomatis"
set "PHP_EXE=C:\xampp\php\php.exe"
set "WORKER=C:\xampp\htdocs\gg_app\pages\output_vpk\auto_packing_worker.php"

net session >nul 2>&1
if not "%errorlevel%"=="0" (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

:menu
cls
echo ==============================================
echo  Output Packing - Windows Task Scheduler
echo ==============================================
echo.
echo  [1] Aktifkan / pasang penarikan otomatis
echo  [2] Nonaktifkan penarikan otomatis
echo  [3] Lihat status
echo  [0] Keluar
echo.
set /p "choice=Pilih aksi: "
if "%choice%"=="1" goto enable
if "%choice%"=="2" goto disable
if "%choice%"=="3" goto status
if "%choice%"=="0" exit /b 0
echo Pilihan tidak valid.
pause
goto menu

:enable
if not exist "%PHP_EXE%" (
    echo PHP tidak ditemukan: %PHP_EXE%
    pause
    goto menu
)
if not exist "%WORKER%" (
    echo Worker tidak ditemukan: %WORKER%
    pause
    goto menu
)
schtasks.exe /Create /TN "%TASK_NAME%" /TR "\"%PHP_EXE%\" \"%WORKER%\"" /SC MINUTE /MO 5 /RU SYSTEM /RL HIGHEST /F
if errorlevel 1 (
    echo Gagal memasang task.
) else (
    schtasks.exe /Change /TN "%TASK_NAME%" /ENABLE >nul
    echo.
    echo Task aktif. Worker berjalan tiap 5 menit.
    echo Switch dan jam pada halaman Otomatis tetap menentukan kapan snapshot dibuat.
)
pause
goto menu

:disable
schtasks.exe /Change /TN "%TASK_NAME%" /DISABLE
if errorlevel 1 (
    echo Task tidak ditemukan atau gagal dinonaktifkan.
) else (
    echo Task dinonaktifkan. Pengaturan halaman tidak diubah.
)
pause
goto menu

:status
schtasks.exe /Query /TN "%TASK_NAME%" /FO LIST /V
pause
goto menu