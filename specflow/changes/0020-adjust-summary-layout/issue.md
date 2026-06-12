---
base_branch: development
created_at: 2026-06-12T08:01:45+00:00
created_by: "alvin"
tokens_at_new: 2592129
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 2683342
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 91213
---

# Issue: 調整summary頁面排版 (0020-adjust-summary-layout)

## 想解決的問題

summary頁面排版三個地方調整

## 期望的結果

1. 原始切版中每一個change都有自己一張卡片排列，目前的版本跟原本差距有點太大，重新讀取 public/files_specflow/2-主數字重排.html 確認一下
2. 原始切版中卡片最上面有一行文字"左側大數字 = 總跨度(wall-clock);細條為四階段比例,滑過顯各段時間 規格 設計 實作"，現在是virtualorz/spectrun · 語言/框架:PHP `^8.3` / Laravel `^13.8` · 17 筆 change(已完成 17、累計 20,131,790 tok)規格 設計 實作 擠成一行有點太亂
   調整一下變成 repo name不顯示，第一行寫 語言/框架:PHP `^8.3` / Laravel `^13.8` · 17 筆 change(已完成 17、累計 20,131,790 tok)
   第二行寫 左側大數字 = 總跨度(wall-clock);細條為四階段比例,滑過顯各段時間 規格 設計 實作
3. 分支選擇下拉選單放在人員頭像旁邊我覺得不太適合，很奇怪，不如放在上述第二行靠右對齊

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: summary blade 以及相關css檔案
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
