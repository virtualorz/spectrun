---
created_at: 2026-06-12T08:09:48+00:00
---

# Design: 0020-adjust-summary-layout(調整 summary 頁面排版)

> summary 三處排版調整:(1) change 列改回**卡片版型**(對齊原始切版 `2-主數字重排.html`);
> (2) header 擠成一行 → 拆兩行、移除 repo name;(3) 分支下拉從頭像旁移到 header 第二行靠右。
> **關鍵**:`public/css/summary.css` 早已含完整卡片 class(`.issue/.hero/.big/.mbody/.segwrap/.meta` 等,0007 抽出)→ 主要是 blade 改用這些結構,CSS 幾乎不動。
> scope:`summary` blade + `summary.css`(僅 header 兩行/下拉靠右的微調)。

## 決策清單

- [ x ] **change 列改回完整卡片(`.issue` > `.ihead` > `.hero` + `.mbody`)**:每筆 change 用既有卡片 class 渲染:
    - `.hero`(左側大數字):`.big` = 總跨度(seg 三段相加 humanSpan;closed)或「進行中」(非 closed);`.unit` =「總跨度」/「進行中」;**`.range` = `issued_at → closed_at` 日期**(非 closed 顯示「{issued} 起」)。
    - `.mbody`:`.mrow1`(`.iid` = `<b>{number}</b> · {slug}` + `.badge` status)、`.ititle` = title、`.segwrap`(segbar 三段 + `.seghint`「new → close」)、`.meta` **完整 chips**:tokens、**決策 {decisions_done}/{decisions_total}**、**任務 {tasks_done}/{tasks_total}**、**討論 {discussion_count}**。
    - 不做 accordion 展開(無 detail 內文資料如 files)→ `.ihead` 用 `<div>`。
  - 理由:issue #1 + 使用者選 (a) 一起做完整卡片;`summary.css` 已有全部 class。
  - 替代方案:只放 tokens chip / 不放日期 → 否決(使用者要完整);accordion 內文展開 → 需 files/問題內文,summaryFor 不回 → 仍不做。

- [ x ] **`LedgerService::summaryFor` 擴充回欄位(供完整卡片)**:每筆 change 多回 `slug`、`decisions_done`、`decisions_total`、`tasks_done`、`tasks_total`、`discussion_count`、`issued_at`、`closed_at`(直接從 `ProjectChange` 帶,純讀不查 DB,§2 rule 3)。**scope 納入 `LedgerService`(+ interface 簽章不變)**。
  - 理由:卡片的 meta chips + hero 日期需要這些;使用者選 (a) 納入 LedgerService。
  - 替代方案:blade 直接讀 model → 否決,view 只吃 summaryFor 整理好的陣列(§2 分層);summaryFor 不擴充 → 否決(使用者要完整卡片)。

- [ x ] **header 拆兩行、移除 repo name**:把現在擠一行的 `.legend` 改兩行 —
    - 第一行:`語言/框架:{{ tech_stack }} · {{ total }} 筆 change(已完成 {{ closed }}、累計 {{ tokens }} tok)`(**不顯示 display_name/repo name**)。
    - 第二行:`左側大數字 = 總跨度(wall-clock);細條為四階段比例,滑過顯各段時間` + 規格/設計/實作 圖例 + **分支下拉靠右**。
  - 理由:issue #2;一行太亂、repo name 多餘。
  - 替代方案:維持一行 → 否決(使用者明確要兩行)。

- [ x ] **分支下拉移到 header 第二行靠右**:從 `.top-right`(`<x-user-menu>` 旁)移除 `<select id="branchSel">`,改放 header 第二行右側(該行 `display:flex; justify-content:space-between`)。**JS(branchSel 的 lazy 載入 / change 存檔)邏輯不變**,只是 DOM 位置改。
  - 理由:issue #3;放頭像旁不協調。
  - 替代方案:留頭像旁 → 否決。

- [ x ] **CSS 微調(`summary.css`)**:卡片 class 沿用既有,不動。僅為「header 兩行 + 第二行 flex 對齊 + 分支下拉樣式」加少量規則(例:`.summary-meta`/`.legend` 兩行容器、第二行 space-between)。
  - 理由:支援上述兩行 header + 下拉靠右;卡片樣式已存在。
  - 替代方案:全用 inline style → 否決,集中在 css 較整潔(但小量 inline 可接受)。

## 降級策略

- 本案**純前端排版**(無外部呼叫、無 DB 變動),無 §10 降級需求。
- 邊界:無 change(空清單)→ 維持現有空狀態文案;非 closed change → 大數字顯示「進行中」類標示。

## 影響範圍

- 直接改動:
  - `resources/views/summary.blade.php`(change 列改卡片、header 兩行、下拉移位)
  - `public/css/summary.css`(header 兩行/下拉靠右的少量規則)
- 間接影響:
  - summary 視覺更接近原始切版;分支下拉位置更合理
- 不影響但需注意:
  - 不動 `ProjectController`/`LedgerService`/`summaryFor`(資料來源不變)、其他頁
  - 卡片的 `.meta` 完整 chips(決策/任務/討論)與 hero 日期範圍**需 summaryFor 多回欄位**(動 LedgerService)→ 本次不做(見待討論)

## 實作細節

> 描述做法,不寫程式碼。

- **humanSpan**:blade 內小 `@php` helper 把秒數轉「Xd / Xh Ym / Xm」(各 change 的總跨度 = seg 三段相加)。
- **卡片**:`@forelse($selected['changes'] as $c)` → `.issue` 卡;`status === 'closed'` 決定 `.big` 是總跨度或「進行中」、badge 文字/class。
- **header**:`.main` 頂部用兩個 `<div>`(line1 stats、line2 legend+dropdown);line2 用 flex space-between,左圖例右下拉。
- **下拉**:`<select id="branchSel" data-project>` 整段搬到 line2 右側;`@push('scripts')` 的 branchSel JS 不變(靠 id 抓)。

## 測試規範

- 純排版:`php artisan test` 全套不變紅(未動邏輯);render smoke:`/summary/{id}` 仍 200、含 `.issue` 卡片 + 兩行 header + `#branchSel`。
- `./vendor/bin/pint`(若動到 php;本次多為 blade/css)。

---

## 已討論問題

### 1. 決策 1 - 卡片完整度(納入 LedgerService)
- **問題**:卡片要完整 meta chips(決策/任務/討論)+ hero 日期嗎?(需動 LedgerService,超原 blade+css scope)
- **結論**:**(a) 一起做**。`LedgerService::summaryFor` 擴充回 slug/decisions/tasks/discussion/issued_at/closed_at;卡片 meta 顯示完整 chips、hero 顯示日期範圍。
- **影響**:**決策 1 已更新**(完整卡片)+ **新增決策 5**(summaryFor 擴充);scope 納入 `LedgerService`;checkbox 重置。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
