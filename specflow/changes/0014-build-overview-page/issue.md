---
base_branch: development
created_at: 2026-06-12T02:51:52+00:00
created_by: "alvin"
tokens_at_new: 1765750
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 1874766
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 109016
---

# Issue: 建立overview頁面 (0014-build-overview-page)

## 想解決的問題

建立overview頁面

## 期望的結果

1. projectController overview function在return 前需要加入讀取project list，包含project change 紀錄都要拿出來，放入blade
2. 沒有change資料就暫時不顯示沒關係，需要建立一個新的service負責整理顯示用的資料
3. overview blade 改由接收參數顯示資料

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: projectController , 新的service , overview blade , routes/web.php
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
