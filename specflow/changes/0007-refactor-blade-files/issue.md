---
base_branch: development
created_at: 2026-06-11T02:21:49+00:00
tokens_at_new: 735861
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 848332
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 112471
---

# Issue: 整理blade檔案內容 (0007-refactor-blade-files)

## 想解決的問題

整理blade中的檔案，想要將共用抽離與css抽離

## 期望的結果

仔細看過resources/views中的每一個頁面，除了welcome以外
其他頁面中都是css夾雜在畫面排版中不容易閱讀
希望可以整理一次每一個頁面的@style區域，將可以共用的部分抽離成獨立的css然後再layout中引入
不能共用的部分也抽離成每個頁面自己的css檔案，引入就好不要直接寫在blade中

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動:所有blade檔案與css檔案
- 不動:其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
