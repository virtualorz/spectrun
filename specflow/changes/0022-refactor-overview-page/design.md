---
created_at: 2026-06-15T02:52:15+00:00
---

# Design: 0022-refactor-overview-page

> 對照參考稿 `public/files_specflow/3-專案總覽層.html` 的「總覽層」卡片,把目前 overview 卡片補齊四項缺漏:搜尋框(純介面)、累計跨度、token 柱狀圖(spark)、並移除卡內逐筆 change 清單。

## 決策清單

- [ x ] **卡片改回 reference 結構,移除卡內逐筆 change 清單**:每張 `.pcard` 內容固定為「專案名 `.pn` + 技術棧 `.pt` + 四格統計 `.pstats` + token 柱狀圖 `.spark` + 最後一行 `.act`」,刪掉目前 `@if ($total > 0)` 那段逐筆 `.clist`/`.citem`(對應 issue 第 4 點)。逐筆 change 仍可在 summary 頁看,總覽層只給「鳥瞰」。
  - 理由:總覽層的職責是跨專案掃視,逐筆明細屬 summary 頁;參考稿的總覽卡也不列 change。
  - 替代方案:保留清單但摺疊 → 仍與參考稿不符、且資訊重複,否決。

- [ x ] **四格統計第 4 格改為「累計跨度」**:`.pstats` 四格固定為 spec 總數 / 已完成 / 累計 token / 累計跨度;移除目前的「specflow ✓/—」格(對應 issue 第 2 點)。累計跨度 = 該專案**所有已 closed 的 change** 之 `(closed_at − issued_at)` 秒數總和,以 `Nd Nh` / `Nh Nm` / `Nm` 人類可讀格式呈現(與參考稿 `humanSpan` 一致)。
  - 理由:跨度是「這個專案到底花了多少 wall-clock」的關鍵指標,比 specflow 旗標更有資訊量;只計 closed 因 running 尚未結算(同參考稿)。
  - 替代方案:把 specflow 旗標移到 `.act` 行 → 旗標對總覽價值低,直接拿掉即可,否決。

- [ x ] **新增 token 柱狀圖(spark),取最近 11 筆 change**:每張卡片加一條 `.spark`,以 change 的 token(`tokens_at_close ?? tokens_at_new`)為高度、依 number 排序取**最新 11 筆**(數量 > 11 時只留尾端 11 筆,舊→新由左到右),running 狀態的 bar 用 `.b.run`(橙色)(對應 issue 第 3 點)。
  - 理由:參考稿即以此給每個專案一個「token 消耗節奏」縮圖;限 11 筆與參考稿視覺密度一致,避免 bar 過細。
  - 替代方案:畫全部 change → 專案 change 多時(本機已有 17 筆)bar 會太細糊成一片,否決。

- [ x ] **新增搜尋框(純介面、不接邏輯)**:在 `.pgrid` 上方加 `.search > input#q placeholder="搜尋專案…"`,只渲染外觀,**不掛任何 oninput/過濾邏輯**(對應 issue 第 1 點:先做介面)。
  - 理由:issue 明確要求「先做出介面就好,不帶搜尋功能」,先佔位後續再接。
  - 替代方案:直接做前端即時過濾 → 超出 issue 範圍,否決。

- [ x ] **聚合資料一律由 `LedgerService::build()` 產出,不在 Controller/Blade 運算**:擴充 `build()` 每個專案多回 `stats`(total / closed / tokens / span_human)與 `spark`(最近 11 筆 `{tokens, running}`);新增私有 helper `_humanSpan(int $seconds)`(可沿用既有 `_span()` 算秒數)。`build()` 回傳型別註解同步更新;`LedgerServiceInterface::build` 簽章不變。
  - 理由:依 project.md §2,跨資料的聚合/格式化屬 Service;Controller 只走流程、Blade 只渲染,兩者都不該算日期跨度。雖然 issue 範圍寫「ProjectController」,但本質是「後端允許異動」,聚合放 LedgerService 才合規(見影響範圍的範圍說明)。
  - 替代方案:在 Blade 用 `@php` 算跨度/spark → 違反 §2 與 §7(view 不寫邏輯),否決;在 Controller 算 → 違反 §2(Controller 不做資料運算),否決。

