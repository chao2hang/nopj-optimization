<?php

namespace Nopj\Optimization\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BenchmarkController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 确保只有管理员可以访问
        RequestUtil::getActor($request)->assertAdmin();

        // 获取当前论坛的根路径
        $baseUrl = (string) $request->getUri()->withPath('/')->withQuery('')->withFragment('');

        // 预热请求（让 Flarum 完成 Less 编译和缓存初始化）
        $this->measure($baseUrl, false);

        // 进行对比测试，每个跑 3 次取平均值，排除偶发干扰
        $results = [
            'optimized' => $this->measureAverage($baseUrl, false),
            'original' => $this->measureAverage($baseUrl, true),
        ];

        return new JsonResponse($results);
    }

    protected function measureAverage(string $url, bool $skipOptimization): array
    {
        $iterations = 3;
        $totalTime = 0;
        $lastResult = [];

        for ($i = 0; $i < $iterations; $i++) {
            $lastResult = $this->measure($url, $skipOptimization);
            $totalTime += $lastResult['time_ms'];
            // 稍作停顿
            usleep(100000); 
        }

        $lastResult['time_ms'] = round($totalTime / $iterations, 2);
        
        return $lastResult;
    }

    protected function measure(string $url, bool $skipOptimization): array
    {
        $start = microtime(true);
        
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => $skipOptimization ? "X-Skip-Optimization: 1\r\n" : "X-Skip-Optimization: 0\r\n",
                'ignore_errors' => true,
                'timeout' => 10,
            ]
        ];
        
        $context = stream_context_create($opts);
        $content = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        
        $end = microtime(true);

        return [
            'time_ms' => round(($end - $start) * 1000, 2),
            'header_count' => count($headers),
            'has_preload' => $this->checkPreload($headers),
            'status_code' => $this->extractStatusCode($headers),
        ];
    }

    protected function checkPreload(array $headers): bool
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Link:') !== false && stripos($header, 'rel=preload') !== false) {
                return true;
            }
        }
        return false;
    }

    protected function extractStatusCode(array $headers): int
    {
        if (empty($headers)) return 0;
        preg_match('{HTTP\/\S*\s(\d{3})}', $headers[0], $match);
        return isset($match[1]) ? (int)$match[1] : 0;
    }
}
