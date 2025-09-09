CHCP 65001
start "Register Service" php think worker:gateway_win register
start "Business Worker Service" php think worker:gateway_win business_worker
start "Gateway Service" php think worker:gateway_win gateway
echo 所有服务已启动，按任意键关闭所有服务...
pause
taskkill /f /im php.exe