- [ x ] **不引入前端圖表套件**:spark 與既有 `.pstats` 都用純 CSS(overview.css 已有 `.search`/`.spark`/`.spark .b.run` 樣式),不裝任何 chart library。
  - 理由:柱狀圖只是等寬 flex bar,純 CSS 即可;避免無謂相依與打包成本(issue 雖允許裝套件,但此處不需要)。
  - 替代方案:Chart.js / ApexCharts → 殺雞用牛刀,否決。

## 影響範圍

- 直接改動:
  - `resources/views/overview.blade.php` —— 重排卡片:加 `.search`、改第 4 格為累計跨度、加 `.spark`、移除卡內 `.clist` 逐筆 change;`.act` 改顯示最後同步(維持)。
  - `app/Services/Ledger/LedgerService.php` —— `build()` 每專案加 `stats` + `spark`;新增私有 `_humanSpan()`。
- 間接影響(被呼叫端、被繼承類):
  - `app/Core/Contracts/Ledger/LedgerServiceInterface.php` —— `build()` 回傳結構的 docblock 更新(簽章不變)。
  - `app/Http/Controllers/ProjectController@overview` —— 預期**不需改邏輯**(已呼叫 `build()` 並把結果丟給 view);若 view 取用鍵名調整,最多同步傳遞,不新增運算。
  - `public/css/overview.css` —— 預期沿用既有 `.search`/`.spark`/`.pstats`;僅在缺漏時微調(例如卡片在無 change 時的空 spark)。
- 不影響但需注意:
  - `tests/Feature/OverviewPageTest.php` —— 現有 `test_shows_tracked_projects_with_changes` 斷言看得到 change 標題與 `0001`,因本次**刻意移除卡內逐筆清單**會失效,需改為斷言新卡片內容(累計跨度、spark bar、搜尋框);這是設計性行為變更,非「為過測試改斷言」(§7)。
  - summary / timeline / repository 等頁不動。
  - **範圍說明**:issue 範圍列「只動 overview.blade + ProjectController」,但依 §2 聚合需落在 LedgerService;故實際後端改的是 LedgerService(+ interface docblock)而非 ProjectController。屬合理的同類後端調整,在此明確標註。

## 實作細節

- `LedgerService::build()`:在現有每專案陣列上補
  - `stats`:`total`(change 數)、`closed`(status=closed 數)、`tokens`(Σ `tokens_at_close ?? tokens_at_new`)、`span_human`(Σ closed 的 `_span(issued_at, closed_at)` 秒數 → `_humanSpan()`)。
  - `spark`:change 依 `number` 排序、取尾端 11 筆,每筆 `{ tokens: tokens_at_close ?? tokens_at_new ?? 0, running: status !== 'closed' }`。
  - `_humanSpan(int $seconds)`:`>= 1 天` → `Nd Nh`;`>= 1 小時` → `Nh Nm`;否則 `Nm`(0 顯示 `0m`)。
- `overview.blade.php`:卡片用 `$p['stats']` 與 `$p['spark']` 渲染;spark bar 高度 = `max(3, round(tokens / maxTokens * 30))` px(maxTokens 取該卡 spark 內最大值,避免除以 0),running 加 `run` class;搜尋框只放 `<input id="q" placeholder="搜尋專案…">` 不掛事件。
- 無額外細節,其餘見 task.md。

---

## 已討論問題

<!-- 討論記錄會自動追加在這裡。初始狀態為空。 -->

---

## 待討論問題

> 若無問題,請保持此區塊只有本說明文字(不需刪除整個區塊)。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
