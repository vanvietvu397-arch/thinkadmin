CHCP 65001
php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING & ~E_STRICT" workerman\start_register.php workerman\start_web.php workerman\start_gateway.php workerman\start_businessworker.php
pause
