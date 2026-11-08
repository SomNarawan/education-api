# Student Import Template Master Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Require a curriculum before downloading the student import template, populate the workbook with curriculum-aware master-data dropdowns, and allow imports without an advisor.

**Architecture:** The frontend sends the selected `curriculum_id` to the existing download endpoint. Laravel validates it, `StudentImportService` loads the same list-of-value sources as the create-student popup, creates a visible `Master Data` sheet, and delegates low-level OOXML named-range/data-validation insertion to a focused helper. The existing import resolver remains authoritative, with advisor fields changed from required to nullable.

**Tech Stack:** Laravel 12, PHP 8.2, PHPUnit 11, SimpleXLSX/SimpleXLSXGen, ZipArchive/OOXML, React 19, TypeScript 6, Ant Design 6

**Spec:** `docs/superpowers/specs/2026-11-06-student-import-template-master-data-design.md`

## Global Constraints

- Keep the existing 19-column `Sheet1` layout and all headers except removing the required marker from advisor.
- Do not add a spreadsheet dependency; use the installed ZipArchive extension to add OOXML data validations after SimpleXLSXGen saves the workbook.
- Populate dropdowns for rows 3 through 1002 at C, K, L, M, N, Q, and S.
- Use the same active/global and curriculum-personnel sources as `ListOfValueService` and the create-student popup.
- Keep department, curriculum, study plan, and international-program selection outside the workbook.

## Review Focus

- Missing or non-positive `curriculum_id` must return HTTP 422 and must not create a template; covered by Task 1 request tests.
- A curriculum with no eligible personnel must still produce an openable workbook and allow a blank advisor; covered by Task 2 workbook tests.
- An empty optional master-data list must not create an invalid named range; covered by Task 2 workbook tests.
- Master-data labels containing `&`, `<`, quotes, or Thai text must survive workbook XML generation and parsing; covered by Task 2 workbook tests.
- An import row with a blank advisor must map both advisor attributes to `null` and pass advisor validation; covered by Task 3 service tests.

---

### Task 1: Curriculum-aware template download contract

**Files:**
- Create: `app/Http/Requests/Student/DownloadStudentImportTemplateRequest.php`
- Modify: `app/Http/Controllers/Api/StudentImportController.php`
- Test: `tests/Feature/StudentImportTemplateDownloadTest.php`

**Interfaces:**
- Consumes: query parameter `curriculum_id`
- Produces: `DownloadStudentImportTemplateRequest::rules(): array`; controller call `StudentImportService::createTemplate(int $curriculumId): string`

- [ ] **Step 1: Write failing endpoint tests**

Add tests named `test_template_download_requires_curriculum_id`, `test_template_download_rejects_non_positive_curriculum_id`, and `test_template_download_passes_curriculum_id_to_service`. Bypass authentication middleware, bind a mock `StudentImportService`, and assert 422 for missing/invalid input plus an exact `createTemplate(123)` call for valid input.

- [ ] **Step 2: Run the focused tests and verify they fail**

Run: `php artisan test tests/Feature/StudentImportTemplateDownloadTest.php`

Expected: FAIL because the endpoint does not validate or forward `curriculum_id`.

- [ ] **Step 3: Add the request contract**

Implement `DownloadStudentImportTemplateRequest::rules(): array` with `curriculum_id => ['required', 'integer', 'min:1']` and Thai validation messages consistent with `ImportStudentsRequest`.

- [ ] **Step 4: Forward the validated curriculum**

Change `StudentImportController::downloadTemplate(DownloadStudentImportTemplateRequest $request, StudentImportService $studentImport): BinaryFileResponse` to call `createTemplate((int) $request->validated('curriculum_id'))`.

- [ ] **Step 5: Run the focused tests and verify they pass**

Run: `php artisan test tests/Feature/StudentImportTemplateDownloadTest.php`

Expected: PASS.

- [ ] **Step 6: Commit the endpoint contract**

```bash
git add app/Http/Requests/Student/DownloadStudentImportTemplateRequest.php app/Http/Controllers/Api/StudentImportController.php tests/Feature/StudentImportTemplateDownloadTest.php
git commit -m "feat: require curriculum for student import template"
```

### Task 2: Master Data sheet and Excel dropdowns

**Files:**
- Create: `app/Services/Excel/XlsxDataValidationWriter.php`
- Modify: `app/Services/Students/StudentImportService.php`
- Test: `tests/Unit/Services/Students/StudentImportTemplateTest.php`

