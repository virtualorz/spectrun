@extends('layouts.spectrum')

@section('title', 'specflow ledger · 主數字重排')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/summary.css') }}">
@endpush

@section('content')
<div class="wrap">
  <div class="top">
    <x-brand />
    <div class="top-right"><x-user-menu /><button class="icbtn" id="theme" aria-label="切換亮暗主題">◐</button></div>
  </div>
  <div class="shell">
    <aside class="side"><div class="sidelab">專案</div><nav id="nav"></nav></aside>
    <main class="main">
  <div class="legend">
    <span>左側大數字 = 總跨度(wall-clock);細條為四階段比例,滑過顯各段時間</span>
    <span><i style="background:var(--ph1)"></i>規格</span><span><i style="background:var(--ph2)"></i>設計</span><span><i style="background:var(--ph3)"></i>實作</span>
  </div>
  <div id="list"></div>
    </main>
  </div>
</div>
@endsection

@push('scripts')
@verbatim
<script>
const ICONS = {
  check:'<svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>',
  flag:'<svg viewBox="0 0 24 24"><path d="M5 3v18M5 4h11l-2 4 2 4H5"/></svg>',
  play:'<svg viewBox="0 0 24 24"><path d="M7 4l13 8-13 8z"/></svg>'
};
function ts(start, addMin){ return new Date(new Date(start).getTime()+addMin*60000); }
function fmtTS(d){ const p=n=>String(n).padStart(2,'0');
  return p(d.getMonth()+1)+'-'+p(d.getDate())+' '+p(d.getHours())+':'+p(d.getMinutes()); }
function fmtDur(min){ if(min==null) return null;
  const h=Math.floor(min/60), m=Math.round(min%60);
  if(h>0) return h+'h '+(m>0? m+'m':''); if(min<1) return '<1m'; return m+'m'; }

// helper to build an issue compactly
function I(o){ return o; }

