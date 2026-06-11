---
created_at: 2026-06-11T02:24:39+00:00
---

# Design: 0007-refactor-blade-files(整理 blade 檔案內容)

> 把各頁 blade 內嵌的 `<style>` 抽出成獨立 CSS 檔:共用部分在 layout 引入、各頁專屬的
> 也抽成自己的 CSS 檔(只 `<link>` 引入,不再寫在 blade 內)。**只搬 CSS,不動排版結構與 JS。**
> scope:所有 blade 與 css 檔(welcome 除外)。

## 背景:目前狀態(已實測)

- 內嵌 `<style>` 的頁面:`setup`、`login`、`repository`(各 149 行,**三者 byte-identical**)、
  `overview`(91)、`timeline`(89)、`summary`(91);元件 `brand`、`user-menu` 也各自 `@push` 一段 inline CSS。
- `:root` 設計 token:**overview = summary 相同、timeline 不同、card 家族又一版**(共 3 版)——不可全站統一(0001 已知,強抽會改外觀)。
- 跨所有頁**相同**的選擇器:`*{box-sizing}`、`.mono`、brand(`.brand`/`.spectrum-mark`/…)、`.icbtn`、user-menu —— 可安全共用。
- `layouts/spectrum.blade.php` 目前無 inline CSS、也沒用 `@vite`;`welcome.blade.php` 不在範圍。

## 決策清單

- [ x ] **CSS 用 `public/css/*.css` + `{{ asset() }}` `<link>` 引入(不走 Vite)**:把抽出的 CSS 放 `public/css/`,layout 與各頁用 `<link rel="stylesheet" href="{{ asset('css/xxx.css') }}">` 載入。
  - 理由:這些是手寫的設計頁(非 Tailwind),layout 目前未用 `@vite`,`asset()`+`<link>` 無 build step、`php artisan serve` 直接可用,最貼近 issue「抽成 css 檔、引入就好」。
  - 替代方案:走專案既有 Vite(`resources/css` + `@vite`)→ 雖是 project.md §1 名義前端,但要 build、per-page 載入較繁(列待討論)。

- [ x ] **檔案結構:1 份全站共用 + card 家族 1 份 + ledger 各 1 份**:
    - `public/css/spectrum.css`(**layout 載入,全站共用**):跨所有頁相同的選擇器 —— `*{box-sizing}`、`.mono`、brand(`.brand`/`.spectrum-mark`+children/`.brand b`/`.brand .by`)、`.icbtn`、user-menu(`.usermenu` 系列)。
    - `public/css/card.css`(setup/login/repository 載入):card 家族樣式(三頁相同,去重成一份),扣掉已進 spectrum.css 的共用選擇器。
    - `public/css/overview.css`、`timeline.css`、`summary.css`(各 ledger 頁載入):各自樣式扣掉共用選擇器。
  - 理由:card 三頁 byte-identical → 一份去重;ledger 版面各異 → 各自一份;符合「共用進 layout、專屬各自一檔」。
  - 替代方案:每頁一檔(card 也拆 3 份)→ 否決,三頁相同會製造重複;或全部一大檔 → 否決,token 衝突。

- [ x ] **元件(brand、user-menu)的 inline CSS 一併移入 `spectrum.css`,元件變純 markup**:移除 `components/brand.blade.php`、`components/user-menu.blade.php` 內的 `@once @push('styles')` 樣式區,CSS 改由 spectrum.css 全站提供。
  - 理由:issue 要 CSS 不寫在 blade 內;brand/user-menu 全站共用,放 spectrum.css 最自然。
  - 替代方案:各自獨立 `brand.css`/`user-menu.css` 由元件 `@once` link → 可行但檔案更碎,且它們本就全站用,併入 spectrum.css 較簡潔。

- [ x ] **不抽 `:root` token 成單一共用、不抽共用 `body`**:token 留在各自 css(card.css 一份、ledger 各頁一份);`body`(card 置中 vs ledger 儀表板)也各自保留。
  - 理由:token 有 3 版、body 兩種,強抽會改外觀;依附各頁最安全。
  - 替代方案:overview/summary token 相同,抽成一份 ledger-token.css 共用 → 可小幅去重,但增一層相依(列待討論,預設不抽)。

