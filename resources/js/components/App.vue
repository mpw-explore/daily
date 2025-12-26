<template>
  <div class="app-container">
    <!-- 头部 -->
    <div class="header">
      <label>名字</label>
      <input 
        type="text" 
        v-model="userName" 
        @input="handleUserNameChange"
        placeholder="请输入名字"
      />
    </div>

    <!-- 主内容区 -->
    <div class="main-content">
      <!-- 左侧列表 -->
      <div class="left-panel">
        <button class="btn btn-yellow" @click="resetList">重置列表</button>
        <table class="log-table">
          <thead>
            <tr>
              <th>日期</th>
              <th>工作时长</th>
              <th>项目</th>
              <th>内容</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="log in logs" 
              :key="log.id || log.tempId"
              @click="selectLog(log)"
              :class="{ active: selectedLog && (selectedLog.id || selectedLog.tempId) === (log.id || log.tempId) }"
            >
              <td>{{ formatDate(log.days) }}</td>
              <td>{{ formatHours(log.hours) }}</td>
              <td class="tooltip-cell" :data-tooltip="formatProjectName(log)"><span>{{ formatProjectName(log) }}</span></td>
              <td class="tooltip-cell" :data-tooltip="log.content || '-'"><span>{{ log.content || '-' }}</span></td>
              <td>
                <button 
                  class="btn btn-icon"
                  @click.stop="deleteLog(log)"
                >
                  🗑️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 右侧表单 -->
      <div class="right-panel">
        <form @submit.prevent="saveLog" v-if="selectedLog">
          <div class="form-group">
            <label>日期</label>
            <input 
              type="date" 
              v-model="selectedLog.days" 
              required
            />
          </div>
          
          <div class="form-group">
            <label>工作时长</label>
            <input 
              type="number" 
              v-model.number="selectedLog.hours" 
              min="0" 
              max="24" 
              step="0.1"
              required
            />
          </div>
          
          <div class="form-group">
            <label>项目</label>
            <select v-model="selectedLog.project_name" required>
              <option value="">请选择项目</option>
              <option v-for="project in projectList" :key="project" :value="project">
                {{ project }}
              </option>
            </select>
          </div>
          
          <div class="form-group">
            <label>内容</label>
            <input 
              type="text" 
              v-model="selectedLog.content" 
              required
            />
          </div>
          
          <div class="form-actions">
            <button type="submit" class="btn btn-blue">保存</button>
            <button type="button" class="btn btn-light-blue" @click="createNew">创建</button>
          </div>
        </form>
        <div v-else class="empty-form">
          <p>请从左侧列表选择一条记录进行编辑</p>
        </div>
      </div>
    </div>

    <!-- 底部操作区 -->
    <div class="footer">
      <button class="btn btn-green" @click="generateResult">生成结果</button>
      <button class="btn btn-white" @click="saveAll">保存</button>
    </div>

    <!-- 结果展示区 -->
    <div class="result-area" v-if="resultText">
      <textarea 
        v-model="resultText" 
        readonly
        class="result-textarea"
      ></textarea>
    </div>
    
    <!-- Toast 提示 -->
    <transition name="toast-fade">
      <div v-if="toastMessage" :class="['toast', `toast-${toastType}`]">
        {{ toastMessage }}
      </div>
    </transition>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'App',
  data() {
    return {
      userName: '张三',
      logs: [],
      selectedLog: null,
      resultText: '',
      projectList: [
        '楼宇电视',
        '海卓',
        '上药',
        '番茄网',
        '统一门户',
        'smg官网',
        '请假',
      ],
      tempIdCounter: 0,
      originalLogIds: new Set(), // 保存重置列表时的原始ID，用于跟踪删除
      toastMessage: '',
      toastType: 'info', // info, success, error
      toastTimer: null,
      currentWeekStartDate: null, // 当前周的起始日期
      currentWeekEndDate: null, // 当前周的结束日期
    };
  },
  methods: {
    showToast(message, type = 'info') {
      // 清除之前的定时器
      if (this.toastTimer) {
        clearTimeout(this.toastTimer);
      }
      
      this.toastMessage = message;
      this.toastType = type;
      
      // 2秒后自动消失
      this.toastTimer = setTimeout(() => {
        this.toastMessage = '';
        this.toastTimer = null;
      }, 2000);
    },
    
    formatDate(date) {
      if (!date) return '-';
      const d = new Date(date);
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },
    
    formatProjectName(log) {
      return log.project_name || '-';
    },
    
    formatHours(hours) {
      if (hours === null || hours === undefined || hours === '') {
        return '8.0';
      }
      const num = parseFloat(hours);
      if (isNaN(num)) {
        return '8.0';
      }
      return num.toFixed(1);
    },
    
    async handleUserNameChange() {
      // 名字改变时不清空列表，只有点击重置列表才加载
    },
    
    async resetList() {
      if (!this.userName) {
        this.showToast('请先输入名字', 'error');
        return;
      }
      
      try {
        // 计算当前周的日期范围（周一到周日）
        const today = new Date();
        const dayOfWeek = today.getDay();
        const monday = new Date(today);
        monday.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
        monday.setHours(0, 0, 0, 0);
        
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        sunday.setHours(23, 59, 59, 999);
        
        // 从数据库拉取当前周的数据
        const startDateStr = monday.toISOString().split('T')[0];
        const endDateStr = sunday.toISOString().split('T')[0];
        
        // 保存当前周的日期范围，供保存时使用
        this.currentWeekStartDate = startDateStr;
        this.currentWeekEndDate = endDateStr;
        
        const response = await axios.get('/api/daily-logs', {
          params: { 
            user_name: this.userName,
            start_date: startDateStr,
            end_date: endDateStr
          }
        });
        
        // 确保 dbLogs 是一个数组
        let dbLogs = [];
        if (response.data) {
          dbLogs = Array.isArray(response.data) ? response.data : [];
        }
        
        // 保存原始ID集合，用于跟踪删除
        this.originalLogIds = new Set(dbLogs.map(log => log.id).filter(id => id));
        
        // 创建当前周7天的数据，如果有数据库记录则使用数据库记录，否则创建空记录
        this.logs = [];
        for (let i = 0; i < 7; i++) {
          const date = new Date(monday);
          date.setDate(monday.getDate() + i);
          const dateStr = date.toISOString().split('T')[0];
      
          // 查找该日期的所有数据库记录（可能有多个）
          const existingLogs = dbLogs.filter(log => {
            return log.days === dateStr;
          });
          
          if (existingLogs.length > 0) {
            // 如果该日期有多条记录，全部添加到列表
            existingLogs.forEach(log => {
              this.logs.push({
                ...log,
                user_name: this.userName,
              });
            });
          } else {
            // 如果该日期没有记录，创建一条空记录
            this.logs.push({
              tempId: ++this.tempIdCounter,
              user_name: this.userName,
              project_name: '',
              days: dateStr,
              hours: 8.0,
              content: '',
              remark: null,
            });
          }
        }
        
        // 重置列表后，右侧保持为空
        this.selectedLog = null;
      } catch (error) {
        this.showToast('重置列表失败: ' + (error.response?.data?.message || error.message), 'error');
      }
    },
    
    selectLog(log) {
      this.selectedLog = { ...log };
    },
    
    saveLog() {
      // 只更新页面数据，不保存到数据库
      if (!this.selectedLog) return;
      
      // 格式化工作时长为一位小数（保留数值类型）
      if (this.selectedLog.hours !== null && this.selectedLog.hours !== undefined) {
        this.selectedLog.hours = parseFloat(parseFloat(this.selectedLog.hours).toFixed(1));
      } else {
        this.selectedLog.hours = 8.0;
      }
      
      // 更新列表中的记录
      const index = this.logs.findIndex(l => 
        (l.id && l.id === this.selectedLog.id) || 
        (l.tempId && l.tempId === this.selectedLog.tempId)
      );
      
      if (index !== -1) {
        // 直接更新记录，保留id或tempId
        Object.assign(this.logs[index], this.selectedLog);
      }
      
      // 更新selectedLog引用
      this.selectedLog = { ...this.logs[index] };
    },
    
    createNew() {
      if (!this.selectedLog) return;
      
      // 使用当前表单的内容创建新记录
      const newLog = {
        tempId: ++this.tempIdCounter,
        user_name: this.userName,
        project_name: this.selectedLog.project_name || '',
        days: this.selectedLog.days,
        hours: parseFloat(parseFloat(this.selectedLog.hours || 8.0).toFixed(1)),
        content: this.selectedLog.content || '',
        remark: this.selectedLog.remark || null,
      };
      
      // 找到该日期在列表中的最后一条记录的位置
      const targetDate = this.selectedLog.days;
      let insertIndex = this.logs.length;
      
      // 从后往前查找该日期的最后一条记录
      for (let i = this.logs.length - 1; i >= 0; i--) {
        if (this.logs[i].days === targetDate) {
          insertIndex = i + 1;
          break;
        }
      }
      
      // 如果找不到该日期的记录，查找应该插入的位置（按日期排序）
      if (insertIndex === this.logs.length) {
        for (let i = 0; i < this.logs.length; i++) {
          if (this.logs[i].days > targetDate) {
            insertIndex = i;
            break;
          }
        }
      }
      
      // 在指定位置插入新记录
      this.logs.splice(insertIndex, 0, newLog);
      this.selectedLog = { ...newLog };
    },
    
    deleteLog(log) {
      // 只从页面删除，不删除数据库中的记录
      const index = this.logs.findIndex(l => 
        (l.id && l.id === log.id) || 
        (l.tempId && l.tempId === log.tempId)
      );
      
      if (index !== -1) {
        this.logs.splice(index, 1);
      }
      
      // 如果删除的是当前选中的记录，清除选中状态
      // 否则保持右侧表单展开状态
      if (this.selectedLog && 
          ((this.selectedLog.id && this.selectedLog.id === log.id) ||
           (this.selectedLog.tempId && this.selectedLog.tempId === log.tempId))) {
        this.selectedLog = null;
      }
    },
    
    generateResult() {
      if (this.logs.length === 0) {
        this.showToast('没有数据可生成', 'error');
        return;
      }
      
      const sortedLogs = [...this.logs].sort((a, b) => {
        return new Date(a.days) - new Date(b.days);
      });
      
      let result = `周报 - ${this.userName}\n\n`;
      
      sortedLogs.forEach(log => {
        const date = this.formatDate(log.days);
        result += `${date} (${this.formatHours(log.hours)}小时)\n`;
        result += `项目: ${log.project_name || '-'}\n`;
        result += `内容: ${log.content || '-'}\n\n`;
      });
      
      this.resultText = result;
    },
    
    async saveAll() {
      if (!this.userName) {
        this.showToast('请先输入名字', 'error');
        return;
      }
      
      if (!this.currentWeekStartDate || !this.currentWeekEndDate) {
        this.showToast('请先重置列表', 'error');
        return;
      }
      
      try {
        // 只提交有内容的记录（空数据不提交，视作无数据）
        const allLogs = this.logs
          .filter(log => log.project_name && log.content) // 只保留有内容的记录
          .map(log => ({
            id: log.id || null,
            days: log.days,
            project_name: log.project_name,
            hours: parseFloat(parseFloat(log.hours || 8.0).toFixed(1)),
            content: log.content,
            remark: log.remark || null,
          }));
        
        const response = await axios.post('/api/daily-logs/batch-save', {
          user_name: this.userName,
          start_date: this.currentWeekStartDate,
          end_date: this.currentWeekEndDate,
          logs: allLogs,
        });
        
        const { updated = 0, created = 0, deleted = 0 } = response.data;
        const totalChanges = updated + created + deleted;
        
        if (totalChanges > 0) {
          this.showToast(`保存成功！`, 'success');
          // 重新加载列表以同步数据库中的ID
          await this.resetList();
        } else {
          this.showToast('没有需要保存的更改', 'info');
        }
      } catch (error) {
        this.showToast('保存失败: ' + (error.response?.data?.message || error.message), 'error');
      }
    },
  },
};
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background-color: #f5f5f5;
}

