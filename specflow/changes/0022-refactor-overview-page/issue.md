---
base_branch: development
created_at: 2026-06-15T02:43:24+00:00
created_by: "alvin"
tokens_at_new: 2955474
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 3034260
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 78786
---

# Issue: 重構overview頁面 (0022-refactor-overview-page)

## 想解決的問題

重構overview頁面排版

## 期望的結果

重新讀一次 "3-專案總覽層.html"，檢視以下
1. "搜尋專案"這一個輸入框沒有做出來，先做出介面就好，不帶搜尋功能
2. 每個專案的卡片中"累計跨度"資訊沒有出現
3. 每個專案的卡邊中"柱狀圖"沒有出現，可以取專案最接近的11個change出來做柱狀圖
4. 取消目前在專案卡片中排列每個change的內容

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: overview.blade ， 如果需要安裝其他套件就裝，後端projectController也允許異動
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