const PROJECTS = [
  {
    key:'campaign', name:'廣告投放平台', tech:'Laravel · PHP 8.2',
    issues:[
    I({id:'0001',slug:'init-repository-pattern',title:'導入 Repository Pattern 基礎架構',
      problem:'Controller 直接操作 Eloquent，查詢邏輯散落各處難以重用與測試。期望抽出 Repository 層，Controller 只依賴 Contract。',
      start:'2026-03-02T14:54:00+08:00',dDesign:300,dRun:240,dClose:2600,tokens:38120,dec:[3,3],task:[5,5],disc:0,
      decisions:['以 Contract + 具體實作分離，service provider 綁定','snake_case 加底線前綴給 protected method'],
      tasks:['新增 BaseRepository 抽象類','UserRepository 實作並綁定','Controller 改注入 Contract'],
      files:['app/Core/Repositories/BaseRepository.php','app/Repositories/UserRepository.php','app/Providers/RepositoryServiceProvider.php'],
      dev:'無偏離。'}),
    I({id:'0002',slug:'refactor-campaign-proxy',title:'重構 campaign proxy 快取層',
      problem:'CampaignProxyController 直接呼叫 ExternalApiClient，缺快取層，每次請求都打外部 API 造成延遲。期望同一 endpoint 5 分鐘內重複呼叫命中快取。',
      start:'2026-03-03T11:43:00+08:00',dDesign:90,dRun:15,dClose:1100,tokens:55545,dec:[4,4],task:[7,7],disc:2,
      decisions:['用 Repository 層包裝快取，Controller 不直接碰 cache facade','外部失敗時降級回傳上次快取值並記 warning log','TTL 設 300 秒，key 含 query hash','快取命中/未命中各記一筆 metric'],
      tasks:['新增 CampaignCacheRepository','包裝 ExternalApiClient 呼叫','加入降級邏輯','寫 feature 測試命中/降級兩路徑'],
      files:['app/Repositories/CampaignCacheRepository.php','app/Http/Controllers/CampaignProxyController.php','tests/Feature/CampaignCacheTest.php'],
      dev:'討論後 TTL 從 60s 調整為 300s，避免報表頁過度打外部 API。'}),
    I({id:'0003',slug:'add-report-export',title:'新增報表 CSV 匯出',
      problem:'PM 需要把投放成效報表下載成 CSV 給客戶，目前只能截圖。期望提供一鍵匯出，含 UTF-8 BOM 讓 Excel 正常顯示中文。',
      start:'2026-03-04T11:38:00+08:00',dDesign:150,dRun:120,dClose:2600,tokens:29870,dec:[3,3],task:[4,4],disc:0,
      decisions:['用 streamed response 避免大報表佔記憶體','加 UTF-8 BOM 解決 Excel 亂碼'],
      tasks:['新增 ReportExportService','streamed CSV response','補檔名時間戳'],
      files:['app/Services/Report/ReportExportService.php','app/Http/Controllers/ReportController.php'],
      dev:'無偏離。'}),
    I({id:'0004',slug:'fix-timezone-bug',title:'修正報表時區計算錯誤',
      problem:'報表日期以 UTC 切分，台灣客戶看到的「今日」少了 8 小時資料。期望全部以 Asia/Taipei 切分日界。',
      start:'2026-03-05T16:23:00+08:00',dDesign:480,dRun:60,dClose:1100,tokens:21340,dec:[2,2],task:[3,3],disc:1,
      decisions:['查詢前統一轉 app timezone 再切日界','加回歸測試鎖定跨日邊界'],
      tasks:['修正 ReportRepository 日期條件','補跨日邊界測試','回填既有快取'],
      files:['app/Repositories/ReportRepository.php','tests/Unit/ReportTimezoneTest.php'],
      dev:'討論確認 DB 存 UTC 不動，只在查詢層轉換。'}),
    I({id:'0005',slug:'add-operation-record',title:'接入 operationRecord 非同步寫入',
      problem:'缺乏操作軌跡，出問題難以追責。期望關鍵操作非同步寫入 MongoDB，不阻塞主流程。',
      start:'2026-03-09T11:04:00+08:00',dDesign:150,dRun:240,dClose:360,tokens:44210,dec:[3,3],task:[5,5],disc:1,
      decisions:['透過 Laravel Queue 非同步寫 Mongo','失敗只記 log 不影響主交易'],
      tasks:['整合 operationRecord 套件','標記需記錄的 action','設定 queue worker','補寫入失敗的 fallback'],
      files:['app/Http/Middleware/RecordOperation.php','config/queue.php','app/Jobs/WriteOperationRecord.php'],
      dev:'無偏離。'}),
    I({id:'0006',slug:'jwt-authenticator',title:'整合 JWT Authenticator 套件',
      problem:'各服務各自實作 token 驗證，邏輯重複且不一致。期望統一改用內部 jsadways JWT Authenticator。',
      start:'2026-03-10T16:07:00+08:00',dDesign:480,dRun:240,dClose:600,tokens:51030,dec:[4,4],task:[6,6],disc:2,
      decisions:['以 middleware 統一驗證，移除各 controller 手刻邏輯','refresh token 走獨立 guard','過期回 401 + 標準錯誤格式','保留舊 token 一週過渡期'],
      tasks:['安裝並設定 Authenticator','改寫 auth middleware','移除舊驗證碼','補過渡期相容','整合測試','更新 API 文件'],
      files:['app/Http/Middleware/JwtAuth.php','config/auth.php','composer.json'],
      dev:'討論後保留一週雙軌過渡，避免前端同步壓力。'}),
    I({id:'0007',slug:'scope-filter-package',title:'抽出 ScopeFilter 共用套件',
      problem:'多個 Repository 重複實作 query filter（日期、狀態、關鍵字）。期望抽成可組合的 ScopeFilter 套件給全平台共用。',
      start:'2026-03-11T10:09:00+08:00',dDesign:150,dRun:15,dClose:1800,tokens:47650,dec:[3,3],task:[5,5],disc:1,
      decisions:['以 pipeline 模式串接 filter','每個 filter 單一職責、可單測'],
      tasks:['設計 Filter 介面','實作 Date/Status/Keyword filter','改造 CampaignRepository 套用','發佈內部 composer 套件','補單元測試'],
      files:['packages/scope-filter/src/AbstractFilter.php','packages/scope-filter/src/Pipeline.php'],
      dev:'無偏離。'}),
    I({id:'0008',slug:'campaign-retry-strategy',title:'campaign 外呼 retry 策略',
      problem:'外部 campaign API 偶發 5xx 導致投放指令漏發。期望加入指數退避 retry，超過上限才告警。',
      start:'2026-03-13T10:21:00+08:00',dDesign:45,dRun:30,dClose:150,tokens:33480,dec:[3,3],task:[4,4],disc:0,
      decisions:['指數退避，最多 3 次','最終失敗寫 dead-letter queue 並通知'],
      tasks:['封裝 retry 裝飾器','設定退避參數','dead-letter 處理','補測試模擬 5xx'],
      files:['app/Services/Campaign/RetryableClient.php','app/Jobs/DispatchCampaign.php'],
      dev:'無偏離。'}),
    I({id:'0009',slug:'dashboard-cache-warmup',title:'dashboard 快取預熱排程',
      problem:'每天上班尖峰首次開 dashboard 要等 6 秒冷啟動。期望排程在上班前預熱熱門查詢快取。',
      start:'2026-03-14T09:33:00+08:00',dDesign:300,dRun:480,dClose:600,tokens:26910,dec:[2,2],task:[3,3],disc:0,
      decisions:['08:30 排程預熱前一日彙總','只預熱命中率高的查詢'],
      tasks:['新增 WarmupDashboardCache command','設定 scheduler','記錄預熱耗時 metric'],
      files:['app/Console/Commands/WarmupDashboardCache.php','app/Console/Kernel.php'],
      dev:'無偏離。'}),
    I({id:'0010',slug:'migrate-php82',title:'升級 PHP 8.2 相容性',
      problem:'生產環境準備升 PHP 8.2，現有程式碼有 dynamic property 等棄用警告。期望全面相容並通過 CI。',
      start:'2026-03-14T15:41:00+08:00',dDesign:480,dRun:15,dClose:1100,tokens:58740,dec:[4,4],task:[7,7],disc:2,
      decisions:['補上 #[AllowDynamicProperties] 或改顯式屬性','readonly property 套用於 DTO','升級相依套件鎖版本','CI 矩陣加入 8.2'],
      tasks:['掃描棄用警告','逐一修正 dynamic property','DTO 改 readonly','升級 composer 相依','調整 CI 矩陣','跑全測試','整理升級筆記'],
      files:['composer.json','app/DTO/CampaignDTO.php','.github/workflows/ci.yml'],
      dev:'兩個第三方套件未支援 8.2，討論後改用替代套件。'}),
    I({id:'0011',slug:'add-budget-alert',title:'預算超標即時告警',
      problem:'投放預算超標時往往隔天才發現，造成超支。期望即時偵測並推 Slack 告警。',
      start:'2026-03-13T09:20:00+08:00',dDesign:300,dRun:480,dClose:null,tokens:null,dec:[3,3],task:[5,3],disc:1,running:true,
      decisions:['每筆消耗事件即時比對閾值','超標推 Slack + 標記 campaign 暫停建議'],
      tasks:['新增 BudgetMonitor service','閾值設定 UI','Slack webhook 整合','即時事件觸發','告警去重節流'],
      files:['app/Services/Budget/BudgetMonitor.php (新增)'],
      dev:''})
    ]
  },
  {
    key:'kol', name:'KOL 管理系統', tech:'Laravel · MySQL',
    issues:[
    I({id:'0001',slug:'init-kol-schema',title:'建立 KOL 資料表結構',
      problem:'KOL 資料目前散在 Excel，無法查詢與關聯。期望建立正規化的資料表結構作為系統基礎。',
      start:'2026-02-10T14:33:00+08:00',dDesign:150,dRun:480,dClose:1100,tokens:41200,dec:[3,3],task:[6,6],disc:1,
      decisions:['核心 kols 表 + 多張關聯子表','銀行帳號獨立表加密儲存'],
      tasks:['設計 10 張 migration','外鍵與索引','建立 model 關聯','seeder 假資料','補 schema 文件','跑 migrate 驗證'],
      files:['database/migrations/create_kols_table.php','app/Models/Kol.php'],
      dev:'討論後將社群帳號與標籤拆成獨立關聯表。'}),
    I({id:'0002',slug:'kol-social-profiles',title:'KOL 社群帳號管理',
      problem:'一位 KOL 可能有多個社群平台帳號與粉絲數，需集中管理並可更新。期望支援多平台帳號 CRUD。',
      start:'2026-02-11T11:11:00+08:00',dDesign:900,dRun:60,dClose:1800,tokens:28430,dec:[2,2],task:[4,4],disc:0,
      decisions:['platform enum 限定支援平台','粉絲數記錄歷史快照'],
      tasks:['social_profiles CRUD','platform 驗證','粉絲數快照表','補測試'],
      files:['app/Models/KolSocialProfile.php','app/Http/Controllers/SocialProfileController.php'],
      dev:'無偏離。'}),
    I({id:'0003',slug:'tax-withholding',title:'所得稅 10% 代扣計算',
      problem:'付款給 KOL 須依法代扣所得稅，目前人工算易錯。期望系統自動計算代扣稅額。',
      start:'2026-02-12T10:20:00+08:00',dDesign:1500,dRun:480,dClose:2600,tokens:39870,dec:[3,3],task:[5,5],disc:2,
      decisions:['依扣繳起扣點判斷是否代扣','稅額計算抽成 TaxCalculator service','保留計算明細供稽核'],
      tasks:['實作 TaxCalculator','起扣點判斷','金額四捨五入規則','寫稅務測試案例','明細記錄'],
      files:['app/Services/Tax/TaxCalculator.php','tests/Unit/TaxCalculatorTest.php'],
      dev:'討論確認未達起扣點不代扣，並補上邊界測試。'}),
    I({id:'0004',slug:'nhi-supplement',title:'二代健保補充保費計算',
      problem:'單次給付達門檻須扣二代健保補充保費，規則與所得稅不同。期望獨立計算且可與所得稅併用。',
      start:'2026-02-14T09:34:00+08:00',dDesign:300,dRun:30,dClose:360,tokens:42560,dec:[3,3],task:[5,5],disc:1,
      decisions:['獨立 NhiCalculator 與稅務分離','達門檻才計費率','與所得稅各自記明細'],
      tasks:['實作 NhiCalculator','門檻與費率設定','整合付款計算','補測試','明細欄位'],
      files:['app/Services/Tax/NhiCalculator.php','app/Services/Payment/PaymentCalculator.php'],
      dev:'無偏離。'}),
    I({id:'0005',slug:'project-financials',title:'專案財務拆帳',
      problem:'一個合作專案常分多位 KOL 與多筆款項，財務難對帳。期望以專案為單位彙整財務並可拆帳。',
      start:'2026-02-15T10:09:00+08:00',dDesign:300,dRun:15,dClose:2600,tokens:49230,dec:[4,4],task:[6,6],disc:2,
      decisions:['project_financials 彙總表','拆帳明細關聯 KOL','金額一致性以 DB 交易保證','提供對帳檢視'],
      tasks:['財務彙總表','拆帳邏輯','一致性交易包裹','對帳頁面','補測試','文件'],
      files:['app/Models/KolProjectFinancial.php','app/Services/Finance/SplitService.php'],
      dev:'討論後加入金額加總一致性檢查避免拆帳誤差。'}),
    I({id:'0006',slug:'payment-batch',title:'付款批次產生',
      problem:'每月付款逐筆處理太慢。期望把當期應付彙整成付款批次一次處理。',
      start:'2026-02-17T09:12:00+08:00',dDesign:150,dRun:15,dClose:150,tokens:45120,dec:[3,3],task:[5,5],disc:1,
      decisions:['批次內含多筆 payment，狀態機管控','產生後鎖定避免重複付'],
      tasks:['payment_batches 表','批次產生邏輯','狀態機','鎖定機制','匯出銀行格式'],
      files:['app/Models/KolPaymentBatch.php','app/Services/Payment/BatchService.php'],
      dev:'無偏離。'}),
    I({id:'0007',slug:'attachment-upload',title:'KOL 合約附件上傳',
      problem:'合約 PDF 散落同事電腦，找不到也無版本。期望上傳到系統並與 KOL 關聯保存。',
      start:'2026-02-19T10:51:00+08:00',dDesign:480,dRun:15,dClose:2600,tokens:25640,dec:[2,2],task:[4,4],disc:0,
      decisions:['檔案存私有 bucket，DB 存 metadata','下載走 signed URL'],
      tasks:['attachments 表','上傳處理','signed URL 下載','檔案型別驗證'],
      files:['app/Models/KolAttachment.php','app/Services/Storage/AttachmentService.php'],
      dev:'無偏離。'}),
    I({id:'0008',slug:'tag-assignment',title:'KOL 標籤分類系統',
      problem:'要依領域、合作層級快速篩選 KOL，目前只能逐筆看。期望支援多標籤分類與篩選。',
      start:'2026-02-22T09:15:00+08:00',dDesign:90,dRun:30,dClose:2600,tokens:30210,dec:[3,3],task:[4,4],disc:1,
      decisions:['多對多 tag assignment','標籤可分群（領域/層級）'],
      tasks:['tags 與 assignment 表','標籤 CRUD','篩選查詢套 ScopeFilter','補測試'],
      files:['app/Models/KolTag.php','app/Repositories/KolRepository.php'],
      dev:'討論後標籤加入群組欄位以便分類顯示。'}),
    I({id:'0009',slug:'import-legacy-excel',title:'匯入舊系統 Excel 資料',
      problem:'要把歷史 Excel 名單一次匯入系統，格式雜亂多版本。期望容錯匯入並回報錯誤行。',
      start:'2026-02-24T10:04:00+08:00',dDesign:900,dRun:15,dClose:1800,tokens:53310,dec:[4,4],task:[6,6],disc:2,
      decisions:['以 queue 分批匯入避免逾時','逐行驗證，錯誤行彙整回報','重複資料以 email 去重','匯入結果產報告'],
      tasks:['Excel 解析','逐行驗證','去重邏輯','分批 queue','錯誤報告','補測試'],
      files:['app/Jobs/ImportKolExcel.php','app/Services/Import/RowValidator.php'],
      dev:'討論後改用 queue 分批，並回傳可下載的錯誤報告。'}),
    I({id:'0010',slug:'audit-log',title:'操作稽核記錄',
      problem:'財務相關操作須留痕以利稽核。期望記錄誰在何時改了什麼。',
      start:'2026-02-25T10:35:00+08:00',dDesign:150,dRun:480,dClose:1800,tokens:34780,dec:[3,3],task:[4,4],disc:0,
      decisions:['以 model observer 記錄變更前後值','稽核資料唯讀不可改'],
      tasks:['audit_logs 表','observer 掛載','變更 diff 記錄','稽核檢視頁'],
      files:['app/Observers/AuditObserver.php','app/Models/AuditLog.php'],
      dev:'無偏離。'}),
    I({id:'0011',slug:'kol-payment-report',title:'月結付款報表',
      problem:'每月要產出含稅額、健保、實付的月結報表給財務。期望系統自動彙整匯出。',
      start:'2026-02-24T11:20:00+08:00',dDesign:45,dRun:15,dClose:null,tokens:null,dec:[3,3],task:[5,2],disc:1,running:true,
      decisions:['以付款批次為來源彙整月結','含稅/健保/實付三欄拆分'],
      tasks:['月結彙整查詢','報表欄位設計','Excel 匯出','權限控管','補測試'],
      files:['app/Services/Report/MonthlyPayrollReport.php (新增)'],
      dev:''})
    ]
  },
  {
    key:'neijin', name:'正念台灣', tech:'Laravel · Inertia · React',
    issues:[
    I({id:'0001',slug:'init-inertia-react',title:'建立 Inertia + React 骨架',
      problem:'要做一個前後端整合但不分離的 App，需先立好骨架。期望 Laravel + Inertia + React 可跑起首頁。',
      start:'2026-01-15T14:13:00+08:00',dDesign:45,dRun:30,dClose:360,tokens:36420,dec:[3,3],task:[5,5],disc:0,
      decisions:['Inertia 取代傳統 API + SPA','React 元件以頁面為單位組織','Vite 打包'],
      tasks:['安裝 Inertia 與 Vite','設定 React 入口','建立 layout','首頁頁面','跑通 SSR 設定'],
      files:['resources/js/app.jsx','app/Http/Middleware/HandleInertiaRequests.php'],
      dev:'無偏離。'}),
    I({id:'0002',slug:'sms-otp-twilio',title:'Twilio Verify 簡訊 OTP',
      problem:'註冊需手機驗證，台灣門號要能收到簡訊。期望串 Twilio Verify 完成 OTP 流程並實測送達台灣。',
      start:'2026-01-18T11:34:00+08:00',dDesign:150,dRun:480,dClose:2600,tokens:47210,dec:[4,4],task:[6,6],disc:2,
      decisions:['用 Twilio Verify 服務而非自管 OTP','號碼正規化為 E.164','驗證失敗次數節流','OTP 不落地只存 verify sid'],
      tasks:['整合 Twilio SDK','號碼正規化','送出與驗證 endpoint','節流中介層','實測台灣門號','補測試'],
      files:['app/Services/Otp/TwilioVerifyService.php','app/Http/Controllers/Auth/OtpController.php'],
      dev:'實測送達台灣門號成功；討論後加入失敗節流防濫用。'}),
    I({id:'0003',slug:'meditation-player',title:'引導冥想播放器',
      problem:'核心功能是播放引導冥想音檔，需可背景播放與進度記錄。期望做出穩定的播放器元件。',
      start:'2026-01-18T09:01:00+08:00',dDesign:300,dRun:15,dClose:150,tokens:54330,dec:[4,4],task:[6,6],disc:2,
      decisions:['以 HTML5 audio + React 狀態封裝','背景播放與鎖屏控制','進度節流寫回後端','緩衝與錯誤重試'],
      tasks:['播放器元件','播放狀態管理','鎖屏媒體控制','進度回寫','錯誤重試','補測試'],
      files:['resources/js/Components/Player.jsx','resources/js/hooks/useAudio.js'],
      dev:'討論後進度改為每 15 秒節流寫回，降低請求量。'}),
    I({id:'0004',slug:'tts-integration',title:'ATEN 優聲學 TTS 串接',
      problem:'引導語要能用自然中文語音生成，省去人工錄音。期望串接 ATEN 優聲學 TTS 產生音檔。',
      start:'2026-01-20T09:46:00+08:00',dDesign:150,dRun:30,dClose:150,tokens:44120,dec:[3,3],task:[5,5],disc:1,
      decisions:['後台輸入文本，非同步產生音檔','音檔存私有儲存供播放器取用'],
      tasks:['TTS API 串接','文本轉語音 job','音檔儲存','後台預覽','補測試'],
      files:['app/Services/Tts/AtenTtsService.php','app/Jobs/GenerateNarration.php'],
      dev:'無偏離。'}),
    I({id:'0005',slug:'ambient-audio',title:'Pixabay 環境音整合',
      problem:'冥想需要可疊加的環境音（雨聲、海浪）。期望提供環境音清單並可與引導語混音播放。',
      start:'2026-01-23T09:14:00+08:00',dDesign:480,dRun:60,dClose:1100,tokens:27640,dec:[2,2],task:[4,4],disc:0,
      decisions:['環境音獨立音軌可調音量','來源標註 Pixabay 授權'],
      tasks:['環境音清單','雙音軌混音','音量控制 UI','授權標註'],
      files:['resources/js/Components/AmbientMixer.jsx'],
      dev:'無偏離。'}),
    I({id:'0006',slug:'user-onboarding',title:'新用戶引導流程',
      problem:'新用戶不知從何開始，留存差。期望首次登入引導選擇目標與推薦課程。',
      start:'2026-01-23T10:17:00+08:00',dDesign:90,dRun:30,dClose:2600,tokens:31280,dec:[3,3],task:[4,4],disc:1,
      decisions:['三步引導，可跳過','依目標推薦起始課程'],
      tasks:['引導步驟元件','目標選擇','推薦邏輯','完成狀態記錄'],
      files:['resources/js/Pages/Onboarding.jsx','app/Services/Recommend/StarterRecommender.php'],
      dev:'討論後引導允許跳過，避免阻擋老用戶。'}),
    I({id:'0007',slug:'subscription-tiers',title:'訂閱方案分級',
      problem:'要區分免費與付費內容並導入訂閱。期望建立方案分級與權限控管。',
      start:'2026-01-24T10:37:00+08:00',dDesign:45,dRun:15,dClose:2600,tokens:50870,dec:[4,4],task:[6,6],disc:2,
      decisions:['免費/月費/年費三級','以 policy 控管內容存取','到期降級為免費','保留試用期'],
      tasks:['方案資料表','權限 policy','內容存取守門','到期降級排程','試用期邏輯','補測試'],
      files:['app/Models/Subscription.php','app/Policies/ContentPolicy.php'],
      dev:'討論後加入七天試用期提升轉換。'}),
    I({id:'0008',slug:'daily-streak',title:'每日連續打卡',
      problem:'缺乏持續動機。期望以連續打卡天數鼓勵每日練習。',
      start:'2026-01-24T09:09:00+08:00',dDesign:300,dRun:30,dClose:1100,tokens:24980,dec:[2,2],task:[3,3],disc:0,
      decisions:['以 app timezone 判定每日邊界','斷掉重新計算不溯往'],
      tasks:['streak 計算','每日邊界判定','顯示元件'],
      files:['app/Services/Streak/StreakService.php','resources/js/Components/StreakBadge.jsx'],
      dev:'無偏離。'}),
    I({id:'0009',slug:'push-notification',title:'推播提醒',
      problem:'用戶設定的練習時間到了沒提醒。期望支援每日推播提醒。',
      start:'2026-01-26T10:30:00+08:00',dDesign:90,dRun:15,dClose:150,tokens:38450,dec:[3,3],task:[5,5],disc:1,
      decisions:['用戶可設提醒時間','排程依時區推播','可關閉'],
      tasks:['提醒設定 UI','排程推播 job','時區處理','推播 token 管理','補測試'],
      files:['app/Jobs/SendReminderPush.php','resources/js/Pages/Settings/Reminder.jsx'],
      dev:'無偏離。'}),
    I({id:'0010',slug:'offline-cache',title:'離線音檔快取',
      problem:'通勤無網路時無法播放。期望已下載課程可離線播放。',
      start:'2026-01-26T16:28:00+08:00',dDesign:45,dRun:480,dClose:1800,tokens:57920,dec:[4,4],task:[7,7],disc:2,
      decisions:['以 service worker 快取音檔','下載管理與容量上限','過期清理策略','離線狀態 UI 提示'],
      tasks:['service worker 設定','下載管理','容量控管','過期清理','離線提示','播放器離線判斷','補測試'],
      files:['resources/js/sw.js','resources/js/services/DownloadManager.js'],
      dev:'討論後加入容量上限與 LRU 過期清理。'}),
    I({id:'0011',slug:'session-history',title:'冥想紀錄統計頁',
      problem:'用戶看不到自己累積的練習成果。期望提供歷程統計（總時數、連續天數、分類分布）。',
      start:'2026-01-25T14:20:00+08:00',dDesign:900,dRun:15,dClose:null,tokens:null,dec:[3,3],task:[5,3],disc:1,running:true,
      decisions:['彙總總時數與連續天數','分類分布以圖呈現'],
      tasks:['歷程彙總查詢','統計頁面','分布圖元件','空狀態設計','補測試'],
      files:['resources/js/Pages/History.jsx (新增)'],
      dev:''})
    ]
  }
];

