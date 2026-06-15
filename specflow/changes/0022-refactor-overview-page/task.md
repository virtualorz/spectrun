---
created_at: 2026-06-15T02:58:11+00:00
closed_at: 2026-06-15T03:04:37+00:00
---

# Task: 0022-refactor-overview-page(重構overview頁面)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. LedgerService 新增 `_humanSpan()` 私有 helper
  - 檔案:`app/Services/Ledger/LedgerService.php`
  - 內容:加 `private function _humanSpan(int $seconds): string`。`$s = max(0, $seconds)`;`>= 86400` → `intdiv($s,86400).'d '.intdiv($s%86400,3600).'h'`;`>= 3600` → `intdiv($s,3600).'h '.intdiv($s%3600,60).'m'`;否則 `intdiv($s,60).'m'`(0 → `0m`)。命名 `_` 前綴(§3)。

- [x] 2. LedgerService::build() 每專案補 `stats` + `spark`
  - 檔案:`app/Services/Ledger/LedgerService.php`
  - 內容:在 `build()` 的每專案 map 內,除既有欄位外加:
    - `stats` = `['total'=>$changes->count(), 'closed'=>$changes->where('status','closed')->count(), 'tokens'=>$changes->sum(fn($c)=>(int)($c->tokens_at_close ?? $c->tokens_at_new ?? 0)), 'span_human'=>$this->_humanSpan((int) $changes->where('status','closed')->sum(fn($c)=>$this->_span($c->issued_at, $c->closed_at)))]`。
    - `spark` = `$changes->sortBy('number')->values()->slice(-11)->map(fn($c)=>['tokens'=>(int)($c->tokens_at_close ?? $c->tokens_at_new ?? 0),'running'=>$c->status !== 'closed'])->values()->all()`。
    - `changes` 鍵可保留(blade 不再逐筆渲染,但留著不影響)。`_span()` 既有沿用。

- [x] 3. LedgerServiceInterface::build docblock 更新
  - 檔案:`app/Core/Contracts/Ledger/LedgerServiceInterface.php`
  - 內容:`build()` 簽章不變,只更新回傳結構 docblock,標註每筆多了 `stats`(total/closed/tokens/span_human)與 `spark`(array of {tokens,running})。

- [x] 4. overview.blade 加搜尋框
  - 檔案:`resources/views/overview.blade.php`
  - 內容:在 `.crumb` 之後、`.pgrid` 之前加 `<div class="search"><input id="q" type="text" placeholder="搜尋專案…" autocomplete="off"></div>`。**不掛任何事件**(純介面,決策 4)。

- [x] 5. overview.blade 卡片:四格統計改用 stats(第 4 格累計跨度)
  - 檔案:`resources/views/overview.blade.php`
  - 內容:移除卡內 `@php $changes/$total/$closed/$tokens @endphp` 那段運算(改吃 `$p['stats']`)。`.pstats` 四格改為:spec 總數 `{{ $p['stats']['total'] }}`、已完成(`.v g`)`{{ $p['stats']['closed'] }}`、累計 token `{{ number_format($p['stats']['tokens']) }}`、累計跨度 `{{ $p['stats']['span_human'] }}`(移除原「specflow ✓/—」格)。

- [x] 6. overview.blade 卡片:加 spark 柱狀圖、移除逐筆 change 清單
  - 檔案:`resources/views/overview.blade.php`
  - 內容:刪掉 `@if ($total > 0) … .clist … @endif` 整段。在 `.pstats` 之後、`.act` 之前加:
    `@php $bars = $p['spark']; $maxTok = collect($bars)->max('tokens') ?: 1; @endphp`
    `<div class="spark">@foreach ($bars as $b)<div class="b {{ $b['running'] ? 'run' : '' }}" style="height:{{ max(3, (int) round($b['tokens'] / $maxTok * 30)) }}px"></div>@endforeach</div>`
    無 change 時 `$bars` 為空,spark 內無 bar(空高度容器),不報錯。`.act` 行維持「最後同步…」。

- [x] 7. overview.css 微調(僅必要時)
  - 檔案:`public/css/overview.css`
  - 內容:`.search`/`.spark`/`.pstats` 樣式已存在,原則上不動。若 spark 空陣列導致塌陷,給 `.spark` 既有 `height:30px` 已足夠;確認無需新增即跳過(在執行後備註標明「未改」)。

- [x] 8. 更新 OverviewPageTest 斷言
  - 檔案:`tests/Feature/OverviewPageTest.php`
  - 內容:`test_shows_tracked_projects_with_changes` 因卡片不再列逐筆 change,移除 `assertSee('導入 Repository Pattern')` 與 `assertSee('0001')`;改斷言新卡片內容:`assertSee('octocat/spectrum')`、`assertSee('累計跨度')`、`assertSee('class="spark"', false)`、`assertSee('搜尋專案')`(搜尋框 placeholder)。為讓累計跨度有值,測試的 change 補 `issued_at`/`closed_at` 時間戳。其餘測試(無 user 302、空狀態)維持。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=OverviewPageTest` 全綠(3 passed)
- [x] `php artisan test` 全套不變紅(43 passed)
- [x] render smoke:`/`(overview)→ 200,含 `.search` 輸入框、四格含「累計跨度」(實測 `3d 1h`)、`.spark` 11 根 bar、且**不含**卡內逐筆 change 清單(citem=0);真實資料 17 筆專案 spark 取最新 11 筆
- [x] `./vendor/bin/pint app tests`

## 執行後備註

### 實際改動檔案

- `app/Services/Ledger/LedgerService.php` —— `build()` 每專案補 `stats`(total/closed/tokens/span_human)+ `spark`(最新 11 筆);新增私有 `_humanSpan(int): string`
- `app/Core/Contracts/Ledger/LedgerServiceInterface.php` —— `build()` docblock 更新(簽章不變)
- `resources/views/overview.blade.php` —— 加搜尋框(純介面)、四格第 4 格改「累計跨度」、加 spark 柱狀圖、移除卡內逐筆 change 清單
- `tests/Feature/OverviewPageTest.php` —— change 補 issued_at/closed_at;斷言改為累計跨度/spark/搜尋框,並 assertDontSee 逐筆 change 標題

### 偏離原計畫

- task 7(overview.css):**未改檔**。`.search`/`.spark`/`.spark .b.run`/`.pstats` 樣式 overview.css 早已具備(0007 抽稿時就含 reference 全套),空 spark 由既有 `.spark{height:30px}` 撐住,無需新增規則。
- 範圍:依 design 決策 5,後端聚合落在 **LedgerService**(+ interface docblock),ProjectController@overview **未改**(已呼叫 build 並傳遞)。與 issue 範圍「ProjectController」的差異已於 design 標註並經勾選同意。

### 發現的新問題或後續建議

- 搜尋框目前純介面、無過濾邏輯(依 issue 要求)。後續若要接搜尋,可在此 input 掛前端即時過濾或走後端 query。
- overview.blade 的 `build()` 回傳仍含 `changes` 明細鍵,目前 blade 未使用;若確定總覽頁永不需要,可在後續精簡 `build()` 輸出以減少 payload。
