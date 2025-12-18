<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>周报生成器</title>
    <?php if(config('app.debug')): ?>
    <!-- 开发环境：使用 Vite 开发服务器 -->
    <script type="module" src="http://localhost:5173/@vite/client"></script>
    <script type="module" src="http://localhost:5173/resources/js/app.js"></script>
    <?php else: ?>
    <!-- 生产环境：使用构建后的文件 -->
    <script type="module" src="<?php echo e(asset('build/assets/app.js')); ?>"></script>
    <link rel="stylesheet" href="<?php echo e(asset('build/assets/app.css')); ?>">
    <?php endif; ?>
</head>
<body>
    <div id="app"></div>
</body>
</html>

<?php /**PATH /Users/pengweimao/Documents/project/daily/resources/views/app.blade.php ENDPATH**/ ?>