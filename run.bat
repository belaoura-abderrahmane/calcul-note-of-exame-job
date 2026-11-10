@echo off
chcp 65001 >nul
title برنامج حساب نقاط المسابقات
cd /d "%~dp0"

call check.bat
if errorlevel 1 goto :fail

if not exist vendor\autoload.php (
    echo [X] المكتبات غير مثبتة. شغّل install.bat أولاً.
    goto :fail
)
if not exist .env (
    echo [X] ملف .env غير موجود. شغّل install.bat أولاً.
    goto :fail
)

rem البحث عن منفذ غير مستعمل ابتداءً من 8000
set PORT=8000
for /f %%p in ('powershell -NoProfile -Command "$used=[System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners().Port; $p=8000; while($used -contains $p){$p++}; $p"') do set PORT=%%p

php artisan optimize:clear >nul 2>&1

echo.
echo ================================================
echo   الخادم يعمل على: http://127.0.0.1:%PORT%/admin
echo   لا تغلق هذه النافذة أثناء استعمال البرنامج.
echo ================================================
echo.
start "" cmd /c "timeout /t 4 >nul & start http://127.0.0.1:%PORT%/admin"
php artisan serve --host=127.0.0.1 --port=%PORT% --no-reload

echo.
echo توقف الخادم. إذا ظهرت رسالة خطأ أعلاه أرسل صورة لها.
:fail
echo.
echo آخر سطور من سجل الأخطاء (storage\logs\laravel.log):
if exist storage\logs\laravel.log powershell -NoProfile -Command "Get-Content -Tail 15 'storage\logs\laravel.log'"
pause
