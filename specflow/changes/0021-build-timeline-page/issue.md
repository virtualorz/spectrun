---
base_branch: development
created_at: 2026-06-12T08:31:27+00:00
created_by: "alvin"
tokens_at_new: 2687752
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 2949106
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 261354
---

# Issue: 完成timeline頁面 (0021-build-timeline-page)

## 想解決的問題

完成timeline頁面處理

## 期望的結果

首先我想要取消demo畫面中設計的"點任一條 issue 看完整歷程" 這個功能，因為資料目前沒有從github上拉下來，我認為先不動
資料/分層:跟 summary 一樣
timeline 主區顯示什麼: 甘特圖式時間軸(每個 change 一條 bar,依 issued→closed 的真實時間定位 left/width,左邊有日期軸)
LedgerService : 加 timeline 專用 method
路由 : /timeline/{project} 改成必填 , 左 nav 點選切換、overview/summary 連結帶 id
同步按鈕 : 跟summary頁面一樣補上功能，橫軸為日期(wall-clock)規格 設計 實作 執行中 這一行靠右放分支選單




## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: ProjectController、LedgerService、timeline blade、routes、可能 timeline.css、tests
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
