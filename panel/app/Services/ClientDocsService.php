<?php

namespace App\Services;

use App\Models\Documents;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Providers\DocumentServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Supplier document library (shared across tenders).
 *
 * A file belongs to CLIENT + PRODUCT CODE + REQUIREMENT + kVA — never to a single tender.
 * Storage: one `op-doc-client-docs` document per supplier, one EAV file slot per key.
 * Versioning reuses the generic engine: a new sys_con_entities row per upload,
 * active = document_files.status = 1, document_files.replaced_id chains backwards.
 * Expiry lives on each uploaded version (document_files.valid_until).
 */
class ClientDocsService
{
    public const TYPE  = 'op-doc-client-docs';
    public const FORM  = 'op-doc-client-docs-form';
    public const GROUP = 'client_docs';
    public const BRAND_GROUP = 'brand_catalog';

    /** Stable, unique-per-slot tag prefix (kept short: entity_tag is varchar(100)).
     *  Brand is part of slot identity: same product code on a different brand is a different file. */
    public static function slotTag(string $productCode, string $brand, string $standard, string $fileTitle, string $kva): string
    {
        $hash = substr(sha1($productCode.'|'.$brand.'|'.$standard.'|'.$fileTitle.'|'.$kva), 0, 24);
        return 'cdf_'.$hash;
    }

    public static function slotRow(string $productCode, string $brand, string $standard, string $fileTitle, string $kva): string
    {
        return 'slot_'.substr(sha1($productCode.'|'.$brand.'|'.$standard.'|'.$fileTitle.'|'.$kva), 0, 24);
    }

    /** Frontend/back shared match key: product code + brand + requirement text + kVA. */
    public static function matchKey(string $productCode, string $brand, string $standard, string $fileTitle, string $kva): string
    {
        return $productCode.'||'.$brand.'||'.$standard.'||'.$fileTitle.'||'.$kva;
    }

    /** Pre-brand key, kept only so frozen (completed) tenders still resolve their old snapshots. */
    public static function legacyMatchKey(string $productCode, string $standard, string $fileTitle, string $kva): string
    {
        return $productCode.'||'.$standard.'||'.$fileTitle.'||'.$kva;
    }

    /** Fold free-text brand variants: trim, collapse spaces, Turkish-aware casefold. */
    public static function normalizeBrand(string $brand): string
    {
        $b = trim(preg_replace('/\s+/u', ' ', $brand));
        $b = strtr($b, ['İ' => 'i', 'I' => 'ı']);
        return mb_strtolower($b, 'UTF-8');
    }

    /**
     * Upsert one brand into the supplier's suggestion catalog.
     * Canonical spelling is the first one entered; later variants only refresh usage.
     * Catalog is EAV, stored inside the supplier's client-docs registry document
     * (group `brand_catalog`, row `b_<sha1(norm)>`) — no side tables.
     */
    public function rememberBrand(string $supplierQnid, string $brand, bool $touch = true): void
    {
        $brand = trim($brand);
        $norm = self::normalizeBrand($brand);
        if ($supplierQnid === '' || $brand === '' || $norm === '') return;

        try {
            $registry = $this->ensureRegistry($supplierQnid);
            $connId = (int) $registry['conn_id'];
            if (! $connId) return;

            $suffix = '**'.self::BRAND_GROUP.'**'.self::brandRow($norm);
            $now = now();

            $exists = Sys_con_entities::where('conn_id', $connId)
                ->where('entity_tag', 'brand'.$suffix)
                ->exists();
            if ($exists) {
                if ($touch) {
                    Sys_con_entities::where('conn_id', $connId)
                        ->where('entity_tag', 'last_used'.$suffix)
                        ->update(['entity_value' => $now->toDateTimeString(), 'updated_at' => $now]);
                }
                return;
            }

            foreach ([
                'brand' => $brand,
                'brand_norm' => $norm,
                'last_used' => $now->toDateTimeString(),
            ] as $tag => $value) {
                $e = new Sys_con_entities();
                $e->table_tag = 'sys_con_ops';
                $e->conn_id = $connId;
                $e->entity_tag = $tag.$suffix;
                $e->entity_value = $value;
                $e->save();
            }
        } catch (\Throwable $e) {
            // catalog is a convenience layer — never break uploads/listing on it
        }
    }

    /** Deterministic EAV row key for one folded brand. */
    public static function brandRow(string $norm): string
    {
        return 'b_'.substr(sha1($norm), 0, 20);
    }

