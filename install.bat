@echo off
chcp 65001 >nul
title تثبيت برنامج حساب نقاط المسابقات
cd /d "%~dp0"

echo ==========================================
echo   تثبيت برنامج حساب نقاط مسابقات التوظيف
echo ==========================================
echo.
echo تأكد أن Laragon يعمل (Start All) وأن MySQL مشغل.
echo.

call check.bat
if errorlevel 1 goto :error
echo.

if not exist .env (
    copy .env.example .env >nul
    echo [1/4] تم إنشاء ملف .env
)

echo [2/4] تثبيت المكتبات (composer install)...
call composer install --no-interaction
if errorlevel 1 goto :error

echo [3/4] توليد مفتاح التطبيق...
php artisan key:generate --force

echo [4/4] إنشاء قاعدة البيانات والجداول والمستخدم...
echo (إذا سُئلت عن إنشاء قاعدة البيانات اكتب yes)
php artisan migrate --seed
if errorlevel 1 goto :error

php artisan filament:optimize-clear >nul 2>&1
php artisan optimize:clear >nul 2>&1

echo.
echo ==========================================
echo   تم التثبيت بنجاح
echo   شغّل run.bat ثم افتح: http://127.0.0.1:8000/admin
echo   البريد: admin@admin.com   كلمة المرور: password
echo ==========================================
pause
exit /b 0

:error
echo.
echo حدث خطأ أثناء التثبيت. راجع الرسائل أعلاه.
pause
exit /b 1