**Interfaces:**
- Consumes: `StudentImportService::createTemplate(int $curriculumId): string` and `ListOfValueService::get(ListOfValueType $type, array $filters = []): Collection`
- Produces: `XlsxDataValidationWriter::apply(string $path, array $namedRanges, array $validations): void`; `namedRanges` maps Excel-safe names to absolute formulas such as `'Master Data'!$A$2:$A$8`, `validations` maps `sqref` ranges such as `C3:C1002` to a named range, and the resulting workbook has sheets `Sheet1` and `Master Data`

- [ ] **Step 1: Write failing workbook tests**

Mock `ListOfValueService` for titles, admission channels, high schools, relationships, student statuses, and `curriculum-personnel` filtered by curriculum 123. Assert that SimpleXLSX reads both sheets and the expected Thai display labels, while ZipArchive inspection finds workbook defined names and list validations for `C3:C1002`, `K3:K1002`, `L3:L1002`, `M3:M1002`, `N3:N1002`, `Q3:Q1002`, and `S3:S1002`.

- [ ] **Step 2: Add edge-case workbook tests**

Add cases for no advisors, an empty optional list, and labels containing `&`, `<`, quotes, and Thai text. Assert the file opens, the relevant named range points to at least its reserved blank row, and parsed cell text round-trips unchanged.

- [ ] **Step 3: Run the workbook tests and verify they fail**

Run: `php artisan test tests/Unit/Services/Students/StudentImportTemplateTest.php`

Expected: FAIL because `Master Data`, named ranges, and validations do not exist.

- [ ] **Step 4: Implement the OOXML validation helper**

Implement `XlsxDataValidationWriter::apply(string $path, array $namedRanges, array $validations): void`. Open the `.xlsx` with ZipArchive, insert escaped workbook-level `<definedNames>` before `<calcPr>` or `</workbook>`, insert list validations with `allowBlank="1"`, `showErrorMessage="1"`, and `<formula1>range_name</formula1>` before `<pageMargins>` or `</worksheet>` in `xl/worksheets/sheet1.xml`, and throw `RuntimeException` when the archive or required XML entries cannot be read or replaced.

- [ ] **Step 5: Generate the Master Data sheet**

Change `StudentImportService::createTemplate(int $curriculumId): string` to fetch the six option sets, use `name_th` display values, add `Master Data` with one labeled column per set, reserve row 2 for empty sets, save the workbook, and call `XlsxDataValidationWriter::apply(...)` with stable Excel-safe names such as `student_titles` and `student_advisors`.

- [ ] **Step 6: Run the workbook tests and verify they pass**

Run: `php artisan test tests/Unit/Services/Students/StudentImportTemplateTest.php`

Expected: PASS.

- [ ] **Step 7: Commit workbook generation**

```bash
git add app/Services/Excel/XlsxDataValidationWriter.php app/Services/Students/StudentImportService.php tests/Unit/Services/Students/StudentImportTemplateTest.php
git commit -m "feat: add master data dropdowns to student template"
```

### Task 3: Optional advisor during student import

**Files:**
- Modify: `app/Services/Students/StudentImportService.php`
- Test: `tests/Unit/Services/Students/StudentImportAdvisorTest.php`

**Interfaces:**
- Consumes: the existing row-to-attributes resolver and student validation rules
- Produces: blank advisor cells map to `teacher_id = null` and `teacher_full_name = null`; both rules are `nullable|string|max:*`

- [ ] **Step 1: Write failing optional-advisor tests**

Add `test_blank_advisor_maps_to_null_and_passes_validation` as a full `StudentImportService::import(...)` test. Create the minimal SQLite tables required by the import validator, seed active import/master rows, build a one-row source workbook with a blank K cell, mock `StudentStudyPlanService::fetch()`, and assert `SaveStudent::createFromImport()` receives both advisor attributes as `null` and the import reports one success. Add `test_template_does_not_mark_advisor_as_required` by generating the template and asserting parsed header K is exactly `อาจารย์ที่ปรึกษา` without `*`.

- [ ] **Step 2: Run the focused tests and verify they fail**

Run: `php artisan test tests/Unit/Services/Students/StudentImportAdvisorTest.php`

Expected: FAIL because both advisor attributes are required and header K has `*`.

- [ ] **Step 3: Make advisor optional**

Remove index 10 from `REQUIRED_HEADER_INDEXES`, change `teacher_id` to `['nullable', 'string', 'max:50']`, and change `teacher_full_name` to `['nullable', 'string', 'max:255']`. Preserve existing lookup and ambiguity errors whenever column K is nonblank.

