<?php

/**
 * 多设备配置文件
 * 配置多个小智AI设备的连接信息
 */

return [
    // 设备配置示例
    'devices' => [
        // 设备1 - 主设备
        'device_001' => [
            'id' => 'device_001',
            'name' => '测试设备',
            'wss_url' => 'wss://api.xiaozhi.me/mcp/?token=eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjM4OTQxMCwiYWdlbnRJZCI6NjM5MjM4LCJlbmRwb2ludElkIjoiYWdlbnRfNjM5MjM4IiwicHVycG9zZSI6Im1jcC1lbmRwb2ludCIsImlhdCI6MTc1NzAwNTcxNn0.7MyWiZDCqQkGUv-D5oGX4C6A_I_RgZKwGWmZYX5kAPgMmfGVzuaCm8Gq7XLN5C0fKuC1eA_0C-lQxWQtOHyz2A',
            'enabled' => true,
            'metadata' => [
                'location' => '办公室',
                'type' => 'primary',
                'description' => '测试设备'
            ]
        ],
        
        // 设备2 - 备用设备（示例，需要替换为真实的token）
        'device_002' => [
            'id' => 'device_002',
            'name' => '宏山社区儿童活动空间设备',
            'wss_url' => 'wss://api.xiaozhi.me/mcp/?token=eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjM4OTQxMCwiYWdlbnRJZCI6NjI4NDk1LCJlbmRwb2ludElkIjoiYWdlbnRfNjI4NDk1IiwicHVycG9zZSI6Im1jcC1lbmRwb2ludCIsImlhdCI6MTc1NzAwMTUxN30.w8C264vZ65LQaG4fapFGg-IT4ZpCIPTbKbqE1a30VN00I-uITOgmsAOEFPp2ZIDfkSxdudgpTXpZBpUtd7N30A',
            'enabled' => true, // 暂时禁用，需要配置真实token
            'metadata' => [
                'location' => '会议室',
                'type' => 'secondary',
                'description' => '宏山社区儿童活动空间设备'
            ]
        ],

    ],
    
    // 全局配置
    'global' => [
        'auto_reconnect' => true,
        'reconnect_interval' => 30, // 秒
        'max_reconnect_attempts' => 5,
        'connection_timeout' => 10, // 秒
        'log_level' => 'info'
    ]
];
