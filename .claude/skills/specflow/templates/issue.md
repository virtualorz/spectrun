---
base_branch: <BASE_BRANCH>
created_at: <CREATED_AT>
---

# Issue: <一句話標題,描述你想做什麼>

## 想解決的問題

<2~3 句話描述現況的痛點。例如:CampaignProxyController 目前直接呼叫
ExternalApiClient,缺少快取層,每次請求都打外部 API,造成延遲。>

## 期望的結果

<改完之後系統應該變成什麼樣。寫成「使用者/開發者觀察得到的差異」,
不要寫實作細節。例如:同樣的 endpoint 在 5 分鐘內重複呼叫應該命中快取,
回應時間從 800ms 降到 < 50ms。>

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動:
- 不動:
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
