<?php

/*
 * This file is part of nopj/optimization.
 *
 * Copyright (c) 2026 Nopj.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Nopj\Optimization;

use Flarum\Extend;
use Flarum\Frontend\Document;

return [
    (new Extend\Frontend('forum'))
        ->content(function (Document $document) {
            // 预解析字体服务域名
            $document->head[] = '<link rel="preconnect" href="https://fonts.googleapis.com">';
            $document->head[] = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        }),

    (new Extend\Locales(__DIR__.'/resources/locale')),

    (new Extend\Middleware('forum'))
        ->add(Middleware\OptimizeResponse::class),

    (new Extend\Routes('api'))
        ->get('/nopj-optimization/benchmark', 'nopj-optimization.benchmark', Api\Controller\BenchmarkController::class)
        ->get('/nopj-optimization/performance-report', 'nopj-optimization.report', Api\Controller\BenchmarkReportController::class),
];
