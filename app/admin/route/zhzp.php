<?php

// +----------------------------------------------------------------------
// | Admin Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------

use think\facade\Route;

// 设备管理路由
Route::group('zhzp', function () {
    // 设备管理
    Route::group('device', function () {
        Route::get('index', 'zhzp.Device/index');
        Route::get('add', 'zhzp.Device/add');
        Route::get('edit', 'zhzp.Device/edit');
        Route::post('add', 'zhzp.Device/add');
        Route::post('edit', 'zhzp.Device/edit');
        Route::post('remove', 'zhzp.Device/remove');
        Route::post('forbid', 'zhzp.Device/forbid');
        Route::post('resume', 'zhzp.Device/resume');
        Route::post('state', 'zhzp.Device/state');
        Route::get('detail', 'zhzp.Device/detail');
        Route::post('sendInstruct', 'zhzp.Device/sendInstruct');
        Route::get('getSuppliers', 'zhzp.Device/getSuppliers');
        Route::get('stats', 'zhzp.Device/stats');
    });
})->middleware(['admin', 'auth']);
