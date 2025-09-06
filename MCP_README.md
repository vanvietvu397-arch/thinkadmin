# MCP WSS 服务器 - 小智AI多设备集成

这个项目实现了一个MCP（Model Context Protocol）服务器，通过WSS（WebSocket Secure）协议连接到多个小智AI设备，提供社区信息查询工具和设备管理功能。

## 功能特性

- ✅ 支持WSS协议连接到小智AI
- ✅ **多设备支持** - 可同时连接多个小智AI设备
- ✅ **设备识别** - 自动识别请求来源设备
- ✅ 提供 `queryCommunityInfo` 工具查询社区信息
- ✅ 提供 `queryDeviceInfo` 工具查询设备信息
- ✅ 提供 `queryAllDevices` 工具查询所有设备状态
- ✅ 支持多种查询类型（成员数量、基本信息、完整信息）
- ✅ 完整的错误处理和日志记录
- ✅ 基于php-mcp SDK构建

## 安装依赖

```bash
composer install
```

## 文件结构

```
├── mcp_server.php                    # 单设备MCP服务器
├── mcp_multi_device_server.php       # 多设备MCP服务器
├── config/
│   └── devices.php                   # 多设备配置文件
├── app/admin/controller/
│   ├── McpWssTransport.php          # WSS传输层实现
│   ├── CommunityTools.php           # 社区信息查询工具
│   └── DeviceManager.php            # 设备管理器
└── MCP_README.md                    # 说明文档
```

## 使用方法

### 1. 单设备模式

```bash
php mcp_server.php
```

服务器将连接到单个小智AI设备。

### 2. 多设备模式（推荐）

```bash
php mcp_multi_device_server.php
```

服务器将连接到配置中的所有启用设备。

#### 配置多设备

编辑 `config/devices.php` 文件：

```php
'devices' => [
    'device_001' => [
        'id' => 'device_001',
        'name' => '小智AI主设备',
        'wss_url' => 'wss://api.xiaozhi.me/mcp/?token=YOUR_TOKEN_1',
        'enabled' => true,
        'metadata' => [
            'location' => '办公室',
            'type' => 'primary'
        ]
    ],
    'device_002' => [
        'id' => 'device_002', 
        'name' => '小智AI备用设备',
        'wss_url' => 'wss://api.xiaozhi.me/mcp/?token=YOUR_TOKEN_2',
        'enabled' => true,
        'metadata' => [
            'location' => '会议室',
            'type' => 'secondary'
        ]
    ]
]
```

### 3. 在小智AI中使用

连接成功后，小智AI将能够调用以下工具：

#### queryCommunityInfo

查询社区信息，支持以下参数：

- `communityId` (必需): 社区ID
- `type` (可选): 查询类型
  - `member_count`: 仅查询成员数量
  - `basic_info`: 查询基本信息
  - `all`: 查询完整信息（默认）

**示例用法：**

```
查询社区 community_001 有多少人
```

```
查询社区 community_002 的基本信息
```

```
查询社区 community_003 的完整信息
```

**多设备特性：**
- 自动识别请求来源设备
- 返回结果包含设备信息
- 支持不同设备同时查询

#### queryDeviceInfo

查询当前连接的设备信息。

**示例用法：**

```
查询当前设备信息
```

#### queryAllDevices

查询所有已注册设备的状态。

**示例用法：**

```
查询所有设备状态
```

## 支持的社区

系统预置了以下测试社区：

- `community_001`: ThinkAdmin开发者社区 (1250人)
- `community_002`: PHP技术交流群 (856人)  
- `community_003`: Web开发新手村 (2341人)
- 其他ID: 返回默认社区信息 (500人)

## 返回数据格式

### 成员数量查询
```json
{
    "community_id": "community_001",
    "member_count": 1250,
    "message": "社区 ThinkAdmin开发者社区 共有 1250 人"
}
```

### 基本信息查询
```json
{
    "community_id": "community_002",
    "name": "PHP技术交流群",
    "description": "PHP技术讨论和学习交流群",
    "created_at": "2023-03-20 14:20:00",
    "message": "社区基本信息：PHP技术交流群 - PHP技术讨论和学习交流群"
}
```

### 完整信息查询（多设备）
```json
{
    "community_id": "community_003",
    "query_time": "2025-09-04 15:46:33",
    "device_info": {
        "device_id": "device_001",
        "device_name": "小智AI主设备",
        "session_id": "wss-session-device_001-abc123"
    },
    "name": "Web开发新手村",
    "description": "适合Web开发初学者的学习社区",
    "member_count": 2341,
    "active_members": 156,
    "created_at": "2023-02-10 16:45:00",
    "last_activity": "2024-01-20 11:20:00",
    "status": "active",
    "message": "来自设备 小智AI主设备 的查询：社区 Web开发新手村 共有 2341 人，其中活跃用户 156 人"
}
```

### 设备信息查询
```json
{
    "device_info": {
        "device_id": "device_001",
        "device_name": "小智AI主设备",
        "session_id": "wss-session-device_001-abc123",
        "connected": true,
        "last_seen": "2025-09-04 15:46:33",
        "wss_url": "wss://api.xiaozhi.me/mcp/?token=..."
    },
    "message": "设备信息：小智AI主设备 (ID: device_001)，连接状态：已连接"
}
```

### 所有设备状态查询
```json
{
    "devices": [
        {
            "id": "device_001",
            "name": "小智AI主设备",
            "wss_url": "wss://api.xiaozhi.me/mcp/?token=...",
            "connected": true,
            "last_seen": "2025-09-04 15:46:33"
        },
        {
            "id": "device_002",
            "name": "小智AI备用设备", 
            "wss_url": "wss://api.xiaozhi.me/mcp/?token=...",
            "connected": false,
            "last_seen": null
        }
    ],
    "summary": {
        "total_devices": 2,
        "connected_devices": 1,
        "disconnected_devices": 1
    },
    "message": "设备状态总览：共 2 个设备，其中 1 个已连接"
}
```

## 技术实现

### WSS传输层
- 基于 `ratchet/pawl` 实现WebSocket客户端
- 支持SSL/TLS安全连接
- 自动重连和错误处理

### MCP协议
- 基于 `php-mcp/server` SDK
- 支持工具发现和调用
- 完整的JSON-RPC消息处理

### 工具系统
- 使用PHP属性注解定义工具
- 支持参数验证和类型检查
- 结构化错误处理

## 扩展开发

### 添加新工具

1. 在 `CommunityTools.php` 中添加新方法
2. 使用 `#[McpTool]` 属性注解
3. 重新启动服务器

### 修改社区数据

编辑 `CommunityTools.php` 中的 `getCommunityData()` 方法，添加或修改社区信息。

### 自定义传输层

继承 `McpWssTransport` 类并重写相关方法来实现自定义的传输逻辑。

## 故障排除

### 连接问题
- 检查网络连接
- 验证WSS URL和token
- 查看服务器日志

### 工具调用问题
- 确认工具已正确注册
- 检查参数格式
- 查看错误日志

## 许可证

MIT License
