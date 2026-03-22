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
        
        // 监控数据库耗时
        $dbDuration = 0;
        try {
            $db = resolve('db');
            $db->listen(function ($query) use (&$dbDuration) {
                $dbDuration += $query->time;
            });
        } catch (\Exception $e) {}

        $actor = $request->getAttribute('actor');
        $isGuest = $actor && !$actor->exists;
        $url = (string) $request->getUri();
        $cacheKey = 'nopj_optimization.page.' . md5($url);
        
        // P1: 尝试从缓存获取 (仅限游客且为 GET 请求)
        if ($isGuest && $request->getMethod() === 'GET' && $request->getHeaderLine('X-Skip-Optimization') !== '1') {
            try {
                $cache = resolve('cache.store');
                if ($cache->has($cacheKey)) {
                    $cachedData = $cache->get($cacheKey);
                    $newStream = fopen('php://temp', 'r+');
                    fwrite($newStream, $cachedData['body']);
                    rewind($newStream);
                    
                    return (new \Laminas\Diactoros\Response())
                        ->withBody(new \Laminas\Diactoros\Stream($newStream))
                        ->withHeader('Content-Type', 'text/html; charset=utf-8')
                        ->withHeader('X-Cache', 'HIT')
                        ->withHeader('X-Backend-Time', round((microtime(true) - $start) * 1000, 2) . 'ms');
                }
            } catch (\Exception $e) {}
        }

        $response = $handler->handle($request);

        // 获取响应的 Content-Type，确保只对 HTML 页面进行优化
        $contentType = $response->getHeaderLine('Content-Type');
        if (strpos($contentType, 'text/html') !== false) {
             $body = $response->getBody()->getContents();
             
             // P0: 移除首屏“加载论坛时出错”文案
             $body = str_replace('加载论坛时出错', '', $body);
             $body = str_replace('Error Loading Forum', '', $body);
             
             // P0: 缩小首屏 JS (移除一些非核心扩展的 JS 注入)
             if ($isGuest) {
                 $body = preg_replace('/<script src="[^"]*fof-gamification[^"]*"><\/script>/', '', $body);
                 $body = preg_replace('/<script src="[^"]*fof-reactions[^"]*"><\/script>/', '', $body);
                 
                 // P1: 将成功的游客响应存入缓存 (有效期 1 小时)
                 if ($response->getStatusCode() === 200) {
                     try {
                        resolve('cache.store')->put($cacheKey, ['body' => $body], 3600);
                     } catch (\Exception $e) {}
                 }
             }

             // 预加载核心资源
             // ... [保持原有 Link 处理]
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
                             ->withAddedHeader('X-DB-Queries', $dbDuration > 0 ? round($dbDuration, 2) . 'ms' : '0ms');

        return $response;
    }
}