/* ---- shared helpers (all three views) ---- */
function fmtTok(n){ if(n==null) return '—';
  if(n>=1000000) return (n/1000000).toFixed(2)+'M';
  return n.toLocaleString('en-US'); }
function fmtDate(d){ const p=n=>String(n).padStart(2,'0'); return p(d.getMonth()+1)+'-'+p(d.getDate()); }
function humanSpan(min){ if(min==null) return null;
  const d=Math.floor(min/1440), h=Math.floor((min%1440)/60), m=Math.round(min%60);
  if(d>=1) return d+'d'+(h? ' '+h+'h':'');
  if(h>=1) return h+'h'+(m? ' '+m+'m':'');
  return m+'m'; }
function computeDurations(it){
  const tNew=new Date(it.start);
  const tDesign=ts(it.start, it.dDesign);
  const tRun=it.dRun!=null ? ts(it.start, it.dDesign+it.dRun) : null;
  const tClose=(it.dRun!=null && it.dClose!=null) ? ts(it.start, it.dDesign+it.dRun+it.dClose) : null;
  const total=tClose ? Math.round((tClose-tNew)/60000) : null;
  // elapsed-so-far for running (new -> run start)
  const elapsed = (!tClose && tRun) ? Math.round((tRun-tNew)/60000) : total;
  return {tNew,tDesign,tRun,tClose,total,elapsed,
    pSpec:it.dDesign, pDesign:it.dRun, pImpl:it.dClose};
}
function projSummary(p){
  const closed=p.issues.filter(x=>!x.running);
  const totalTok=closed.reduce((s,x)=>s+(x.tokens||0),0);
  const totalSpan=closed.reduce((s,x)=>s+(computeDurations(x).total||0),0);
  let last=null; p.issues.forEach(x=>{const c=computeDurations(x).tClose; if(c&&(!last||c>last)) last=c;});
  const running=p.issues.filter(x=>x.running).length;
  return {closedN:closed.length,totalN:p.issues.length,runningN:running,
    totalTok,totalSpan,last,avgSpan:closed.length?Math.round(totalSpan/closed.length):0};
}
const SVG = {
  box:'<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 12l3 3 5-6"/></svg>',
  boxe:'<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>',
  list:'<svg viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>',
  chat:'<svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.5 7.2L4 21l1.8-5.5A8 8 0 1 1 21 12z"/></svg>',
  clock:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
  branch:'<svg viewBox="0 0 24 24"><circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="8" r="2.5"/><path d="M6 8.5v7M8.5 7.5c6 1 9 2 9 6"/></svg>',
  coin:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2 0 0 1 5 0c0 2.5-5 1.5-5 4a2.5 2 0 0 0 5 0"/></svg>'
};
// three wall-clock phases: spec writing / design review / implementation
function segmentBar(it, h){
  const d=computeDurations(it);
  if(it.running){
    const tot=(d.pSpec+d.pDesign)||1;
    const w1=d.pSpec/tot*100, w2=d.pDesign/tot*100;
    return `<div class="segbar" style="height:${h}px">
      <span class="sg sg1" style="flex:${w1}"></span>
      <span class="sg sg2" style="flex:${w2}"></span>
      <span class="sg sg-open" title="實作中"></span></div>`;
  }
  const tot=(d.pSpec+d.pDesign+d.pImpl)||1;
  return `<div class="segbar" style="height:${h}px">
    <span class="sg sg1" style="flex:${d.pSpec}" title="規格 ${humanSpan(d.pSpec)}"></span>
    <span class="sg sg2" style="flex:${d.pDesign}" title="設計 ${humanSpan(d.pDesign)}"></span>
    <span class="sg sg3" style="flex:${d.pImpl}" title="實作 ${humanSpan(d.pImpl)}"></span></div>`;
}
function detailHTML(it){
  const decDone=it.dec[1], taskDone=it.task[1];
  const decItems=it.decisions.map((t,i)=>{const done=i<decDone;
    return `<div class="citem"><span class="ck ${done?'done':'todo'}">${done?SVG.box:SVG.boxe}</span>${t}</div>`;}).join('');
  const taskItems=it.tasks.map((t,i)=>{const done=i<taskDone;
    return `<div class="citem"><span class="ck ${done?'done':'todo'}">${done?SVG.box:SVG.boxe}</span>${i+1}. ${t}</div>`;}).join('');
  const filesHtml=it.files.map(f=>`<span class="fpath">${f}</span>`).join('');
  const execSec=it.running
    ? `<div class="dsec"><div class="dlab">目前進度</div><div class="dprose">執行中,已完成 ${taskDone}/${it.task[0]} 項任務。close 尚未執行,token 與總跨度待結算。</div></div>`
    : `<div class="dsec"><div class="dlab">執行後備註 — 實際改動檔案</div><div class="files">${filesHtml}</div></div>
       <div class="dsec"><div class="dlab">偏離原計畫</div><div class="note">${it.dev||'無'}</div></div>`;
  return `<div class="dsec"><div class="dlab">想解決的問題</div><div class="dprose">${it.problem}</div></div>
    <div class="dsec"><div class="dlab">設計決策 · ${decDone}/${it.dec[0]}</div><div class="clist">${decItems}</div></div>
    <div class="dsec"><div class="dlab">執行清單 · ${taskDone}/${it.task[0]}</div><div class="clist">${taskItems}</div></div>
    ${execSec}`;
}

