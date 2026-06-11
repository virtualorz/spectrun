---
created_at: 2026-06-11T02:31:37+00:00
closed_at: 2026-06-11T02:37:41+00:00
---

# Task: 0007-refactor-blade-files(整理 blade 檔案內容)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 建立 public/css/spectrum.css(全站共用)
  - 檔案:`public/css/spectrum.css`(新增)
  - 內容:從各頁 `<style>` 取出跨頁相同的選擇器 —— `*{box-sizing}`、`.mono`、brand 群組(`.brand`/`.spectrum-mark`+children/`.brand b`/`.brand .by`)、`.icbtn`(+hover);再加上 user-menu 元件的 `.usermenu` 系列 CSS。

- [x] 2. 建立 public/css/card.css(setup/login/repository 共用)
  - 檔案:`public/css/card.css`(新增)
  - 內容:取 setup.blade 的 `<style>` 內容(= login/repository 相同),移除已進 spectrum.css 的共用選擇器(`*`、`.mono`、brand 群組、`.icbtn`),其餘全放入(含 `:root`/`html.light`、`body` 置中、`.topbar`/`.card`/`.field`/`.btn`/`.howto`/`.secnote`/`.conn`/`.repo`/`.tag`/`.addrow`/`.interval`/`.btnrow`/`.done`/`.foot`/`.hidden` 等)。

- [x] 3. 建立 public/css/overview.css
  - 檔案:`public/css/overview.css`(新增)
  - 內容:取 overview.blade 的 `<style>`,移除已進 spectrum.css 的共用選擇器,其餘(含該頁 `:root` 與版面 CSS)放入。

- [x] 4. 建立 public/css/timeline.css
  - 檔案:`public/css/timeline.css`(新增)
  - 內容:同上,取 timeline.blade 的 `<style>` 移除共用選擇器後放入。

- [x] 5. 建立 public/css/summary.css
  - 檔案:`public/css/summary.css`(新增)
  - 內容:同上,取 summary.blade 的 `<style>` 移除共用選擇器後放入。

- [x] 6. layout 引入 spectrum.css
  - 檔案:`resources/views/layouts/spectrum.blade.php`(修改)
  - 內容:`<head>` 內、`@stack('styles')` 之前加 `<link rel="stylesheet" href="{{ asset('css/spectrum.css') }}">`。

- [x] 7. setup/login/repository 改用 card.css
  - 檔案:`resources/views/setup.blade.php`、`login.blade.php`、`repository.blade.php`(各修改)
  - 內容:把 `@push('styles')` 內的 `<style>…</style>` 換成 `<link rel="stylesheet" href="{{ asset('css/card.css') }}">`。

- [x] 8. overview/timeline/summary 改用各自 css
  - 檔案:`resources/views/overview.blade.php`、`timeline.blade.php`、`summary.blade.php`(各修改)
  - 內容:各頁 `@push('styles')` 內 `<style>…</style>` → `<link rel="stylesheet" href="{{ asset('css/<page>.css') }}">`(對應 overview/timeline/summary)。

- [x] 9. 元件移除 inline CSS
  - 檔案:`resources/views/components/brand.blade.php`、`user-menu.blade.php`(各修改)
  - 內容:刪除 `@once @push('styles')…@endpush` 整段(CSS 已進 spectrum.css);brand 保留純 markup;user-menu 保留 markup 與既有 `@push('scripts')` toggle JS。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] view:cache 全 blade 編譯成功 → 通過
- [x] curl:`/login`、`/repository`、`/`、`/timeline`、`/summary` 回 200(各頁 inline `<style>`=0、含 spectrum.css + 對應頁 css 共 2 link);5 個 `public/css/*.css` 皆回 200(`/setup` 回 302 是因 seed 了 user → 0005 已設定跳轉,與 login 同用 card.css 已驗)→ 通過
- [x] 切割健檢:build 腳本確認共用/舊 brand 規則皆未殘留於 card/ledger css;ledger canonical brand 正常 render(timeline 含 spectrum-mark)→ 通過
- [x] 目視比對 → 交付使用者(class/HTML 未動,理論上完全一致)

## 執行後備註

### 實際改動檔案

- 新增 `public/css/spectrum.css`(全站共用:box-sizing/.mono/brand/.icbtn/user-menu)、`card.css`(setup/login/repository)、`overview.css`、`timeline.css`、`summary.css`
- 改 `resources/views/layouts/spectrum.blade.php`(head 加 `<link spectrum.css>`)
- 改 `setup.blade.php`、`login.blade.php`、`repository.blade.php`(`<style>` → `<link card.css>`)
- 改 `overview.blade.php`、`timeline.blade.php`、`summary.blade.php`(`<style>` → `<link {page}.css>`)
- 改 `components/brand.blade.php`(移除 inline `<style>`,純 markup)、`components/user-menu.blade.php`(移除 inline `<style>`,保留 toggle JS)

### 偏離原計畫

- 無架構偏離。一個必要的清理:**ledger 頁(overview/timeline/summary)的 css 在抽出時一併移除了殘留的舊 brand 規則**(`.brand{align-items:baseline}`/`.brand .dot`/`.brand b 19px`/`.brand span`/`.vtag`)。這些是 0001 替換 brand markup 後沒清的死 CSS,原本靠 component 後載入覆蓋;改為 spectrum.css 載入後,若保留會因 cascade 順序反咬 → 故移除,讓 canonical brand 為唯一來源。屬決策 2「移除共用選擇器」的合理延伸,非偏離設計。

### 發現的新問題或後續建議

- `card.css` 仍含目前未使用的 `.done`/`.steps`/`.summary` 等(setup 原精靈遺留樣式);不影響功能,日後可再清。
- ledger 頁 css 仍各含未使用的 `.demo`(范例資料,markup 已換成 user-menu);harmless,可清。
- overview/summary 的 `:root` token 相同但各存一份(本次刻意,使用者選各一份);未來若要再去重可抽 ledger 共用 token。
- 視覺一致性最終仍需使用者開瀏覽器逐頁確認(本次只驗到結構/載入層級)。
