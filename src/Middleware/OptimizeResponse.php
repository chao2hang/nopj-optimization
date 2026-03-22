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

        // 允许通过 Header 绕过优化，用于对比测试
        if ($request->getHeaderLine('X-Skip-Optimization') === '1') {
             $response = $handler->handle($request);
             return $response->withHeader('X-Backend-Time', round((microtime(true) - $start) * 1000, 2) . 'ms');
        }

        $response = $handler->handle($request);

        // 获取响应的 Content-Type，确保只对 HTML 页面进行优化
        $contentType = $response->getHeaderLine('Content-Type');
        if (strpos($contentType, 'text/html') !== false) {
             // 预加载核心资源
             $response = $response->withAddedHeader('Link', '</assets/forum.css>; rel=preload; as=style');
             $response = $response->withAddedHeader('Link', '</assets/forum.js>; rel=preload; as=script');
             $response = $response->withAddedHeader('Link', '</assets/forum-zh-Hans.js>; rel=preload; as=script');
        }

        // 性能优化：移除冗余响应头
        $response = $response->withoutHeader('X-Powered-By')
                             ->withAddedHeader('X-Backend-Time', round((microtime(true) - $start) * 1000, 2) . 'ms');

        return $response;
    }
}
