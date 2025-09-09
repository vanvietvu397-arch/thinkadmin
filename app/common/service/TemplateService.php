<?php

namespace app\common\service;

use PhpMcp\Server\Attributes\McpTool;
use think\facade\Log;
use app\common\model\DeviceModel;
use think\facade\Db;
use GatewayWorker\Lib\Gateway;

/**
 * 模板信息查询服务 
 * 当小智AI语音中提到"关于模板的信息"时，调用此服务查询模板信息
 */
class TemplateService
{
    //设备ID
    public static int $deviceId;
    //APP_id
    public static int $appId;

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

        
        // 通过分析调用栈获取当前调用的设备信息
        $deviceInfo = self::getDeviceInfoFromBacktrace();
        
        if ($deviceInfo['success']) {
            self::$deviceId = $deviceInfo['deviceId'];
        }

        //根据设备ID 查询APP_id
        $appId = DeviceModel::where('id', self::$deviceId)->value('app_id');
        self::$appId = $appId;
        $keywords = self::extractKeywords($keyword);
        $matchedTemplates = self::matchTemplate($keywords, $appId);
        if (empty($matchedTemplates)) {
            return [
                'success' => false,
                'message' => '抱歉，没有找到相关内容，请尝试其他关键词。',
                'keyword' => $keyword,
                'extracted_keywords' => $keywords
            ];
        }

        $allDisplayContent = [];
        foreach ($matchedTemplates as $matchedTemplate) {

            // 变量替换
            $responseContent = self::replaceVariables($matchedTemplate['rich_text_content'], $appId);

            // 记录对话
            self::recordConversation($appId, $keyword, $matchedTemplate['template_id'], $responseContent, $keywords);

            // 生成展示屏内容
            $displayContent = self::generateDisplayContent($matchedTemplate, $responseContent);
            $allDisplayContent[] = $displayContent;

            //根据 设备ID 把对话内容推送到对应的智慧展示屏 websocket
            self::pushToDisplayScreen(self::$deviceId, $displayContent);

        }
        $result = [
            'success' => true,
            'message' => '查询成功',
            'data' => $allDisplayContent,
            'total' => count($allDisplayContent),
            'keyword' => $keyword
        ];
        Log::info('queryTemplateInfo：'.json_encode($result));
        return $result;
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
        $appId = self::$appId;
        $template = Db::connect('mysql2')->name('shop_voice_template')
            ->where('template_id', $templateId)
            ->where('app_id', $appId)
            ->where('status', 1)
            ->find();

        $responseContent = self::replaceVariables($template['rich_text_content'], $appId);
        $template = self::generateDisplayContent($template, $responseContent);
        if (!$template) {
            return [
                'success' => false,
                'message' => '模板不存在'
            ];
        }

