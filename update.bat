@echo off
chcp 65001 >nul
title تحديث برنامج حساب نقاط المسابقات
cd /d "%~dp0"

echo ==========================================
echo   تحديث البرنامج إلى آخر نسخة من GitHub
echo ==========================================
echo.

if not exist .git (
    echo [X] هذا المجلد ليس نسخة git. انسخ المشروع بالأمر:
    echo     git clone https://github.com/belaoura-abderrahmane/calcul-note-of-exame-job.git
    goto :error
)

echo [1/4] جلب آخر التعديلات...
git pull origin main
if errorlevel 1 goto :error

echo [2/4] تحديث المكتبات...
call composer install --no-interaction
if errorlevel 1 goto :error

echo [3/4] تحديث قاعدة البيانات (سلالم التنقيط...)...
php artisan migrate --force
if errorlevel 1 goto :error

echo [4/4] مسح الذاكرة المؤقتة...
php artisan optimize:clear >nul 2>&1
php artisan filament:optimize-clear >nul 2>&1

echo.
echo ==========================================
echo   تم التحديث. أعد تحميل الصفحة في المتصفح بـ Ctrl+F5
echo ==========================================
pause
exit /b 0

:error
echo.
echo حدث خطأ أثناء التحديث. أرسل صورة لهذه النافذة.
pause
exit /b 1
