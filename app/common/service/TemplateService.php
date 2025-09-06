<?php

namespace app\common\service;

use PhpMcp\Server\Attributes\McpTool;
use think\facade\Log;
use Psr\Log\LoggerInterface;
use app\admin\controller\DeviceManager;

/**
 * 模板信息查询服务 
 * 当小智AI语音中提到"关于模板的信息"时，调用此服务查询模板信息
 */
class TemplateService
{


    /**
     * 查询模板信息
     *
     * @param string $keyword 搜索关键词（可选）
     * @param array $params 设备参数（必选）
     * @return array 模板信息列表
     */
    #[McpTool(
        name: "query_template_info",
        description: "查询模板相关信息，支持关键词搜索"
    )]
    public static function queryTemplateInfo(string $keyword = '',array $params = []): array
    {
        Log::info('queryTemplateInfo：'.'keyword='.$keyword);

        
        try {
            //通过分析调用栈获取当前调用的设备信息
            $callingDeviceId = null;
            $callingDeviceInfo = null;
            
            // 获取调用栈，包含对象信息
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 15);
            
            // 查找调用栈中的McpWssTransport实例
            foreach ($backtrace as $trace) {
                if (isset($trace['object']) && $trace['object'] instanceof \app\admin\controller\McpWssTransport) {
                    $transport = $trace['object'];
                    $callingDeviceId = $transport->getDeviceId();
                    $callingDeviceInfo = $GLOBALS['mcp_device_config_' . $callingDeviceId] ?? null;
                    
                    Log::info('通过调用栈找到设备：'.json_encode([
                        'deviceId' => $callingDeviceId,
                        'deviceName' => $transport->getDeviceName(),
                        'traceFile' => basename($trace['file'] ?? 'unknown'),
                        'traceLine' => $trace['line'] ?? 0,
                        'traceFunction' => $trace['function'] ?? 'unknown'
                    ]));
                    break;
                }
            }

            if ($callingDeviceId && $callingDeviceInfo) {
                $data = [
                    'deviceId' => $callingDeviceId,
                    'deviceName' => $callingDeviceInfo['name'] ?? 'unknown',
                    'wssUrl' => $callingDeviceInfo['wss_url'] ?? 'unknown',
                    'enabled' => $callingDeviceInfo['enabled'] ?? false,
                    'method' => $callingDeviceId ? 'backtrace_detection' : 'active_transport_detection'
                ];
                Log::info('当前调用设备信息：'.json_encode($data));
                
                // 构建设备上下文信息
                $deviceContext = [
                    'callingDeviceId' => $callingDeviceId,
                    'callingDeviceName' => $callingDeviceInfo['name'] ?? 'unknown',
                    'callingDeviceUrl' => $callingDeviceInfo['wss_url'] ?? 'unknown',
                    'sessionId' => 'wss-session-' . $callingDeviceId . '-' . substr(md5($callingDeviceInfo['wss_url'] ?? ''), 0, 8)
                ];
                
                Log::info('设备上下文信息：'.json_encode($deviceContext));
                
            } else {
                Log::warning('未找到任何设备信息');
            }
            
        } catch (\Exception $e) {
            Log::error('获取设备信息失败：' . $e->getMessage());
        }



        // 这里可以连接数据库查询模板信息
        // 示例数据，实际使用时替换为数据库查询
        $templates = [
            [
                'id' => 1,
                'name' => '用户注册模板',
                'description' => '新用户注册时使用的邮件模板',
                'category' => '邮件模板',
                'status' => '启用',
                'create_time' => '2024-01-15 10:30:00'
            ],
            [
                'id' => 2,
                'name' => '订单确认模板',
                'description' => '订单确认后发送给用户的短信模板',
                'category' => '短信模板',
                'status' => '启用',
                'create_time' => '2024-01-16 14:20:00'
            ],
            [
                'id' => 3,
                'name' => '密码重置模板',
                'description' => '用户忘记密码时重置密码的邮件模板',
                'category' => '邮件模板',
                'status' => '启用',
                'create_time' => '2024-01-17 09:15:00'
            ],
            [
                'id' => 4,
                'name' => '社区党员信息',
                'description' => '社区党员信息的展示模板',
                'category' => '展示模板',
                'status' => '启用',
                'create_time' => '2024-01-17 09:15:00'
            ]
        ];

        // 如果有关键词，进行过滤
        if (!empty($keyword)) {
            $templates = array_filter($templates, function($template) use ($keyword) {
                return stripos($template['name'], $keyword) !== false ||
                    stripos($template['description'], $keyword) !== false ||
                    stripos($template['category'], $keyword) !== false;
            });
        }

        return [
            'success' => true,
            'message' => '查询成功',
            'data' => array_values($templates),
            'total' => count($templates),
            'keyword' => $keyword
        ];
    }

    /**
     * 获取模板分类统计
     *
     * @return array 分类统计信息
     */
    #[McpTool(
        name: "get_template_categories",
        description: "获取模板分类统计信息"
    )]
    public static function getTemplateCategories(): array
    {
        $categories = [
            '邮件模板' => 2,
            '短信模板' => 1,
            '展示模板' => 1,
        ];

        return [
            'success' => true,
            'message' => '获取分类统计成功',
            'data' => $categories
        ];
    }

    /**
     * 获取模板详情
     *
     * @param int $templateId 模板ID
     * @return array 模板详细信息
     */
    #[McpTool(
        name: "get_template_detail",
        description: "根据模板ID获取模板详细信息"
    )]
    public static function getTemplateDetail(int $templateId): array
    {
        // 模拟数据库查询
        $template = [
            'id' => $templateId,
            'name' => '用户注册模板',
            'description' => '新用户注册时使用的邮件模板',
            'category' => '邮件模板',
            'content' => '尊敬的{username}，欢迎注册我们的平台！',
            'variables' => ['username', 'email', 'register_time'],
            'status' => '启用',
            'create_time' => '2024-01-15 10:30:00',
            'update_time' => '2024-01-15 10:30:00'
        ];

        return [
            'success' => true,
            'message' => '获取模板详情成功',
            'data' => $template
        ];
    }
}
