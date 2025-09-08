<?php

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 多数据库连接服务示例
 * 演示如何在ThinkAdmin中使用多个MySQL数据库连接
 */
class MultiDatabaseService
{
    /**
     * 从第一个数据库（默认）查询数据
     */
    public static function getDataFromFirstDatabase()
    {
        try {
            // 使用默认数据库连接（mysql）
            $result = Db::table('users')->limit(10)->select();
            
            Log::info('从第一个数据库查询成功', ['count' => count($result)]);
            
            return [
                'success' => true,
                'message' => '从第一个数据库查询成功',
                'data' => $result,
                'database' => 'mysql'
            ];
        } catch (\Exception $e) {
            Log::error('第一个数据库查询失败：' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '第一个数据库查询失败：' . $e->getMessage(),
                'data' => [],
                'database' => 'mysql'
            ];
        }
    }

    /**
     * 从第二个数据库查询数据
     */
    public static function getDataFromSecondDatabase()
    {
        try {
            // 使用第二个数据库连接（mysql2）
            $result = Db::connect('mysql2')->table('users')->limit(10)->select();
            
            Log::info('从第二个数据库查询成功', ['count' => count($result)]);
            
            return [
                'success' => true,
                'message' => '从第二个数据库查询成功',
                'data' => $result,
                'database' => 'mysql2'
            ];
        } catch (\Exception $e) {
            Log::error('第二个数据库查询失败：' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '第二个数据库查询失败：' . $e->getMessage(),
                'data' => [],
                'database' => 'mysql2'
            ];
        }
    }

    /**
     * 同时从两个数据库查询数据
     */
    public static function getDataFromBothDatabases()
    {
        $results = [];
        
        // 查询第一个数据库
        $results['first_db'] = self::getDataFromFirstDatabase();
        
        // 查询第二个数据库
        $results['second_db'] = self::getDataFromSecondDatabase();
        
        return [
            'success' => true,
            'message' => '多数据库查询完成',
            'data' => $results
        ];
    }

    /**
     * 在第二个数据库中插入数据
     */
    public static function insertToSecondDatabase($data)
    {
        try {
            // 使用第二个数据库连接插入数据
            $result = Db::connect('mysql2')->table('users')->insert($data);
            
            Log::info('向第二个数据库插入数据成功', ['data' => $data]);
            
            return [
                'success' => true,
                'message' => '向第二个数据库插入数据成功',
                'data' => $result,
                'database' => 'mysql2'
            ];
        } catch (\Exception $e) {
            Log::error('向第二个数据库插入数据失败：' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '向第二个数据库插入数据失败：' . $e->getMessage(),
                'data' => null,
                'database' => 'mysql2'
            ];
        }
    }

    /**
     * 测试数据库连接
     */
    public static function testDatabaseConnections()
    {
        $results = [];
        
        // 测试第一个数据库连接
        try {
            Db::table('users')->limit(1)->select();
            $results['mysql'] = [
                'status' => 'success',
                'message' => '第一个数据库连接正常'
            ];
        } catch (\Exception $e) {
            $results['mysql'] = [
                'status' => 'error',
                'message' => '第一个数据库连接失败：' . $e->getMessage()
            ];
        }
        
        // 测试第二个数据库连接
        try {
            Db::connect('mysql2')->table('users')->limit(1)->select();
            $results['mysql2'] = [
                'status' => 'success',
                'message' => '第二个数据库连接正常'
            ];
        } catch (\Exception $e) {
            $results['mysql2'] = [
                'status' => 'error',
                'message' => '第二个数据库连接失败：' . $e->getMessage()
            ];
        }
        
        return [
            'success' => true,
            'message' => '数据库连接测试完成',
            'data' => $results
        ];
    }
}
