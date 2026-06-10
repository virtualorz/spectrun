---
base_branch: development
created_at: 2026-06-10T06:08:31+00:00
tokens_at_new: 23671
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 151853
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 128182
---

# Issue: 初版介面設計 (0001-init-ui-design)

## 想解決的問題

讀取切版html建立blade基礎頁面

## 期望的結果

讀取public/files_specflow 資料夾中的三個html檔案，製作成laravel blade頁面
可以先把資料完全寫死沒關係，先不做動態資料呈現，很單純的把html先轉存成blade，並抽取共用
以下幾件事情注意
1. spectrum-setup.html 名稱不變，記住左上角的圖示跟這幾個字"Spectrum / specflow family"
2. 3-專案總覽層.html 是整個系統首頁，左上角的圖示以及文字需要改成跟spectrum-setup.html一樣
3. 1-時間軸視圖.html 改名為time-line，同樣左上角的圖示以及文字需要改成跟spectrum-setup.html一樣
4. 2-主數字重排.html 改名為summary，同樣左上角的圖示以及文字需要改成跟spectrum-setup.html一樣
需要連同route一起建立讓我可以確認資料排版有沒有正確

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: routes/web.php , blade相關檔案
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
