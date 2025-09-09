@echo off
cd /d "%~dp0"
echo 启动注册服务...
start "Register" php think worker:gateway_win register
timeout /t 3 /nobreak >nul
echo 启动业务处理服务...
start "Business" php think worker:gateway_win business_worker
timeout /t 3 /nobreak >nul
echo 启动网关服务...
start "Gateway" php think worker:gateway_win gateway
echo 所有服务已启动完成！
pause
