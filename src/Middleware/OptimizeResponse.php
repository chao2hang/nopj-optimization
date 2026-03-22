<?php

namespace Nopj\Optimization\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OptimizeResponse implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = defined('FLARUM_START') ? FLARUM_START : microtime(true);
        $dbStart = microtime(true);
        
        // 尝试获取数据库连接以监控耗时 (Flarum 1.x 容器注入)
        $dbDuration = 0;
        try {
            $db = resolve('db');
            $db->listen(function ($query) use (&$dbDuration) {
                $dbDuration += $query->time;
            });
        } catch (\Exception $e) {}

        // 允许通过 Header 绕过优化，用于对比测试
        if ($request->getHeaderLine('X-Skip-Optimization') === '1') {
             $response = $handler->handle($request);
             return $response->withHeader('X-Backend-Time', round((microtime(true) - $start) * 1000, 2) . 'ms');
        }

        $response = $handler->handle($request);

        // 获取响应的 Content-Type，确保只对 HTML 页面进行优化
        $contentType = $response->getHeaderLine('Content-Type');
        if (strpos($contentType, 'text/html') !== false) {
             $body = $response->getBody()->getContents();
             
             // P0: 移除首屏“加载论坛时出错”文案（防止语言包未覆盖的情况）
             $body = str_replace('加载论坛时出错', '', $body);
             $body = str_replace('Error Loading Forum', '', $body);
             
             // P0: 缩小首屏 JS (移除一些非核心扩展的 JS 注入)
             // 对于游客，我们可以移除更多不必要的组件，如：gamification, mentions 等
             if (!$request->getAttribute('actor')->exists) {
                 $body = preg_replace('/<script src="[^"]*fof-gamification[^"]*"><\/script>/', '', $body);
                 $body = preg_replace('/<script src="[^"]*fof-reactions[^"]*"><\/script>/', '', $body);
             }

             // 预加载核心资源
             $response = $response->withAddedHeader('Link', '</assets/forum.css>; rel=preload; as=style');
             $response = $response->withAddedHeader('Link', '</assets/forum.js>; rel=preload; as=script');
             $response = $response->withAddedHeader('Link', '</assets/forum-zh-Hans.js>; rel=preload; as=script');

             // 更新 Body
             $newStream = fopen('php://temp', 'r+');
             fwrite($newStream, $body);
             rewind($newStream);
             $response = $response->withBody(new \Laminas\Diactoros\Stream($newStream));
        }

        // 性能优化：移除冗余响应头
        $response = $response->withoutHeader('X-Powered-By')
                             ->withAddedHeader('X-Backend-Time', round((microtime(true) - $start) * 1000, 2) . 'ms')
                             ->withAddedHeader('X-DB-Queries', $dbDuration > 0 ? 'Logged' : 'No queries tracked');

        return $response;
    }
}
