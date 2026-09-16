@echo off
rem Dev launcher: serves the Laravel slice with the pgsql driver enabled
rem via -d flags (no global XAMPP config change). router.php streams real
rem static files (icon.svg etc.) directly and routes the rest to Laravel.
cd /d %~dp0
php -d extension=pdo_pgsql -d extension=pgsql -S 0.0.0.0:8080 -t public router.php