.app-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px;
}

.header {
  background: white;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.header label {
  margin-right: 10px;
  font-weight: 500;
}

.header input {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  width: 200px;
}

.main-content {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.left-panel {
  flex: 1;
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.right-panel {
  flex: 1;
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.empty-form {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  min-height: 300px;
  color: #999;
  font-size: 14px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}

.btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

.btn-yellow {
  background-color: #ffc107;
  color: #000;
  margin-bottom: 15px;
}

.btn-blue {
  background-color: #2196F3;
  color: white;
  margin-right: 10px;
}

.btn-light-blue {
  background-color: #E3F2FD;
  color: #2196F3;
}

.btn-pink {
  background-color: #E91E63;
  color: white;
  padding: 5px 10px;
}

.btn-green {
  background-color: #4CAF50;
  color: white;
  margin-right: 10px;
}

.btn-white {
  background-color: white;
  color: #333;
  border: 1px solid #ddd;
}

.btn-icon {
  font-size: 16px;
  padding: 5px 10px;
}

.log-table {
  width: 100%;
  border-collapse: collapse;
}

.log-table th,
.log-table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #eee;
}

.log-table th {
  white-space: nowrap;
}

.log-table td.tooltip-cell {
  position: relative;
  overflow: visible;
}

.log-table td.tooltip-cell > span {
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.log-table th:nth-child(1),
.log-table td:nth-child(1) {
  width: 120px;
  min-width: 120px;
  max-width: 120px;
}

.log-table th:nth-child(2),
.log-table td:nth-child(2) {
  width: 100px;
  min-width: 100px;
  max-width: 100px;
}

.log-table th:nth-child(3),
.log-table td:nth-child(3) {
  width: 100px;
  min-width: 100px;
  max-width: 100px;
}

.log-table th:nth-child(4),
.log-table td:nth-child(4) {
  width: 150px;
  min-width: 150px;
  max-width: 150px;
}

/* 自定义 Tooltip 样式 */
.tooltip-cell {
  position: relative;
  cursor: default;
}

.tooltip-cell::before {
  content: attr(data-tooltip);
  position: absolute;
  bottom: calc(100% + 10px);
  left: 50%;
  transform: translateX(-50%);
  padding: 14px 18px;
  background-color: rgba(51, 51, 51, 0.95);
  backdrop-filter: blur(10px);
  color: #fff;
  font-size: 16px;
  font-weight: 400;
  line-height: 1.6;
  white-space: normal;
  word-wrap: break-word;
  word-break: break-word;
  max-width: 350px;
  min-width: 180px;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
  z-index: 10000;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  text-align: left;
  letter-spacing: 0.3px;
  transition: none;
}

.tooltip-cell:hover::before {
  opacity: 1;
  visibility: visible;
}

.tooltip-cell::after {
  content: '';
  position: absolute;
  bottom: calc(100% + 4px);
  left: 50%;
  transform: translateX(-50%);
  border: 7px solid transparent;
  border-top-color: rgba(51, 51, 51, 0.95);
  z-index: 10001;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: none;
}

.tooltip-cell:hover::after {
  opacity: 1;
  visibility: visible;
}

.log-table th:nth-child(5),
.log-table td:nth-child(5) {
  width: 80px;
  min-width: 80px;
  max-width: 80px;
}

.log-table th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #333;
}

.log-table tbody tr {
  cursor: pointer;
  transition: background-color 0.2s;
  position: relative;
}

.log-table tbody tr .tooltip-cell {
  overflow: visible;
}

.log-table tbody tr:hover {
  background-color: #f5f5f5;
}

.log-table tbody tr.active {
  background-color: #e3f2fd;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.form-actions {
  margin-top: 30px;
  display: flex;
  gap: 10px;
}

.footer {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

.result-area {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.result-textarea {
  width: 100%;
  min-height: 200px;
  padding: 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  font-family: monospace;
  line-height: 1.6;
  resize: vertical;
}

/* Toast 提示样式 */
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 15px 20px;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 9999;
  max-width: 400px;
  font-size: 14px;
  font-weight: 500;
  color: white;
}

.toast-info {
  background-color: #2196F3;
}

.toast-success {
  background-color: #4CAF50;
}

.toast-error {
  background-color: #f44336;
}

/* Toast 动画 */
.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: opacity 0.3s, transform 0.3s;
}

.toast-fade-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-fade-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>

