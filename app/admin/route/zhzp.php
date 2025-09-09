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
    
    // 设备分类管理
    Route::group('device_classify', function () {
        Route::get('index', 'zhzp.DeviceClassify/index');
        Route::get('add', 'zhzp.DeviceClassify/add');
        Route::get('edit', 'zhzp.DeviceClassify/edit');
        Route::post('add', 'zhzp.DeviceClassify/add');
        Route::post('edit', 'zhzp.DeviceClassify/edit');
        Route::post('remove', 'zhzp.DeviceClassify/remove');
        Route::post('forbid', 'zhzp.DeviceClassify/forbid');
        Route::post('resume', 'zhzp.DeviceClassify/resume');
        Route::post('state', 'zhzp.DeviceClassify/state');
        Route::get('detail', 'zhzp.DeviceClassify/detail');
        Route::get('getClassifyList', 'zhzp.DeviceClassify/getClassifyList');
    });
    
    // 设备分组管理
    Route::group('device_group', function () {
        Route::get('index', 'zhzp.DeviceGroup/index');
        Route::get('add', 'zhzp.DeviceGroup/add');
        Route::get('edit', 'zhzp.DeviceGroup/edit');
        Route::post('add', 'zhzp.DeviceGroup/add');
        Route::post('edit', 'zhzp.DeviceGroup/edit');
        Route::post('remove', 'zhzp.DeviceGroup/remove');
        Route::post('forbid', 'zhzp.DeviceGroup/forbid');
        Route::post('resume', 'zhzp.DeviceGroup/resume');
        Route::post('state', 'zhzp.DeviceGroup/state');
        Route::get('detail', 'zhzp.DeviceGroup/detail');
        Route::get('getGroupList', 'zhzp.DeviceGroup/getGroupList');
    });
    
    // 设备指令管理
    Route::group('device_instruct', function () {
        Route::get('index', 'zhzp.DeviceInstruct/index');
        Route::get('add', 'zhzp.DeviceInstruct/add');
        Route::get('edit', 'zhzp.DeviceInstruct/edit');
        Route::post('add', 'zhzp.DeviceInstruct/add');
        Route::post('edit', 'zhzp.DeviceInstruct/edit');
        Route::post('remove', 'zhzp.DeviceInstruct/remove');
        Route::post('forbid', 'zhzp.DeviceInstruct/forbid');
        Route::post('resume', 'zhzp.DeviceInstruct/resume');
        Route::post('state', 'zhzp.DeviceInstruct/state');
        Route::get('detail', 'zhzp.DeviceInstruct/detail');
        Route::get('getInstructList', 'zhzp.DeviceInstruct/getInstructList');
    });
    
    // 设备推送管理
    Route::group('device_push', function () {
        Route::get('index', 'zhzp.DevicePush/index');
        Route::get('add', 'zhzp.DevicePush/add');
        Route::get('edit', 'zhzp.DevicePush/edit');
        Route::post('add', 'zhzp.DevicePush/add');
        Route::post('edit', 'zhzp.DevicePush/edit');
        Route::post('remove', 'zhzp.DevicePush/remove');
        Route::post('forbid', 'zhzp.DevicePush/forbid');
        Route::post('resume', 'zhzp.DevicePush/resume');
        Route::post('state', 'zhzp.DevicePush/state');
        Route::get('detail', 'zhzp.DevicePush/detail');
        Route::get('getPushList', 'zhzp.DevicePush/getPushList');
        Route::get('getDeviceList', 'zhzp.DevicePush/getDeviceList');
    });
})->middleware(['admin', 'auth']);
