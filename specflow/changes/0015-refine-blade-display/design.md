---
created_at: 2026-06-12T03:19:58+00:00
---

# Design: 0015-refine-blade-display(細部修改 blade 顯示資訊)

> 三個 ledger 頁的導覽細修:overview 卡片連到 summary(帶 project id);summary↔timeline 互相切換的
> 圖示按鈕;summary/timeline 各加「同步專案資訊」按鈕(UI 佔位,後端待後續)。按鈕統一放 `.top-right`。
> scope:`overview`/`summary`/`timeline` blade(+ `LedgerService` 補 `id`,見決策 1)。

## 決策清單

- [ x ] **overview 專案卡片連到 summary(帶 project id)+ `LedgerService` 補 `id`**:`LedgerService::build()` 輸出每筆加 `'id' => $project->id`;`overview.blade` 把專案卡片包成連結 `<a href="{{ route('summary', ['project' => $p['id']]) }}">`。summary 路由無 `{project}` 參數 → Laravel 自動把 `project` 變成 query string(`/summary?project=1`),**不需改路由**。
  - 理由:Req1;卡片要帶「project 的 id」就必須讓 view data 有 id。
  - ⚠️ **scope 補上 `LedgerService`**(issue 只列 3 blade,但帶 id 一定要動它;改動極小,加一個欄位)。
  - 替代方案:用 `full_name` 當識別 → 否決,Req1 明指「project 的 id」;改路由成 `/summary/{project}` → 否決,scope 不動路由/controller。

- [ x ] **summary ↔ timeline 互相切換的圖示按鈕**:兩頁在 `.top-right`(theme 按鈕左側)各加一個 `<a class="icbtn">` + SVG —— summary 頁放「切換到時間軸」連 `route('timeline')`、timeline 頁放「切換到摘要」連 `route('summary')`。用連結即可,無需 JS。
  - 理由:Req2/3;`.icbtn` 是既有按鈕樣式,放 `.top-right` 與 theme 一致。
  - 替代方案:用 `<button onclick>` 導頁 → 否決,純連結更簡單、可被爬蟲/測試辨識。

- [ x ] **summary / timeline 各加「同步專案資訊」按鈕(本次為 UI 佔位)**:兩頁 `.top-right` 各加一個 `.icbtn`「同步專案資訊」(SVG 同步圖示 + `title`/`aria-label`)。**md→DB 同步後端尚未實作 → 本次按鈕不接行為**(以 `disabled` + tooltip「同步功能即將推出」呈現,避免誤導);待後續同步功能完成再 wire。
  - 理由:Req4 要按鈕存在;但 scope 只動 blade、無同步後端 → 只能放 UI 佔位。
  - 替代方案:現在就接 POST 同步 → 否決,後端不存在且超出 scope。

- [ x ] **按鈕統一放 `.top-right`、以 `.icbtn` 樣式並排(Req5 位置規劃)**:三頁的新圖示按鈕都置於右上角既有 `.top-right` 容器內,排在 `#theme` 左側,維持三頁一致的操作區。
  - 理由:Req5;集中在固定操作區,視覺一致、不破壞既有版面。
  - 替代方案:散在頁面各處 → 否決,不一致。

## 降級策略

- 本案**無外部系統呼叫**(純前端導覽連結 + 一個非功能性佔位按鈕),無 §10 降級需求。
- 「同步專案資訊」按鈕為佔位、不觸發任何請求。

## 影響範圍

- 直接改動:
  - `resources/views/overview.blade.php`(卡片包連結到 summary?project=id)
  - `resources/views/summary.blade.php`(加「切換到 timeline」+「同步專案資訊」按鈕)
  - `resources/views/timeline.blade.php`(加「切換到 summary」+「同步專案資訊」按鈕)
  - `app/Services/Ledger/LedgerService.php`(`build()` 輸出加 `id`)
- 間接影響:
  - overview 卡片可點擊進 summary(帶 id);summary/timeline 可互跳
- 不影響但需注意:
  - 不動路由 / controller / model;summary 目前仍是靜態 demo,**暫不依 `?project=id` 過濾**(id 先保留給日後)
  - 「同步專案資訊」按鈕無作用(待後續)

## 實作細節

> 描述做法,不寫程式碼。

- **LedgerService**:`build()` map 內加 `'id' => $project->id`(其餘不變)。
- **overview.blade**:`.pcard` 外層改 `<a class="pcard" href="{{ route('summary', ['project' => $p['id']]) }}">`(或卡片內加「查看摘要」連結);保留既有 class 樣式。
- **summary/timeline blade**:`.top-right` 內 `#theme` 前插入兩個 `<a class="icbtn">`(切換頁)與一個 `<button class="icbtn" disabled>`(同步佔位);各帶適當 SVG 與 `aria-label`。
- 兩頁切換按鈕互為對方路由(summary→timeline、timeline→summary)。

## 測試規範

- 手動 / 輕量驗證:`php artisan test` 全套不變紅;`./vendor/bin/pint`(若動到 php 才需)。
- 視覺確認三頁按鈕位置與連結正確(見待討論:是否補 Feature 測試)。

---

## 已討論問題

### 1. 決策 1 - scope 補 LedgerService 輸出 id
- **問題**:overview 卡片帶 project id 需動 `LedgerService::build()`(超出 3 blade)?
- **結論**:OK,加 `'id'` 欄位(用 id,不用 full_name)。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 2. 決策 3 - 同步按鈕做 UI 佔位
- **問題**:「同步專案資訊」按鈕後端沒做,放佔位可以嗎?
- **結論**:OK,按鈕做出來、本次為 UI 佔位(disabled),日後再 wire。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 3. summary 暫不吃 id
- **問題**:連結帶 `?project=id` 但 summary 暫不依 id 過濾,OK?
- **結論**:OK,id 先保留給日後。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 4. 不寫測試
- **問題**:要補 Feature 測試嗎?
- **結論**:不寫。**純排版/導覽連結修改,不帶 test**(使用者指定);驗證僅 `php artisan test` 全套不變紅 + pint。
- **影響**:未修改決策(測試規範:本次不補測試)。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->

