#!/bin/bash

# 启动 Laravel 开发服务器
php artisan serve &

# 等待一下让 Laravel 服务器启动
sleep 2

# 启动 Vite 开发服务器
npm run dev

