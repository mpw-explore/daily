<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_log', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->string('user_name', 64)->comment('用户名称');
            $table->string('project_name', 128)->comment('项目名称');
            $table->date('days')->comment('日志所属日期');
            $table->decimal('hours', 5, 2)->comment('工作时长（小时）');
            $table->string('content', 1000)->comment('工作内容描述');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->timestamp('create_time')->useCurrent()->comment('创建时间');
            $table->timestamp('update_time')->useCurrent()->useCurrentOnUpdate()->comment('更新时间');
            
            $table->index(['user_name', 'days'], 'idx_user_date');
            $table->index(['project_name', 'days'], 'idx_project_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_log');
    }
};

