# CoalApp Excel Export System

## 1. Overview

The Excel export system in CoalApp allows frontend data tables to export filtered records as `.xlsx` files. It provides a shared backend controller and service layer that convert model data into spreadsheets, including custom label mapping and row formatting.

## 2. Key files

- `app/Http/Controllers/ExportController.php`
  - Main export endpoint handler and model-specific export logic.
- `app/Services/ExportService.php`
  - Generic XLSX generation implementation using PhpSpreadsheet.
- `routes/api.php`
  - Defines the export API route.
- `resources/js/lib/pickle.js`
  - Client-side helper that submits POST data to a new browser tab.
- frontend page components
  - `resources/js/pages/coalsystem/Client/CList.vue`
  - `resources/js/pages/coalsystem/NotificationLogs/NList.vue`
  - `resources/js/pages/coalsystem/Documents/DList.vue`
  - `resources/js/pages/coalsystem/Request/RList.vue`
  - `resources/js/pages/coalsystem/Users/UList.vue`
  - `resources/js/pages/coalsystem/Logs/LList.vue`

## 3. Backend export flow

### 3.1 Controller entry point

`ExportController::index(Request $request, $model, $type = null)` does the following:

1. Reads request payload and JSON-decodes filter values.
2. Chooses export behavior based on the `$model` parameter.
3. Builds:
   - `$filename`: export file name
   - `$headers`: column key → column label map
   - `$data`: result rows from `tableList()` on the target model
   - `$rowCallback`: optional formatter for individual cells
4. Returns a streamed XLSX response via `ExportService::exportExcel()`.

### 3.2 Supported export models

The current export system supports these models:

- `clients`
- `documents`
- `offers`
- `requests`
- `notificationlogs`
- `users`
- `userlogs`

Each model has a dedicated section in `ExportController` to map columns, attach labels, and format values.

### 3.3 Data sources

Most export models use a `tableList()` helper method on Eloquent models:

- `App\Models\Documents::tableList()` for `clients`, `offers`, `requests`
- `App\Models\Document_files::tableList()` for `documents`
- `App\Models\NotificationLog::tableList()` for `notificationlogs`
- `App\Models\User::tableList()` for `users`
- `App\Models\Userlog::tableList()` for `userlogs`

The service expects an array of rows under the `data` key from the returned result.

### 3.4 Value mapping and formatting

A model-specific `$rowCallback` is defined in the controller to:

- format date columns as `d.m.Y` or `d.m.Y H:i:s`
- convert internal `status` values to localized labels
- decode JSON structures like `main_attr`
- flatten nested fields onto the export row object

If no formatter exists for a column, the raw row property is exported.

## 4. ExportService implementation

`app/Services/ExportService.php` is responsible for spreadsheet generation.

### 4.1 Spreadsheet creation

- Creates a new `Spreadsheet`
- Adds a worksheet named `Export`
- Writes headers to row 1
- Writes data rows starting at row 2
- Auto-sizes all columns

### 4.2 Value normalization

`normalizeValue()` converts values before export:

- booleans → `1` / `0`
- null → empty string
- arrays/objects → JSON string
- everything else → string

### 4.3 Response delivery

The export is streamed through `response()->streamDownload(...)` with:

- filename normalized to `.xlsx`
- content type `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

## 5. Frontend export trigger

Frontend pages use `Plib.openTab()` to submit the export request in a new tab:

```js
this.plib.openTab('POST', '/api/v1/export/clients', this.table.currentFilter, '_blank');
```

`resources/js/lib/pickle.js` creates a hidden HTML form and submits it with the configured POST data.

This means export requests behave like regular form submissions and return a binary download response.

## 6. API route

In `routes/api.php` the export endpoint is registered as:

```php
Route::post('/v1/export/{model}/{type?}', [ExportController::class, 'index']);
```

It is protected by the API authentication middleware stack in the same route group.

## 7. Important behaviors and caveats

- If the export data array is empty, the controller returns a JSON `404` response:
  - `{'success': false, 'msg': 'No export data found.'}`
- Exported date values are written as text strings, not native Excel date cells.
- Arrays and objects are exported as `JSON_UNESCAPED_UNICODE` strings.
- The system is tightly coupled to `tableList()` model output and relies on column keys matching the `$headers` map.

## 8. Recommendations

To extend the export system:

- add a new `case` branch in `ExportController` for the new model
- define `$headers` and `$filename`
- fetch `$data` using the model's `tableList()` API
- optionally set `$rowCallback` for custom formatting
- keep the export payload size manageable for large result sets

## 9. Summary

The export subsystem is a lightweight Excel generation pipeline built around a shared controller and a reusable spreadsheet service. It supports multiple models, custom row formatting, and frontend POST-based download requests.
