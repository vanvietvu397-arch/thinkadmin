<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        // Windows兼容的GatewayWorker命令
        'worker:gateway_win' => \app\command\GatewayWorkerForWin::class,
    ],
];