        return [
            'success' => true,
            'message' => '获取模板详情成功',
            'data' => $template
        ];
    }


    /**
     * 通过分析调用栈获取当前调用的设备信息
     * 
     * @return array 设备信息数组，包含设备ID、设备名称、WSS URL等信息
     */
    private static function getDeviceInfoFromBacktrace(): array
    {
        $deviceInfo = [
            'deviceId' => null,
            'deviceName' => null,
            'deviceInfo' => null,
            'context' => null,
            'success' => false
        ];
        
        try {
            // 获取调用栈，包含对象信息
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 15);
            
            // 查找调用栈中的McpWssTransport实例
            foreach ($backtrace as $trace) {
                if (isset($trace['object']) && $trace['object'] instanceof \app\common\service\McpWssTransportService) {
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
                    
                    if ($callingDeviceId && $callingDeviceInfo) {
                        $deviceInfo['deviceId'] = $callingDeviceId;
                        $deviceInfo['deviceName'] = $callingDeviceInfo['name'] ?? 'unknown';
                        $deviceInfo['deviceInfo'] = $callingDeviceInfo;
                        
                        // 构建设备上下文信息
                        $deviceInfo['context'] = [
                            'callingDeviceId' => $callingDeviceId,
                            'callingDeviceName' => $callingDeviceInfo['name'] ?? 'unknown',
                            'callingDeviceUrl' => $callingDeviceInfo['wss_url'] ?? 'unknown',
                            'sessionId' => 'wss-session-' . $callingDeviceId . '-' . substr(md5($callingDeviceInfo['wss_url'] ?? ''), 0, 8)
                        ];
                        
                        $deviceInfo['success'] = true;
                        
                        Log::info('当前调用设备信息：'.json_encode([
                            'deviceId' => $callingDeviceId,
                            'deviceName' => $callingDeviceInfo['name'] ?? 'unknown',
                            'wssUrl' => $callingDeviceInfo['wss_url'] ?? 'unknown',
                            'enabled' => $callingDeviceInfo['enabled'] ?? false,
                            'method' => 'backtrace_detection'
                        ]));
                        
                        Log::info('设备上下文信息：'.json_encode($deviceInfo['context']));
                    }
                    break;
                }
            }
            
            if (!$deviceInfo['success']) {
                Log::warning('未找到任何设备信息');
            }
            
        } catch (\Exception $e) {
            Log::error('获取设备信息失败：' . $e->getMessage());
        }
        
        return $deviceInfo;
    }

    /**
     * 从文本中提取关键词
     * 
     * @param string $text 输入文本
     * @return array 提取的关键词数组
     */
    private static function extractKeywords(string $text): array
    {
        $keywords = [];
        $cleanText = preg_replace('/[^\p{Han}a-zA-Z0-9]/u', ' ', $text);
        $words = preg_split('/\s+/', trim($cleanText));

        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') >= 2) {
                $keywords[] = $word;
            }
        }

        return array_unique($keywords);
    }

    /**
     * 根据关键词匹配模板
     * 
     * @param array $keywords 关键词数组
     * @param int $appId 应用ID
     * @return array|null 匹配的模板数组，如果没有匹配则返回null
     */
    private static function matchTemplate(array $keywords, int $appId): ?array
    {
        if (empty($keywords)) {
            return null;
        }

        $keywordConditions = [];
        foreach ($keywords as $keyword) {
            $keywordConditions[] = "k.keyword_text LIKE '%{$keyword}%'";
        }

        $keywordWhere = implode(' OR ', $keywordConditions);

        $sql = "
            SELECT DISTINCT t.*, 
                    COUNT(DISTINCT tk.keyword_id) as match_count,
                    AVG(k.weight) as avg_weight
            FROM jjjshop_shop_voice_template t
            LEFT JOIN jjjshop_shop_voice_template_keyword_rel tk ON t.template_id = tk.template_id
            LEFT JOIN jjjshop_shop_voice_keyword k ON tk.keyword_id = k.keyword_id
            WHERE t.app_id = {$appId} 
            AND t.status = 1
            AND ({$keywordWhere})
            GROUP BY t.template_id
            ORDER BY match_count DESC, avg_weight DESC
            
        ";

        try {
            // 使用mysql2连接执行查询，确保与DeviceModel使用相同的数据库
            $result = Db::connect('mysql2')->query($sql);
            return $result ?: null;
        } catch (\Exception $e) {
            Log::error('matchTemplate查询失败：' . $e->getMessage());
            return null;
        }
    }

    /**
     * 替换模板内容中的变量
     * 
     * @param string $content 包含变量的模板内容
     * @param int $appId 应用ID
     * @return string 替换变量后的内容
     */
    private static function replaceVariables(string $content, int $appId): string
    {
        preg_match_all('/\[([^]]+)]/', $content, $matches);

        if (empty($matches[1])) {
            return $content;
        }

        $variables = $matches[1];
        $replacements = [];

        foreach ($variables as $varName) {
            $varValue = Db::connect('mysql2')->name('shop_voice_variable')
                ->where('app_id', $appId)
                ->where('variable_identifier', $varName)
                ->where('status', 1)
                ->value('variable_value');
            $replacements['[' . $varName . ']'] = $varValue ?: '未知';
        }

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * 记录对话信息到数据库
     * 
     * @param int $appId 应用ID
     * @param string $input 用户输入内容
     * @param int $templateId 模板ID
     * @param string $response 响应内容
     * @param array $keywords 关键词数组
     * @return void
     */
    private static function recordConversation(int $appId, string $input, int $templateId, string $response, array $keywords): void
    {
        try {
            Db::connect('mysql2')->name('shop_voice_conversation')->insert([
                'app_id' => $appId,
                'device_id' => self::$deviceId,
                'input_text' => $input,
                'input_type' => 'query',
                'template_id' => $templateId,
                'response_content' => json_encode(['content' => $response]),
                'keywords' => json_encode($keywords),
                'create_time' => time()
            ]);
        } catch (\Exception $e) {
            // 记录失败不影响主要功能
        }
    }

    /**
     * 生成展示屏显示内容
     * 
     * @param array $template 模板数据
     * @param string $response 响应内容
     * @return array 格式化后的展示内容
     */
    private static function generateDisplayContent(array $template, string $response): array
    {
        return [
            'id' => $template['template_id'],
            'title' => '您是否要搜索以下内容？',
            'subtitle' => '已为您找到以下内容：',
            'template_name' => $template['template_name'],
            'content' => $response,
            'display_time' => date('Y-m-d H:i:s'),
            'action' => 'confirm'
        ];
    }

    /**
     * 推送内容到智慧展示屏
     * 使用 ThinkPHP Workerman 扩展库进行 WebSocket 推送
     *
     * @param string $deviceId 设备ID
     * @param array $content 推送内容
     * @return bool 推送是否成功
     */
    private static function pushToDisplayScreen(string $deviceId, array $content): bool
    {
        try {
            // 构造推送数据
            $pushData = [
                'type' => 'template_display',
                'device_id' => $deviceId,
                'timestamp' => time(),
                'data' => $content
            ];

            // 使用设备ID作为UID标识，推送到对应的智慧展示屏
            $uid = 'device_' . $deviceId;
            
            // 检查设备是否在线
            if (Gateway::isUidOnline($uid)) {
                Gateway::sendToUid($uid, json_encode($pushData));
                Log::info("推送到展示屏成功 - 设备ID: {$deviceId}", $pushData);
                return true;
            } else {
                Log::warning("设备离线，推送失败 - 设备ID: {$deviceId}");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("推送到展示屏失败 - 设备ID: {$deviceId}, 错误: " . $e->getMessage());
            return false;
        }
    }
}
