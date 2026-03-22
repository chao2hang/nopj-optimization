# Nopj Optimization for Flarum

这是一个专门为 Flarum 设计的性能优化扩展插件，旨在通过资源预加载、响应头优化及可观测性工具，提升论坛的首屏加载速度。

## 🚀 主要功能

- **资源预加载 (Resource Preloading)**: 自动为 `forum.js` 和 `forum.css` 添加 `Link: rel=preload` 响应头，引导浏览器在解析 HTML 早期就开始并行下载核心资源。
- **预解析 (Preconnect)**: 为常用外部服务（如 Google Fonts）添加 `preconnect` 指令，提前完成 DNS 解析、TCP 握手及 SSL 协商。
- **HTTP 响应头精简**: 自动移除 `X-Powered-By` 等冗余头信息，减少响应字节，提升安全性。
- **性能监控 (X-Backend-Time)**: 在每一个响应中注入 `X-Backend-Time` 头，实时展示 Flarum 后端处理的耗时（从启动到响应结束）。
- **性能报告可视化**: 提供内置的 A/B 测试报告页面，直观对比开启优化前后的性能指标。

## 🛠 安装与启用

你可以直接通过 Composer 安装：

```bash
composer require nopj/optimization
```

如果是本地开发调试，也可以在主项目的 `composer.json` 中配置路径映射后引入。

安装完成后，登录 Flarum 管理后台，在扩展列表中找到 **Nopj Optimization** 并启用。

## 📊 性能看板

启用插件后，管理员可以直接访问以下路径查看实时性能对比报告：
`你的论坛域名/api/nopj-optimization/performance-report`

> **注意**: 该页面会进行内部环回请求，请确保服务器环境支持访问自身域名。

## 💡 性能核心提示：OpCache

本插件主要解决的是**前端加载效率**。如果你的 Flarum 首字节时间 (TTFB) 仍然很高（>500ms），请务必检查 PHP 的 **OpCache** 是否开启。

建议的 `php.ini` 配置：
```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```
修改后请重启 Web 服务器以生效。

## 📜 许可证

MIT
