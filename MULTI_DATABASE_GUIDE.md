# ThinkAdmin 多数据库连接配置指南

## 概述

本指南将帮助你在ThinkAdmin项目中配置和使用多个MySQL数据库连接。

## 配置步骤

### 1. 修改数据库配置文件

已修改 `config/database.php` 文件，添加了第二个MySQL数据库连接配置：

```php
'connections' => [
    'mysql' => [
        // 第一个数据库配置（默认）
        'type' => 'mysql',
        'hostname' => env('DB_MYSQL_HOST', '127.0.0.1'),
        'hostport' => env('DB_MYSQL_PORT', '3306'),
        'database' => env('DB_MYSQL_DATABASE', 'thinkadmin'),
        'username' => env('DB_MYSQL_USERNAME', 'root'),
        'password' => env('DB_MYSQL_PASSWORD', ''),
        // ... 其他配置
    ],
    'mysql2' => [
        // 第二个数据库配置
        'type' => 'mysql',
        'hostname' => env('DB_MYSQL2_HOST', '127.0.0.1'),
        'hostport' => env('DB_MYSQL2_PORT', '3306'),
        'database' => env('DB_MYSQL2_DATABASE', 'thinkadmin2'),
        'username' => env('DB_MYSQL2_USERNAME', 'root'),
        'password' => env('DB_MYSQL2_PASSWORD', ''),
        // ... 其他配置
    ],
]
```

### 2. 配置环境变量

创建 `.env` 文件并添加以下配置：

```env
# 第一个MySQL数据库（默认）
DB_TYPE=mysql
DB_MYSQL_HOST=127.0.0.1
DB_MYSQL_PORT=3306
DB_MYSQL_DATABASE=thinkadmin
DB_MYSQL_USERNAME=root
DB_MYSQL_PASSWORD=your_password
DB_MYSQL_PREFIX=
DB_MYSQL_CHARSET=utf8mb4

# 第二个MySQL数据库
DB_MYSQL2_HOST=127.0.0.1
DB_MYSQL2_PORT=3306
DB_MYSQL2_DATABASE=thinkadmin2
DB_MYSQL2_USERNAME=root
DB_MYSQL2_PASSWORD=your_password
DB_MYSQL2_PREFIX=
DB_MYSQL2_CHARSET=utf8mb4
```

### 3. 创建数据库

确保两个数据库都已创建：

```sql
-- 创建第一个数据库
CREATE DATABASE thinkadmin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 创建第二个数据库
CREATE DATABASE thinkadmin2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 使用方法

### 1. 使用默认数据库连接

```php
use think\facade\Db;

// 使用默认数据库连接（mysql）
$users = Db::table('users')->select();
$user = Db::table('users')->where('id', 1)->find();
```

### 2. 使用第二个数据库连接

```php
use think\facade\Db;

// 使用第二个数据库连接（mysql2）
$users = Db::connect('mysql2')->table('users')->select();
$user = Db::connect('mysql2')->table('users')->where('id', 1)->find();
```

### 3. 在模型中使用多数据库

```php
namespace app\common\model;

use think\Model;

class User extends Model
{
    // 使用默认数据库连接
    protected $connection = 'mysql';
}

class User2 extends Model
{
    // 使用第二个数据库连接
    protected $connection = 'mysql2';
}
```

### 4. 在服务类中使用多数据库

参考 `app/common/service/MultiDatabaseService.php` 文件中的示例：

```php
use think\facade\Db;

class MultiDatabaseService
{
    // 从第一个数据库查询
    public static function getDataFromFirstDatabase()
    {
        return Db::table('users')->select();
    }
    
    // 从第二个数据库查询
    public static function getDataFromSecondDatabase()
    {
        return Db::connect('mysql2')->table('users')->select();
    }
}
```

## 测试多数据库连接

运行测试脚本：

```bash
php test_multi_database.php
```

## 注意事项

1. **数据库权限**：确保数据库用户有足够的权限访问两个数据库
2. **连接池**：ThinkPHP会自动管理数据库连接池
3. **事务处理**：跨数据库的事务需要特殊处理
4. **性能考虑**：多个数据库连接会增加资源消耗
5. **错误处理**：建议为每个数据库连接添加适当的错误处理

## 常见问题

### Q: 如何切换默认数据库连接？

A: 修改 `config/database.php` 中的 `'default'` 配置：

```php
'default' => 'mysql2', // 将默认连接改为mysql2
```

### Q: 如何在同一个事务中使用多个数据库？

A: 跨数据库事务需要特殊处理，建议使用分布式事务或消息队列。

### Q: 如何监控多个数据库的性能？

A: 可以启用SQL监听来监控每个数据库的查询性能：

```php
'trigger_sql' => true, // 在配置中启用
```

## 扩展配置

如果需要连接更多数据库，可以继续添加 `mysql3`、`mysql4` 等配置，方法相同。
