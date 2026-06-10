---
created_at: <CREATED_AT>
closed_at: null
---

# Task: <task-name>

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [ ] 1. <動作 1,明確指出檔案路徑與改動內容>
  - 檔案:`app/Core/Services/{Module}/Contracts/{Module}Contract.php`(新增)
  - 內容:定義介面,包含方法簽章 `read_list(array $filters): array`
- [ ] 2. <動作 2>
  - 檔案:`app/Services/{Module}/{Module}Service.php`(新增)
  - 內容:implements `{Module}Contract`,建構子注入 `{Module}Repository`
- [ ] 3. <動作 3>
  - 檔案:
  - 內容:

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [ ] 執行 `php artisan test --filter=<相關測試類>` 確認測試通過
- [ ] 執行 `./vendor/bin/pint` 確認 coding style
- [ ] (其他驗證步驟,例如手動測試特定 endpoint)

## 執行後備註

<此區塊由 Claude 在 `/spec:run` 完成後填寫。執行前保持空白。>

### 實際改動檔案

(由 Claude 在執行後列出)

### 偏離原計畫

(若無偏離寫「無」;若有,說明哪一項任務怎麼改變、原因為何)

### 發現的新問題或後續建議

(若無寫「無」;若有,條列說明)
