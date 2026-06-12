---
base_branch: development
created_at: 2026-06-12T03:15:19+00:00
created_by: "alvin"
tokens_at_new: 1878369
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 1950691
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 72322
---

# Issue: 細部修改blade顯示資訊 (0015-refine-blade-display)

## 想解決的問題

overview , timeline , summary 三個頁面細部修改

## 期望的結果

1. overview 中每個project卡片要帶連結到summary，連結上需要帶入project的id
2. summary頁面需要一個圖示按鈕切換到timeline頁面
3. 相對的timeline也需要一個圖示按鈕切換到summary頁面
4. summary與timeline頁面都需要"同步專案資訊"按鈕
5. 以上描述的圖示切換按鈕幫我設計規劃放在適合的位置

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: overview , summary , timeline 三個blade
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
