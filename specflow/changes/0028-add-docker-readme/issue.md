---
base_branch: development
created_at: 2026-06-15T07:29:37+00:00
created_by: "alvin"
tokens_at_new: 3494768
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 3556006
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 61238
---

# Issue: 新增docker readme (0028-add-docker-readme)

## 想解決的問題

撰寫docker相關readme

## 期望的結果

我已經把打包成docker image的相關檔案都已經放入包含
docker/entrypoint.sh
docker/supervisord.conf
Dockerfile
.dockerignore
我打算打包後推到dockerhub上，介面上會需要一個readme檔案，幫我寫這個檔案
你應該知道spectrun是需要搭配specflow這個skill一起使用的服務，如果不確定內容可以看一下原本specflow的套件
https://www.npmjs.com/package/@virtualorz/specflow

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: readme.md
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