- [ ] **Step 4: Run advisor and workbook tests**

Run: `php artisan test tests/Unit/Services/Students/StudentImportAdvisorTest.php tests/Unit/Services/Students/StudentImportTemplateTest.php`

Expected: PASS.

- [ ] **Step 5: Commit optional-advisor behavior**

```bash
git add app/Services/Students/StudentImportService.php tests/Unit/Services/Students/StudentImportAdvisorTest.php
git commit -m "fix: allow student imports without advisor"
```

### Task 4: Frontend curriculum selection and download query

**Files:**
- Modify: `C:/xampp/htdocs/education-web/src/services/studentImportService.ts`
- Modify: `C:/xampp/htdocs/education-web/src/features/students/import/StudentImportPicker.tsx`

**Interfaces:**
- Consumes: selected `curriculumId` from `StudentImportPicker` form state
- Produces: `downloadStudentImportTemplate(curriculumId: number): Promise<StudentImportDownload>` issuing `GET /students/import/template` with `{ params: { curriculum_id: curriculumId } }`

- [ ] **Step 1: Create a compile-time failing call contract**

Change the service signature to require `curriculumId: number` and pass it as `curriculum_id`; run the frontend build before updating the caller.

- [ ] **Step 2: Verify the frontend build fails at the old caller**

Run from `C:/xampp/htdocs/education-web`: `npm run build`

Expected: FAIL because `StudentImportPicker` calls `downloadStudentImportTemplate()` without an argument.

- [ ] **Step 3: Require curriculum selection before download**

In `handleDownloadTemplate`, call `selectionForm.validateFields(['curriculumId'])`, pass the validated ID to the service, and leave validation errors on the form without sending a request. Disable the download button while `selectedCurriculumId` is absent, importing, or downloading.

- [ ] **Step 4: Verify frontend type-check and lint**

Run from `C:/xampp/htdocs/education-web`: `npm run build` and `npm run lint`

Expected: both exit 0.

- [ ] **Step 5: Commit frontend behavior**

```bash
git -C C:/xampp/htdocs/education-web add src/services/studentImportService.ts src/features/students/import/StudentImportPicker.tsx
git -C C:/xampp/htdocs/education-web commit -m "feat: scope student template to curriculum"
```

### Task 5: Documentation and full verification

**Files:**
- Modify: `README.md`
- Modify: `C:/xampp/htdocs/education-web/README.md` only if its API workflow is documented there

**Interfaces:**
- Consumes: completed backend and frontend contracts
- Produces: documented `GET /api/students/import/template?curriculum_id={id}` behavior and optional advisor field

- [ ] **Step 1: Update import documentation**

Document that template download requires a selected curriculum, that the generated workbook contains live master-data dropdowns, and that advisor is optional during import.

- [ ] **Step 2: Run backend formatting and targeted tests**

Run: `vendor/bin/pint --test app/Http/Requests/Student/DownloadStudentImportTemplateRequest.php app/Http/Controllers/Api/StudentImportController.php app/Services/Excel/XlsxDataValidationWriter.php app/Services/Students/StudentImportService.php tests/Feature/StudentImportTemplateDownloadTest.php tests/Unit/Services/Students/StudentImportTemplateTest.php tests/Unit/Services/Students/StudentImportAdvisorTest.php`

Run: `php artisan test tests/Feature/StudentImportTemplateDownloadTest.php tests/Unit/Services/Students/StudentImportTemplateTest.php tests/Unit/Services/Students/StudentImportAdvisorTest.php`

Expected: formatting check and tests exit 0.

- [ ] **Step 3: Run the complete backend and frontend regression checks**

Run from `C:/xampp/htdocs/education-api`: `php artisan test`

Run from `C:/xampp/htdocs/education-web`: `npm run build` and `npm run lint`

Expected: all commands exit 0; document any unrelated pre-existing failure separately.

- [ ] **Step 4: Manually inspect a generated workbook**

Generate a template for a curriculum with advisors, open it in Excel or LibreOffice, confirm `Master Data` is visible, and verify each dropdown works through row 1002. Repeat with a curriculum that has no advisors and confirm column K may remain blank.

- [ ] **Step 5: Commit documentation**

```bash
git add README.md docs/superpowers/specs/2026-11-06-student-import-template-master-data-design.md docs/superpowers/plans/2026-11-06-student-import-template-master-data.md
git commit -m "docs: describe student import template master data"
```
