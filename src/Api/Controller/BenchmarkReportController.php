<?php

namespace Nopj\Optimization\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BenchmarkReportController extends BenchmarkController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 确保管理员权限
        RequestUtil::getActor($request)->assertAdmin();

        $baseUrl = (string) $request->getUri()->withPath('/')->withQuery('')->withFragment('');

        $optimized = $this->measure($baseUrl, false);
        $original = $this->measure($baseUrl, true);

        $html = $this->renderHtml($optimized, $original);

        return new HtmlResponse($html);
    }

    protected function renderHtml(array $opt, array $orig): string
    {
        $improvement = $orig['time_ms'] > 0 ? round((($orig['time_ms'] - $opt['time_ms']) / $orig['time_ms']) * 100, 1) : 0;
        $color = $improvement > 0 ? '#2ecc71' : '#e74c3c';

        return "
        <!DOCTYPE html>
        <html lang='zh-CN'>
        <head>
            <meta charset='UTF-8'>
            <title>Flarum 性能优化对比报告</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f4f7f6; color: #333; padding: 40px; }
                .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
                h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
                .card { padding: 20px; border-radius: 8px; border: 1px solid #eee; text-align: center; }
                .card.optimized { background: #f0fff4; border-color: #c6f6d5; }
                .card.original { background: #fff5f5; border-color: #fed7d7; }
                .card h2 { margin: 0; font-size: 14px; text-transform: uppercase; color: #666; letter-spacing: 1px; }
                .card .value { font-size: 48px; font-weight: bold; margin: 15px 0; }
                .card .unit { font-size: 16px; color: #999; }
                .summary { text-align: center; padding: 20px; border-radius: 8px; background: #ebf8ff; border: 1px solid #bee3f8; margin-bottom: 30px; }
                .summary strong { color: $color; font-size: 24px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
                th { background: #fafafa; color: #666; font-weight: 600; }
                .status-on { color: #2ecc71; font-weight: bold; }
                .status-off { color: #e74c3c; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>🚀 Performance Benchmark Report</h1>
                
                <div class='summary'>
                    响应时间提升了约 <strong>$improvement%</strong>
                </div>

                <div class='grid'>
                    <div class='card optimized'>
                        <h2>开启优化 (TTFB)</h2>
                        <div class='value'>{$opt['time_ms']} <span class='unit'>ms</span></div>
                    </div>
                    <div class='card original'>
                        <h2>未开启优化 (TTFB)</h2>
                        <div class='value'>{$orig['time_ms']} <span class='unit'>ms</span></div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>指标</th>
                            <th>开启优化</th>
                            <th>未开启优化</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>响应头数量</td>
                            <td>{$opt['header_count']}</td>
                            <td>{$orig['header_count']}</td>
                        </tr>
                        <tr>
                            <td>资源预加载 (Preload)</td>
                            <td class='" . ($opt['has_preload'] ? 'status-on' : 'status-off') . "'>" . ($opt['has_preload'] ? '已激活' : '未激活') . "</td>
                            <td class='" . ($orig['has_preload'] ? 'status-on' : 'status-off') . "'>" . ($orig['has_preload'] ? '已激活' : '未激活') . "</td>
                        </tr>
                        <tr>
                            <td>冗余头信息移除 (X-Powered-By)</td>
                            <td class='status-on'>已移除</td>
                            <td class='status-off'>已保留</td>
                        </tr>
                        <tr>
                            <td>HTTP 状态码</td>
                            <td>{$opt['status_code']}</td>
                            <td>{$orig['status_code']}</td>
                        </tr>
                    </tbody>
                </table>

                <p style='margin-top: 30px; font-size: 12px; color: #999; text-align: center;'>建议在启用插件后，手动访问该页面进行多次测试以获得准确结果。</p>
            </div>
        </body>
        </html>
        ";
    }
}