- [ x ] **只抽 CSS,不動排版 HTML 與 inline `<script>`;welcome 不動**:`@section('content')` 的 HTML、`@push('scripts')` 的 JS 維持原樣,只把 `<style>…</style>` 換成 `<link>`。
  - 理由:issue 只針對「@style 區域」與閱讀性;動到 HTML/JS 會放大風險與 diff。
  - 替代方案:順手重排 HTML / 抽 JS → 否決,超出 issue、增加回歸風險。

## 影響範圍

- 直接改動:
  - 新增 `public/css/spectrum.css`、`card.css`、`overview.css`、`timeline.css`、`summary.css`
  - 改 `resources/views/layouts/spectrum.blade.php`(head 加 `<link spectrum.css>`)
  - 改 `resources/views/components/brand.blade.php`、`user-menu.blade.php`(移除 inline `<style>`)
  - 改 `resources/views/setup.blade.php`、`login.blade.php`、`repository.blade.php`(inline `<style>` → `<link card.css>`)
  - 改 `resources/views/overview.blade.php`、`timeline.blade.php`、`summary.blade.php`(inline `<style>` → `<link {page}.css>`)
- 間接影響:
  - 各頁 HTML 結構與 class 名稱不變 → 視覺應**完全一致**(本次驗收重點)
- 不影響但需注意:
  - 不動 `welcome.blade.php`、controllers、routes、model、migration、inline `<script>`
  - CSS 變數靠各頁 css 的 `:root` 提供;spectrum.css 的 brand/icbtn 用 `var()`,跨檔解析不受載入順序影響

## 實作細節

> 描述做法,不寫程式碼。

- **抽取共用選擇器**:從任一頁的 `<style>` 取出 `*{box-sizing}`、`.mono`、brand 群組、`.icbtn`、user-menu 群組,寫入 `public/css/spectrum.css`(這些選擇器各頁 byte 相同,取一份即可)。
- **card.css**:取 setup 的 `<style>` 內容(= login/repository 相同),移除上述共用選擇器後其餘全進 `card.css`(含該家族的 `:root`、`body` 置中、`.card`/`.field`/`.btn`/`.howto`/`.secnote`/`.conn`/`.repo`/`.interval`/`.foot`/`.hidden` 等)。
- **overview/timeline/summary.css**:各取該頁 `<style>`,移除共用選擇器後其餘進各自 css(含各自 `:root` 與版面)。
- **layout**:`<head>` 內、`@stack('styles')` 之前加 `<link rel="stylesheet" href="{{ asset('css/spectrum.css') }}">`。
- **各頁**:把 `@push('styles')<style>…</style>@endpush` 換成 `@push('styles')<link rel="stylesheet" href="{{ asset('css/<檔名>.css') }}">@endpush`。
- **元件**:刪掉 `@once @push('styles')…@endpush` 整段(markup 與既有 `@push('scripts')` 保留)。

## 測試規範

- 純 CSS 搬移,無商業邏輯,不寫 PHPUnit。pint 不適用 CSS/blade。
- 驗證:
  1. `php artisan view:clear && view:cache` 全 blade 編譯無誤。
  2. `php artisan serve` + curl:6 個頁面與 5 個 `public/css/*.css` 皆回 200;頁面 HTML 不再含 `<style>`(改為 `<link ... css>`)。
  3. **目視比對**:逐頁(login/repository/setup/overview/timeline/summary)在重構前後外觀一致、深淺色切換正常 —— 此為本次主要驗收(需使用者開瀏覽器確認)。

---

## 已討論問題

### 1. 決策 1 - CSS 機制用 asset()
- **問題**:CSS 載入用 `public/css` + `asset()` `<link>` 還是 Vite?
- **結論**:用 `public/css` + `asset()`(無 build)。
- **影響**:未修改決策(確認既有推薦)。
- **討論時間**:2026-06-11

### 2. 決策 4 - token 各頁各一份
- **問題**:overview/summary token 相同,要不要抽共用 token css?
- **結論**:各頁各一份,不另抽共用 token,維持單純。
- **影響**:未修改決策(確認既有預設)。
- **討論時間**:2026-06-11

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
