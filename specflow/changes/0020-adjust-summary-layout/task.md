---
created_at: 2026-06-12T08:22:50+00:00
closed_at: 2026-06-12T08:30:13+00:00
---

# Task: 0020-adjust-summary-layout(調整 summary 頁面排版)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. LedgerService::summaryFor 擴充回欄位
  - 檔案:`app/Services/Ledger/LedgerService.php`(修改)
  - 內容:`summaryFor` 的 change map 內每筆多回 `'slug'=>$c->slug`、`'decisions_done'`/`'decisions_total'`、`'tasks_done'`/`'tasks_total'`、`'discussion_count'`、`'issued_at'=>$c->issued_at`、`'closed_at'=>$c->closed_at`(直接從 ProjectChange 帶,不查 DB)。interface 簽章不變(回傳仍 array)。

- [x] 2. summary blade:header 拆兩行 + 移除 repo name
  - 檔案:`resources/views/summary.blade.php`(修改 `<main>` 頂部)
  - 內容:把現在的 `.legend`(擠一行)改成兩個區塊:
    - 第一行 `<div class="summaryline1">`:`語言/框架:{{ $selected['header']['tech_stack'] ?? '—' }} · {{ $selected['header']['total'] }} 筆 change(已完成 {{ $selected['header']['closed'] }}、累計 {{ number_format($selected['header']['tokens']) }} tok)`(**不顯示 display_name**)。
    - 第二行 `<div class="legend">`(沿用 class,改 flex space-between):左 = `<span>左側大數字 = 總跨度(wall-clock);細條為四階段比例,滑過顯各段時間</span><span><i style="background:var(--ph1)"></i>規格</span>…設計…實作`;右 = 分支下拉(task 4 移入)。

- [x] 3. summary blade:change 列改完整卡片
  - 檔案:`resources/views/summary.blade.php`(改 `@forelse($selected['changes'])` 區塊)
  - 內容:每筆改成 `.issue` 卡:
    ```
    <div class="issue">
      <div class="ihead">
        <div class="hero">
          @php $total = $c['seg']['spec']+$c['seg']['design']+$c['seg']['impl']; $closed = $c['status']==='closed'; @endphp
          <div class="big {{ $closed ? '' : 'run' }}">{{ $closed ? 人類可讀(總秒) : '進行中' }}</div>
          <div class="unit">{{ $closed ? '總跨度' : '進行中' }}</div>
          <div class="range">{{ $c['issued_at']?->format('m-d') }}{{ $closed && $c['closed_at'] ? ' → '.$c['closed_at']->format('m-d') : ($c['issued_at'] ? ' 起' : '') }}</div>
        </div>
        <div class="mbody">
          <div class="mrow1"><div class="iid"><b>{{ $c['number'] }}</b> · {{ $c['slug'] }}</div>
            <span class="badge {{ $closed ? 'closed' : 'running' }}">{{ $c['status'] }}</span></div>
          <div class="ititle">{{ $c['title'] }}</div>
          <div class="segwrap">
            <div class="segbar"><span class="sg sg1" style="flex:{{ $c['seg']['spec'] }}"></span><span class="sg sg2" style="flex:{{ $c['seg']['design'] }}"></span><span class="sg sg3" style="flex:{{ $c['seg']['impl'] }}"></span></div>
            <span class="seghint">new → close</span>
          </div>
          <div class="meta">
            <span class="chip mono">{{ number_format($c['tokens']) }} tok</span>
            <span class="chip">決策 {{ $c['decisions_done'] }}/{{ $c['decisions_total'] }}</span>
            <span class="chip">任務 {{ $c['tasks_done'] }}/{{ $c['tasks_total'] }}</span>
            <span class="chip">討論 {{ $c['discussion_count'] }}</span>
          </div>
        </div>
      </div>
    </div>
    ```
    用 `@php` 寫一個 `humanSpan($sec)` 把秒數轉「Xd / Xh Ym / Xm / <1m」。`@empty` 空狀態維持。

- [x] 4. summary blade:分支下拉移到 header 第二行靠右 + 從 top-right 移除
  - 檔案:`resources/views/summary.blade.php`(續上)
  - 內容:把 `.top-right` 內的 `<select id="branchSel" …>…</select>` 整段移除,改放到 task 2 第二行 `.legend` 的右側(`<select id="branchSel" data-project="{{ $projectId }}" …>`)。`@push('scripts')` 的 branchSel JS **完全不動**(靠 id 抓)。

- [x] 5. summary.css:header 兩行 + 下拉靠右的少量規則
  - 檔案:`public/css/summary.css`(修改/追加)
  - 內容:加 `.summaryline1{font-size:12px;color:var(--faint);margin-bottom:6px}`;`.legend` 加 `justify-content:space-between;align-items:center`(維持 flex-wrap);`#branchSel{height:32px;border-radius:8px;...}`(若需要)。卡片 class 不動(已存在)。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test` 全套 → 通過(40 tests / 110 assertions;summaryFor 擴充欄位向後相容,既有測試不受影響)
- [x] render smoke:`/summary/1` → 200,含 `class="issue"` 卡片、`class="summaryline1"`、`class="hero"`、`決策 3/3`、`id="branchSel"`;`o/demo`(repo name)只出現在左 nav、**header 第一行不再顯示 repo name**(改顯示語言/框架)
- [x] grep:`LedgerService::summaryFor` 仍不含 DB 查詢(純讀傳入物件,§2 rule 3)
- [x] `./vendor/bin/pint app` → 通過

## 執行後備註

### 實際改動檔案

- `app/Services/Ledger/LedgerService.php`(`summaryFor` 每筆 change 多回 slug/decisions/tasks/discussion/issued_at/closed_at)
- `resources/views/summary.blade.php`(header 拆兩行去 repo name、change 列改 `.issue` 完整卡片含 hero 大數字+日期+完整 meta chips、分支下拉從 top-right 移到 header 第二行靠右、加 `humanSpan` @php helper)
- `public/css/summary.css`(`.summaryline1`、`.legend` space-between、`.legend-l`、`#branchSel` 樣式)

### 偏離原計畫

- 無。依決策實作(完整卡片 + summaryFor 擴充 + header 兩行 + 下拉移位)。卡片 class 全沿用既有 summary.css(0007 抽出),CSS 只加 5 條 header/下拉相關規則。

### 發現的新問題或後續建議

- **卡片不可展開**:本次卡片無 accordion 詳情(原始切版點開有「四階段拆解 / 想解決的問題 / 設計決策清單 / 執行清單 / 偏離原計畫 / 改動檔案」)。那些需 `project_changes` 沒存的內文(問題全文、files、各 checkbox 文字)→ 要做需先擴 migration/sync 存內文 + summaryFor 回傳,屬後續較大 change。
- **segbar 高度**:現用既有 `.segbar`/.sg class;若視覺上條太細/太粗可再微調 css(本次沿用)。
- **實機驗收**:同步真實資料後,進 summary 看卡片的大數字(總跨度)、日期、決策/任務/討論 chips 是否符合預期;humanSpan 的單位(d/h/m)若想更精細可調。