let cur=0; const openSet=new Set();

function renderNav(){
  document.getElementById('nav').innerHTML=PROJECTS.map((p,i)=>
    `<button class="navitem ${i===cur?'active':''}" data-i="${i}"><span class="nm">${p.name}</span><span class="cnt">${p.issues.length}</span></button>`).join('');
  document.querySelectorAll('.navitem').forEach(b=>b.onclick=()=>{cur=+b.dataset.i;openSet.clear();render();});
}

function segDetail(it){
  const d=computeDurations(it);
  const row=(c,lab,min)=>`<span class="sb"><i style="background:${c}"></i>${lab} ${humanSpan(min)||'—'}</span>`;
  if(it.running) return `<div class="stagebreak">${row('var(--ph1)','規格',d.pSpec)}${row('var(--ph2)','設計',d.pDesign)}<span class="sb"><i style="background:var(--run)"></i>實作 進行中</span></div>`;
  return `<div class="stagebreak">${row('var(--ph1)','規格',d.pSpec)}${row('var(--ph2)','設計',d.pDesign)}${row('var(--ph3)','實作',d.pImpl)}</div>`;
}

function card(it){
  const d=computeDurations(it);
  const isOpen=openSet.has(it.id);
  const hero=it.running
    ? `<div class="big run">進行中</div><div class="unit">已經過 ${humanSpan(d.elapsed)}</div><div class="range">${fmtDate(d.tNew)} 開始</div>`
    : `<div class="big">${humanSpan(d.total)}</div><div class="unit">總跨度</div><div class="range">${fmtDate(d.tNew)} → ${fmtDate(d.tClose)}</div>`;
  return `<div class="issue ${isOpen?'open':''}">
    <button class="ihead" data-id="${it.id}" aria-expanded="${isOpen}">
      <div class="hero">${hero}</div>
      <div class="mbody">
        <div class="mrow1"><div class="iid"><b>${it.id}</b> · ${it.slug}</div>
          <span class="badge ${it.running?'running':'closed'}">${it.running?'執行中':'已完成'}</span></div>
        <div class="ititle">${it.title}</div>
        <div class="segwrap">${segmentBar(it,8)}<span class="seghint">${it.running?'3 段中':'new → close'}</span></div>
        <div class="meta">
          <span class="chip mono">${SVG.coin}${it.tokens?it.tokens.toLocaleString('en-US'):'未結算'} tok</span>
          <span class="chip">${SVG.box}決策 ${it.dec[1]}/${it.dec[0]}</span>
          <span class="chip">${SVG.list}任務 ${it.task[1]}/${it.task[0]}</span>
          <span class="chip">${SVG.chat}討論 ${it.disc}</span>
        </div>
      </div>
    </button>
    <div class="detail">
      <div class="dsec"><div class="dlab">四階段拆解(wall-clock)</div>${segDetail(it)}</div>
      ${detailHTML(it)}
    </div>
  </div>`;
}

function render(){
  renderNav();
  const p=PROJECTS[cur];
  const list=[...p.issues].sort((a,b)=>a.id.localeCompare(b.id));
  document.getElementById('list').innerHTML=list.map(card).join('');
  document.querySelectorAll('.ihead').forEach(b=>b.onclick=()=>{const id=b.dataset.id;openSet.has(id)?openSet.delete(id):openSet.add(id);render();});
}

render();
</script>
@endverbatim
@endpush