    /** Distinct brands of one supplier, most recently used first. */
    public function brands(string $supplierQnid, int $limit = 100): array
    {
        if ($supplierQnid === '') return [];
        try {
            // Lazy backfill: historical offer items + branded library slots predate
            // the catalog; fold them in before the first read (insert-only).
            $this->backfillBrands($supplierQnid);

            $doc = $this->registryDoc($supplierQnid);
            $connId = $doc ? $this->connId($doc) : 0;
            if (! $connId) return [];

            $entities = DB::table('sys_con_entities')
                ->where('conn_id', $connId)
                ->where('table_tag', 'sys_con_ops')
                ->where('entity_tag', 'like', '%**'.self::BRAND_GROUP.'**%')
                ->get(['entity_tag', 'entity_value']);

            $rows = [];
            foreach ($entities as $e) {
                $parts = explode('**', $e->entity_tag);
                if (count($parts) !== 3 || $parts[1] !== self::BRAND_GROUP) continue;
                $rows[$parts[2]][$parts[0]] = $e->entity_value;
            }

            $list = [];
            foreach ($rows as $r) {
                if (! empty($r['brand'])) {
                    $list[] = ['brand' => $r['brand'], 'last' => $r['last_used'] ?? ''];
                }
            }
            usort($list, fn ($a, $b) => strcmp($b['last'], $a['last']));

            return array_slice(array_column($list, 'brand'), 0, $limit);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Seed the catalog from data that predates it (offers + branded slots). Insert-only. */
    private function backfillBrands(string $supplierQnid): void
    {
        try {
            $itemForm = Sys_options::where('op_key', 'op-doc-ihale-offer-item-form')->value('id');
            $offerType = Sys_options::where('op_key', 'op-doc-ihale-offer')->value('id');
            if ($itemForm && $offerType) {
                $docIds = DB::table('sys_con_entities as se')
                    ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                    ->join('documents as d', 'd.id', '=', 'so.main_id')
                    ->where('d.type_id', $offerType)
                    ->where('d.status', 1)
                    ->where('se.entity_tag', 'supplier_qnid')
                    ->where('se.entity_value', $supplierQnid)
                    ->pluck('so.main_id')
                    ->unique();

                if ($docIds->isNotEmpty()) {
                    $brands = DB::table('sys_con_entities as se')
                        ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                        ->whereIn('so.main_id', $docIds)
                        ->where('so.type_id', $itemForm)
                        ->where('se.entity_tag', 'urun_markasi')
                        ->pluck('se.entity_value');
                    foreach ($brands as $brand) {
                        $b = trim((string) $brand);
                        if ($b !== '') $this->rememberBrand($supplierQnid, $b, false);
                    }
                }
            }

            $doc = $this->registryDoc($supplierQnid);
            $connId = $doc ? $this->connId($doc) : 0;
            if ($connId) {
                $slotBrands = DB::table('sys_con_entities')
                    ->where('conn_id', $connId)
                    ->where('entity_tag', 'like', 'brand**'.self::GROUP.'**%')
                    ->pluck('entity_value');
                foreach ($slotBrands as $brand) {
                    $b = trim((string) $brand);
                    if ($b !== '') $this->rememberBrand($supplierQnid, $b, false);
                }
            }
        } catch (\Throwable $e) {
            // backfill is best-effort — never block the suggestion read
        }
    }

    public function registryDoc(string $supplierQnid): ?Documents
    {
        $typeId = Sys_options::where('op_key', self::TYPE)->value('id');
        $formId = Sys_options::where('op_key', self::FORM)->value('id');
        if (! $typeId || ! $formId) {
            return null;
        }

        $docId = DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->where('so.type_id', $formId)
            ->where('se.entity_tag', 'supplier_qnid')
            ->where('se.entity_value', $supplierQnid)
            ->orderBy('se.id')
            ->value('so.main_id');

        return $docId ? Documents::find($docId) : null;
    }

    public function ensureRegistry(string $supplierQnid, string $supplierTitle = ''): array
    {
        $doc = $this->registryDoc($supplierQnid);
        if ($doc) {
            return ['doc' => $doc, 'conn_id' => $this->connId($doc)];
        }

        $typeId = Sys_options::where('op_key', self::TYPE)->value('id');
        $formId = Sys_options::where('op_key', self::FORM)->value('id');
        $formMain = Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->value('id');
        if (! $typeId || ! $formId) {
            throw new \RuntimeException('Tedarikçi evrak kütüphanesi tanımı yok (ClientDocsSeeder çalıştırın).');
        }

        $now = now();
        $doc = new Documents();
        $doc->type_id = $typeId;
        $doc->person_id = session('person_id') ?: 'system';
        $doc->title = $supplierTitle ?: 'Tedarikçi Evrak Kütüphanesi';
        $doc->grp_code = $GLOBALS['SYS_CODE'] ?? 'GDZ';
        $doc->status = 1;
        $doc->save();

        $conn = new Sys_con_ops();
        $conn->main_id = $doc->id;
        $conn->conn_id = 0;
        $conn->type_id = $formId;
        $conn->sub_type_id = $formMain;
        $conn->status = 1;
        $conn->save();

        foreach (['supplier_qnid' => $supplierQnid, 'supplier_title' => $supplierTitle] as $tag => $value) {
            $e = new Sys_con_entities();
            $e->table_tag = 'sys_con_ops';
            $e->conn_id = $conn->id;
            $e->entity_tag = $tag;
            $e->entity_value = $value;
            $e->save();
        }

        return ['doc' => $doc, 'conn_id' => $conn->id];
    }

    public function connId(?Documents $doc): ?int
    {
        if (! $doc) {
            return null;
        }
        $formId = Sys_options::where('op_key', self::FORM)->value('id');
        return (int) Sys_con_ops::where('main_id', $doc->id)->where('type_id', $formId)->where('conn_id', 0)->value('id');
    }

    /**
     * Upload/replace one slot. `$reference` is a temp-upload reference from /v1/temp-upload.
     */
    public function uploadSlot(array $a): array
    {
        $supplierQnid = (string) ($a['supplier_qnid'] ?? '');
        $supplierTitle = (string) ($a['supplier_title'] ?? '');
        $productCode = trim((string) ($a['product_code'] ?? ''));
        $brand = trim((string) ($a['brand'] ?? ''));
        $standard = trim((string) ($a['standard'] ?? ''));
        $fileTitle = trim((string) ($a['file_title'] ?? ''));
        $kva = trim((string) ($a['kva'] ?? ''));
        $evrakNo = trim((string) ($a['evrak_no'] ?? ''));
        $reference = $a['reference'] ?? null;

        if ($supplierQnid === '' || $productCode === '' || $fileTitle === '') {
            return ['success' => false, 'msg' => 'Eksik parametre: supplier_qnid / product_code / file_title'];
        }
        if ($brand === '') {
            return ['success' => false, 'msg' => 'Ürün markası zorunludur — evrak ürün kodu + markaya göre saklanır.'];
        }
        if (! is_array($reference) || empty($reference['reference_id'])) {
            return ['success' => false, 'msg' => 'Geçersiz dosya referansı'];
        }

        $registry = $this->ensureRegistry($supplierQnid, $supplierTitle);
        $doc = $registry['doc'];
        $connId = $registry['conn_id'];

        $tag = self::slotTag($productCode, $brand, $standard, $fileTitle, $kva);
        $row = self::slotRow($productCode, $brand, $standard, $fileTitle, $kva);
        $suffix = '**'.self::GROUP.'**'.$row;

        $entities = [
            'supplier_qnid' => $supplierQnid,
            'supplier_title' => $supplierTitle,
            'product_code'.$suffix => $productCode,
            'brand'.$suffix => $brand,
            'standard'.$suffix => $standard,
            'file_title'.$suffix => $fileTitle,
            'kva'.$suffix => $kva,
            'evrak_no'.$suffix => $evrakNo,
        ];

        $provider = new DocumentServiceProvider();
        $rsp = $provider->registerContent($doc->qnid, [
            'typeKey' => self::TYPE,
            'dynamicF' => [
                self::FORM.'**'.$connId => [
                    'tag' => self::FORM,
                    'entities' => $entities,
                ],
            ],
        ], [
            'dynamicFile**'.self::GROUP.'**'.$connId.'*-*'.$tag.$suffix => json_encode($reference),
        ]);

        if (empty($rsp['success'])) {
            return ['success' => false, 'msg' => $rsp['msg'] ?? $rsp['message'] ?? 'Dosya kütüphaneye yazılamadı'];
        }

        $file = $this->activeFile($connId, $tag.$suffix);

        $this->rememberBrand($supplierQnid, $brand);

        return ['success' => true, 'slot' => $this->slotPayload($row, $connId, $tag.$suffix), 'file' => $file];
    }

    /**
     * All slots (with their version history) for a supplier.
     */
    public function listing(string $supplierQnid): array
    {
        $doc = $this->registryDoc($supplierQnid);
        if (! $doc) {
            return ['success' => true, 'data' => ['qnid' => null, 'slots' => []]];
        }
        $connId = $this->connId($doc);

        $entities = DB::table('sys_con_entities')
            ->where('conn_id', $connId)
            ->where('table_tag', 'sys_con_ops')
            ->get(['entity_tag', 'entity_value']);

        $meta = [];
        foreach ($entities as $e) {
            $parts = explode('**', $e->entity_tag);
            if (count($parts) !== 3 || $parts[1] !== self::GROUP) continue;
            $meta[$parts[2]][$parts[0]] = $e->entity_value;
        }

        $files = DB::table('sys_con_entities as se')
            ->join('document_files as df', 'df.id', '=', DB::raw('se.entity_value::int'))
            ->where('se.conn_id', $connId)
            ->where('se.table_tag', 'document_files')
            ->orderByDesc('df.id')
            ->get(['se.entity_tag', 'df.id', 'df.qnid', 'df.status', 'df.valid_until', 'df.created_at', 'df.replaced_id']);

        $byRow = [];
        foreach ($files as $f) {
            $parts = explode('**', $f->entity_tag);
            if (count($parts) < 3 || $parts[1] !== self::GROUP) continue;
            $row = $parts[2];
            $byRow[$row] = $byRow[$row] ?? [];
            $byRow[$row][] = [
                'id' => (int) $f->id,
                'qnid' => $f->qnid,
                'status' => (int) $f->status,
                'valid_until' => $f->valid_until,
                'created_at' => $f->created_at,
                'replaced_id' => (int) $f->replaced_id,
            ];
        }

        $slots = [];
        foreach ($byRow as $row => $versions) {
            $m = $meta[$row] ?? [];
            if (empty($m['product_code']) || empty($m['file_title'])) continue;
            $active = null;
            foreach ($versions as $v) {
                if ($v['status'] === 1) { $active = $v; break; }
            }
            $slots[] = [
                'row' => $row,
                'product_code' => $m['product_code'] ?? '',
                'brand' => $m['brand'] ?? '',
                'standard' => $m['standard'] ?? '',
                'file_title' => $m['file_title'] ?? '',
                'kva' => $m['kva'] ?? '',
                'evrak_no' => $m['evrak_no'] ?? '',
                'match_key' => self::matchKey($m['product_code'] ?? '', $m['brand'] ?? '', $m['standard'] ?? '', $m['file_title'] ?? '', $m['kva'] ?? ''),
                'legacy_key' => self::legacyMatchKey($m['product_code'] ?? '', $m['standard'] ?? '', $m['file_title'] ?? '', $m['kva'] ?? ''),
                'file' => $active,
                'expired' => $this->isExpired($active),
                'versions' => $versions,
            ];
        }

        // Backfill the brand suggestion catalog from existing slots (insert-only).
        foreach ($slots as $s) {
            $b = trim((string) ($s['brand'] ?? ''));
            if ($b !== '') $this->rememberBrand($supplierQnid, $b, false);
        }

        // Attach the latest file-level transaction (offer-file-rejected etc.) to active files
        $activeIds = [];
        foreach ($slots as $s) {
            if (! empty($s['file']['id'])) $activeIds[] = (int) $s['file']['id'];
        }
        if ($activeIds && $connId) {
            $in = implode(',', $activeIds);
            $lastRows = DB::select("SELECT DISTINCT ON (t.target_id) t.target_id, so.op_key, so.title, t.note, t.created_at
                                    FROM transactions t
                                    INNER JOIN sys_options so ON so.id = t.type_id
                                    WHERE t.target_id IN ($in) AND t.op_id = 1
                                    ORDER BY t.target_id, t.id DESC");
            $lastMap = [];
            foreach ($lastRows as $lr) {
                $lastMap[(int) $lr->target_id] = [
                    'op_key' => $lr->op_key,
                    'title' => $lr->title,
                    'note' => $lr->note,
                    'created_at' => $lr->created_at,
                ];
            }
            foreach ($slots as &$s) {
                if (! empty($s['file']['id']) && isset($lastMap[(int) $s['file']['id']])) {
                    $s['file']['last_status'] = $lastMap[(int) $s['file']['id']];
                }
            }
            unset($s);
        }

        return ['success' => true, 'data' => ['qnid' => $doc->qnid, 'conn_id' => $connId, 'slots' => $slots]];
    }

    public function slotPayload(string $row, int $connId, string $fullTag): ?array
    {
        $file = $this->activeFile($connId, $fullTag);
        if (! $file) {
            return null;
        }
        $meta = DB::table('sys_con_entities')
            ->where('conn_id', $connId)
            ->where('table_tag', 'sys_con_ops')
            ->where('entity_tag', 'like', '%**'.self::GROUP.'**'.$row)
            ->get(['entity_tag', 'entity_value']);
        $m = [];
        foreach ($meta as $e) {
            $m[explode('**', $e->entity_tag)[0]] = $e->entity_value;
        }

        return [
            'row' => $row,
            'product_code' => $m['product_code'] ?? '',
            'brand' => $m['brand'] ?? '',
            'standard' => $m['standard'] ?? '',
            'file_title' => $m['file_title'] ?? '',
            'kva' => $m['kva'] ?? '',
            'evrak_no' => $m['evrak_no'] ?? '',
            'match_key' => self::matchKey($m['product_code'] ?? '', $m['brand'] ?? '', $m['standard'] ?? '', $m['file_title'] ?? '', $m['kva'] ?? ''),
            'legacy_key' => self::legacyMatchKey($m['product_code'] ?? '', $m['standard'] ?? '', $m['file_title'] ?? '', $m['kva'] ?? ''),
            'file' => $file,
            'expired' => $this->isExpired($file),
            'versions' => [$file],
        ];
    }

    public function activeFile(int $connId, string $fullTag): ?array
    {
        $f = DB::table('sys_con_entities as se')
            ->join('document_files as df', 'df.id', '=', DB::raw('se.entity_value::int'))
            ->where('se.conn_id', $connId)
            ->where('se.entity_tag', $fullTag)
            ->where('df.status', 1)
            ->orderByDesc('df.id')
            ->first(['df.id', 'df.qnid', 'df.status', 'df.valid_until', 'df.created_at', 'df.replaced_id']);

        if (! $f) return null;

        return [
            'id' => (int) $f->id,
            'qnid' => $f->qnid,
            'status' => (int) $f->status,
            'valid_until' => $f->valid_until,
            'created_at' => $f->created_at,
            'replaced_id' => (int) $f->replaced_id,
        ];
    }

    public function isExpired(?array $file): bool
    {
        if (empty($file['valid_until'])) return false;
        return strtotime($file['valid_until']) < strtotime(date('Y-m-d'));
    }

    /**
     * When a tender is completed, freeze what the supplier had at that moment.
     * One JSON snapshot entity per offer, so later library replacements do not
     * rewrite history for a closed tender.
     */
    public function snapshotOffers(string $ihaleQnid): array
    {
        $offers = DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->join('documents as d', 'd.id', '=', 'so.main_id')
            ->join('sys_options as sp', 'sp.id', '=', 'd.type_id')
            ->where('sp.op_key', 'op-doc-ihale-offer')
            ->where('se.entity_tag', 'ihale_qnid')
            ->where('se.entity_value', $ihaleQnid)
            ->pluck('d.qnid')
            ->unique()
            ->values();

        $done = 0;
        foreach ($offers as $offerQnid) {
            $details = $this->offerSupplier($offerQnid);
            if (! $details) continue;

            $listing = $this->listing($details['supplier_qnid']);
            $slots = $listing['data']['slots'] ?? [];

            $payload = json_encode([
                'snapshot_at' => now()->toDateTimeString(),
                'tender_qnid' => $ihaleQnid,
                'slots' => $slots,
            ], JSON_UNESCAPED_UNICODE);

            $connId = $details['conn_id'];
            $existing = Sys_con_entities::where(['conn_id' => $connId, 'entity_tag' => 'snapshot', 'table_tag' => 'sys_con_ops'])->first();
            $entity = $existing ?: new Sys_con_entities();
            $entity->table_tag = 'sys_con_ops';
            $entity->conn_id = $connId;
            $entity->entity_tag = 'snapshot';
            $entity->entity_value = $payload;
            $entity->save();
            $done++;
        }

        return ['offers' => $offers->count(), 'snapshotted' => $done];
    }

    public function offerSupplier(string $offerQnid): ?array
    {
        $conn = DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->join('documents as d', 'd.id', '=', 'so.main_id')
            ->join('sys_options as sp', 'sp.id', '=', 'd.type_id')
            ->where('d.qnid', $offerQnid)
            ->where('sp.op_key', 'op-doc-ihale-offer')
            ->where('so.conn_id', 0)
            ->select('so.id as conn_id')
            ->first();
        if (! $conn) return null;

        $supplier = DB::table('sys_con_entities')
            ->where('conn_id', $conn->conn_id)
            ->where('entity_tag', 'supplier_qnid')
            ->value('entity_value');

        return $supplier ? ['conn_id' => (int) $conn->conn_id, 'supplier_qnid' => $supplier] : null;
    }
}
