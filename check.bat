@echo off
chcp 65001 >nul
cd /d "%~dp0"
set OK=1

where php >nul 2>&1
if errorlevel 1 (
    echo [X] PHP غير موجود في PATH. افتح Terminal من Laragon ^(زر Terminal^) وشغّل الملف من هناك.
    set OK=0
    goto :end
)

for /f "delims=" %%v in ('php -r "echo PHP_VERSION;"') do set PHPV=%%v
echo [i] إصدار PHP: %PHPV%
php -r "exit(version_compare(PHP_VERSION,'8.3.0','>=')?0:1);"
if errorlevel 1 (
    echo [X] البرنامج يحتاج PHP 8.3 أو أحدث. في Laragon: Menu ^> PHP ^> Version واختر 8.3 أو أحدث
    echo     ^(إن لم يكن متوفراً حمّل PHP 8.3 x64 Thread Safe من windows.php.net وضعه في C:\laragon\bin\php^)
    set OK=0
)

for %%e in (intl zip mbstring openssl pdo_mysql fileinfo) do (
    php -r "exit(extension_loaded('%%e')?0:1);"
    if errorlevel 1 (
        echo [X] الإضافة %%e غير مفعلة. في Laragon: Menu ^> PHP ^> Extensions ^> %%e
        set OK=0
    )
)

:end
if "%OK%"=="1" (echo [OK] بيئة PHP جاهزة) else (exit /b 1)
exit /b 0
