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
}

