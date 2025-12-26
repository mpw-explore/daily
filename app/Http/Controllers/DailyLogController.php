<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DailyLogController extends Controller
{
    public function index(Request $request)
    {
        $userName = $request->input('user_name', '');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $query = DailyLog::where('user_name', $userName);
        
        // 如果提供了日期范围，则按日期范围查询
        if ($startDate && $endDate) {
            $query->whereBetween('days', [$startDate, $endDate]);
        }
        
        $logs = $query->orderBy('days', 'asc')->get();
        return response()->json($logs);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_name' => 'required|string|max:64',
            'project_name' => 'required|string|max:128',
            'days' => 'required|date',
            'hours' => 'required|numeric|min:0|max:24',
            'content' => 'required|string|max:1000',
            'remark' => 'nullable|string|max:500',
        ]);
        
        // 校验：该用户该日期的工作时长总和不能超过24小时
        $totalHours = DailyLog::where('user_name', $validated['user_name'])
            ->where('days', $validated['days'])
            ->sum('hours');
        
        if ($totalHours + $validated['hours'] > 24) {
            // return response()->json([
            //     'message' => '该日期的工作时长总和不能超过24小时，当前已有 ' . number_format($totalHours, 2) . ' 小时'
            // ], 422);
        }
        
        $log = DailyLog::create($validated);
        
        return response()->json($log, 201);
    }
    
    public function update(Request $request, $id)
    {
        $log = DailyLog::findOrFail($id);
        
        $validated = $request->validate([
            'user_name' => 'required|string|max:64',
            'project_name' => 'required|string|max:128',
            'days' => 'required|date',
            'hours' => 'required|numeric|min:0|max:24',
            'content' => 'required|string|max:1000',
            'remark' => 'nullable|string|max:500',
        ]);
        
        // 校验：该用户该日期的工作时长总和不能超过24小时
        // 需要排除当前记录本身的工作时长
        $totalHours = DailyLog::where('user_name', $validated['user_name'])
            ->where('days', $validated['days'])
            ->where('id', '!=', $id)
            ->sum('hours');
        
        if ($totalHours + $validated['hours'] > 24) {
            // return response()->json([
            //     'message' => '该日期的工作时长总和不能超过24小时，当前已有 ' . number_format($totalHours, 2) . ' 小时'
            // ], 422);
        }
        
        $log->update($validated);
        
        return response()->json($log);
    }
    
    public function destroy($id)
    {
        $log = DailyLog::findOrFail($id);
        $log->delete();
        
        return response()->json(['message' => '删除成功']);
    }
    
    public function batchStore(Request $request)
    {
        $userName = $request->input('user_name');
        $logs = $request->input('logs', []);
        
        // 按日期分组，计算每天的总时长
        $dailyHours = [];
        foreach ($logs as $logData) {
            $days = $logData['days'];
            $hours = floatval($logData['hours'] ?? 8);
            
            if (!isset($dailyHours[$days])) {
                $dailyHours[$days] = 0;
            }
            $dailyHours[$days] += $hours;
        }
        
        // 校验每天的总时长
        foreach ($dailyHours as $days => $newTotalHours) {
            // 获取该日期已有的工作时长总和
            $existingHours = DailyLog::where('user_name', $userName)
                ->where('days', $days)
                ->sum('hours');
            
            if ($existingHours + $newTotalHours > 24) {
                return response()->json([
                    'message' => "日期 {$days} 的工作时长总和不能超过24小时，当前已有 " . number_format($existingHours, 2) . " 小时，新增 " . number_format($newTotalHours, 2) . " 小时"
                ], 422);
            }
        }
        
        $savedLogs = [];
        foreach ($logs as $logData) {
            $validated = [
                'user_name' => $userName,
                'project_name' => $logData['project_name'] ?? '',
                'days' => $logData['days'],
                'hours' => $logData['hours'] ?? 8,
                'content' => $logData['content'] ?? '',
                'remark' => $logData['remark'] ?? null,
            ];
            
            $log = DailyLog::create($validated);
            $savedLogs[] = $log;
        }
        
        return response()->json($savedLogs, 201);
    }
    
    public function batchSave(Request $request)
    {
        $userName = $request->input('user_name');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $logs = $request->input('logs', []); // 前端只提交有内容的记录，空数据不提交
        
        if (!$userName) {
            return response()->json(['message' => '用户名不能为空'], 422);
        }
        
        if (!$startDate || !$endDate) {
            return response()->json(['message' => '日期范围不能为空'], 422);
        }
        
        // 前端已经过滤了空数据，这里直接使用
        $validLogs = $logs;
        
        // 按日期分组新数据
        $newLogsByDate = [];
        foreach ($validLogs as $log) {
            $days = $log['days'];
            if (!isset($newLogsByDate[$days])) {
                $newLogsByDate[$days] = [];
            }
            $newLogsByDate[$days][] = $log;
        }
        
        // 获取提交数据中所有涉及的日期
        $submittedDates = array_unique(array_column($logs, 'days'));
        
        // 查询日期范围内该用户的所有记录（用于判断哪些日期需要删除）
        $dbLogs = DailyLog::where('user_name', $userName)
            ->whereBetween('days', [$startDate, $endDate])
            ->orderBy('days', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
        
        // 按日期分组数据库中的旧记录
        $oldLogsByDate = [];
        foreach ($dbLogs as $log) {
            $days = $log['days'];
            if (!isset($oldLogsByDate[$days])) {
                $oldLogsByDate[$days] = [];
            }
            $oldLogsByDate[$days][] = $log;
        }
        
        // 获取数据库中涉及的日期
        $dbDates = array_unique(array_column($dbLogs, 'days'));
        
        // 合并提交的日期和数据库中的日期，确保所有日期都被处理
        $allDates = array_unique(array_merge($submittedDates, $dbDates));
        
        $updatedCount = 0;
        $createdCount = 0;
        $deletedCount = 0;
        
        // 按日期处理
        foreach ($allDates as $days) {
            // 获取该日期的旧记录（从已分组的数据中获取，避免重复查询）
            $oldLogs = $oldLogsByDate[$days] ?? [];
            
            // 获取该日期的新记录（只包含有效数据）
            $newLogs = $newLogsByDate[$days] ?? [];
            
            $oldCount = count($oldLogs);
            $newCount = count($newLogs);
            
            // 如果新数据为空（该日期提交的数据都是空的），直接删除所有旧记录
            if ($newCount == 0 && $oldCount > 0) {
                foreach ($oldLogs as $oldLog) {
                    DailyLog::where('id', $oldLog['id'])->delete();
                    $deletedCount++;
                }
                continue; // 跳过后续处理
            }
            
            // 校验该日期的工作时长总和不能超过24小时（只在有新数据时校验）
            if ($newCount > 0) {
                $totalHours = 0;
                foreach ($newLogs as $newLog) {
                    $totalHours += floatval($newLog['hours'] ?? 8);
                }
                if ($totalHours > 24) {
                    return response()->json([
                        'message' => "日期 {$days} 的工作时长总和不能超过24小时，当前为 " . number_format($totalHours, 2) . " 小时"
                    ], 422);
                }
            }
            
            if ($newCount > $oldCount) {
                // 新数据更多：更新旧的，新增多余的
                for ($i = 0; $i < $oldCount; $i++) {
                    $oldLog = $oldLogs[$i];
                    $newLog = $newLogs[$i];
                    DailyLog::where('id', $oldLog['id'])->update([
                        'project_name' => $newLog['project_name'],
                        'hours' => $newLog['hours'] ?? 8,
                        'content' => $newLog['content'],
                        'remark' => $newLog['remark'] ?? null,
                    ]);
                    $updatedCount++;
                }
                // 新增多余的记录
                for ($i = $oldCount; $i < $newCount; $i++) {
                    $newLog = $newLogs[$i];
                    DailyLog::create([
                        'user_name' => $userName,
                        'days' => $days,
                        'project_name' => $newLog['project_name'],
                        'hours' => $newLog['hours'] ?? 8,
                        'content' => $newLog['content'],
                        'remark' => $newLog['remark'] ?? null,
                    ]);
                    $createdCount++;
                }
            } elseif ($newCount < $oldCount) {
                // 新数据更少：更新新的，删除多余的
                for ($i = 0; $i < $newCount; $i++) {
                    $oldLog = $oldLogs[$i];
                    $newLog = $newLogs[$i];
                    DailyLog::where('id', $oldLog['id'])->update([
                        'project_name' => $newLog['project_name'],
                        'hours' => $newLog['hours'] ?? 8,
                        'content' => $newLog['content'],
                        'remark' => $newLog['remark'] ?? null,
                    ]);
                    $updatedCount++;
                }
                // 删除多余的记录
                for ($i = $newCount; $i < $oldCount; $i++) {
                    DailyLog::where('id', $oldLogs[$i]['id'])->delete();
                    $deletedCount++;
                }
            } elseif ($newCount > 0) {
                // 数量相等：更新所有
                for ($i = 0; $i < $newCount; $i++) {
                    $oldLog = $oldLogs[$i];
                    $newLog = $newLogs[$i];
                    DailyLog::where('id', $oldLog['id'])->update([
                        'project_name' => $newLog['project_name'],
                        'hours' => $newLog['hours'] ?? 8,
                        'content' => $newLog['content'],
                        'remark' => $newLog['remark'] ?? null,
                    ]);
                    $updatedCount++;
                }
            } else {
                // 新数据为空：删除所有旧记录
                foreach ($oldLogs as $oldLog) {
                    DailyLog::where('id', $oldLog['id'])->delete();
                    $deletedCount++;
                }
            }
        }
        
        return response()->json([
            'message' => '保存成功',
            'updated' => $updatedCount,
            'created' => $createdCount,
            'deleted' => $deletedCount,
        ]);
    }
}

