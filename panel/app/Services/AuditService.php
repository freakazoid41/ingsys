<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Centralized, performant audit snapshots.
 *
 * - Actor resolved via SINGLE join (users + persons + sys_options) and cached per request/user.
 * - Order snapshot via SINGLE aggregated query (no 2-step Sys_con_ops + Sys_con_entities).
 * - File snapshot via SINGLE join chain.
 * - Diff helper to avoid storing full getFormData (50KB) when only a few fields changed.
 * - Sys_options title cache (forever, busted on seeder).
 */
class AuditService
{
    /**
     * Snapshot of the actor at log time — frozen.
     * Cached per user for 5 minutes to avoid 3 queries per log trigger.
     */
    public static function actor(): array
    {
        try {
            $auth = auth('sanctum')->user() ?? auth()->user();
            if (!$auth) {
                return [
                    'user_id' => session('person_id') ? (DB::table('users')->where('person_id', session('person_id'))->value('id') ?? 0) : 0,
                    'person_id' => session('person_id') ?? 0,
                    'person_qnid' => session('person_id') ?? null,
                    'name' => session('ptitle') ?? 'system',
                    'email' => session('email') ?? null,
                    'role' => null,
                    'type_key' => session('type_key') ?? null,
                    'ip' => request()->ip(),
                    'sys_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ',
                ];
            }

            $cacheKey = 'audit:actor:' . $auth->id;
            return Cache::remember($cacheKey, 300, function () use ($auth) {
                $row = DB::table('users as u')
                    ->leftJoin('persons as p', 'p.id', '=', 'u.person_id')
                    ->leftJoin('sys_options as so', 'so.id', '=', 'p.type_id')
                    ->where('u.id', $auth->id)
                    ->select(
                        'u.id as user_id',
                        'u.person_id',
                        'p.qnid as person_qnid',
                        'p.name as p_name',
                        'p.surname as p_surname',
                        'u.email',
                        'u.role',
                        'so.op_key as type_key'
                    )->first();

                $name = trim(($row->p_name ?? '') . ' ' . (($row->p_surname ?? '-') !== '-' ? $row->p_surname : ''));
                if ($name === '') $name = $row->email ?? 'system';

                return [
                    'user_id' => $row->user_id ?? $auth->id,
                    'person_id' => $row->person_id ?? $auth->person_id ?? 0,
                    'person_qnid' => $row->person_qnid ?? session('person_id'),
                    'name' => $name,
                    'email' => $row->email ?? null,
                    'role' => $row->role ?? null,
                    'type_key' => $row->type_key ?? session('type_key'),
                    'ip' => request()->ip(),
                    'sys_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ',
                ];
            });
        } catch (\Throwable $e) {
            return [
                'user_id' => auth('sanctum')->user()->id ?? 0,
                'person_id' => session('person_id') ?? 0,
                'person_qnid' => session('person_id') ?? null,
                'name' => session('ptitle') ?? 'system',
                'email' => session('email') ?? null,
                'role' => null,
                'type_key' => session('type_key') ?? null,
                'ip' => request()->ip(),
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ',
            ];
        }
    }

    /**
     * Order snapshot — single aggregated query, cached 5 min per doc.
     * Returns {id,qnid,order_no,transfer_no,buying_no,spec_code,ctitle}
     */
    public static function order(int $docId): array
    {
        $cacheKey = "audit:order:$docId";
        return Cache::remember($cacheKey, 300, function () use ($docId) {
            $doc = DB::table('documents as d')->where('d.id', $docId)->first();
            if (!$doc) return ['id' => $docId];

            $agg = DB::selectOne("
                SELECT
                  MAX(CASE WHEN sce.entity_tag='order_no' THEN sce.entity_value END) as order_no,
                  MAX(CASE WHEN sce.entity_tag='transfer_no' THEN sce.entity_value END) as transfer_no,
                  MAX(CASE WHEN sce.entity_tag='buying_no' THEN sce.entity_value END) as buying_no,
                  MAX(CASE WHEN sce.entity_tag='spec_code' THEN sce.entity_value END) as spec_code,
                  MAX(CASE WHEN sce.entity_tag='ctitle' THEN sce.entity_value END) as ctitle
                FROM sys_con_ops sco
                JOIN sys_con_entities sce ON sce.conn_id=sco.id AND sce.table_tag='sys_con_ops'
                WHERE sco.main_id=? AND sco.conn_id=0
            ", [$docId]);

            return [
                'id' => $doc->id,
                'qnid' => $doc->qnid,
                'order_no' => $agg->order_no ?? null,
                'transfer_no' => $agg->transfer_no ?? $agg->order_no ?? null,
                'buying_no' => $agg->buying_no ?? null,
                'spec_code' => $agg->spec_code ?? null,
                'ctitle' => $agg->ctitle ?? null,
            ];
        });
    }

    public static function orderByQnid(string $qnid): array
    {
        $doc = DB::table('documents')->where('qnid', $qnid)->first();
        if (!$doc) return ['qnid' => $qnid];
        return self::order((int)$doc->id);
    }

    /**
     * Resolve nearest order for any document (item → parent order). Cached.
     */
    public static function orderForDocument(int $docId): array
    {
        $doc = DB::table('documents')->where('id', $docId)->first();
        if (!$doc) return ['id' => $docId];
        $typeKey = DB::table('sys_options')->where('id', $doc->type_id)->value('op_key');
        $attempts = 0;
        while ($doc && $typeKey !== 'op-doc-order' && (int)($doc->parent_id ?? 0) > 0 && $attempts < 3) {
            $parent = DB::table('documents')->where('id', $doc->parent_id)->first();
            if (!$parent) break;
            $doc = $parent;
            $typeKey = DB::table('sys_options')->where('id', $doc->type_id)->value('op_key');
            $attempts++;
        }
        return self::order((int)$doc->id);
    }

    /**
     * File snapshot — single query chain.
     */
    public static function file(int $fileId, ?string $entityTag = null, ?int $connId = null): array
    {
        try {
            $file = DB::table('document_files')->where('id', $fileId)->first();
            if (!$file) return ['id' => $fileId];

            // Resolve entity if not given
            $entityTagVal = $entityTag;
            $connIdVal = $connId;
            if ($entityTagVal === null || $connIdVal === null) {
                $ent = DB::table('sys_con_entities')->where(['entity_value' => (string)$fileId, 'table_tag' => 'document_files'])->first();
                if ($ent) {
                    $entityTagVal = $ent->entity_tag;
                    $connIdVal = $ent->conn_id;
                }
            }

            $field = null; $groupKey = null;
            if ($entityTagVal) {
                $parts = explode('**', $entityTagVal);
                $field = $parts[0] ?? null;
                $groupKey = $parts[1] ?? null;
            }

            $orderQnid = null; $orderNo = null;
            if ($connIdVal) {
                $conn = DB::table('sys_con_ops')->where('id', $connIdVal)->first();
                if ($conn) {
                    $doc = DB::table('documents')->where('id', $conn->main_id)->first();
                    if ($doc) {
                        $parentDoc = $doc;
                        // If file is on an item, climb to order
                        if ((int)($doc->parent_id ?? 0) > 0) {
                            $typeKey = DB::table('sys_options')->where('id', $doc->type_id)->value('op_key');
                            if ($typeKey === 'op-doc-order-item') {
                                $parentDoc = DB::table('documents')->where('id', $doc->parent_id)->first() ?? $doc;
                            }
                        }
                        $orderQnid = $parentDoc->qnid ?? $doc->qnid;
                        $orderNo = DB::table('sys_con_entities as sce')
                            ->join('sys_con_ops as sco', 'sco.id', '=', 'sce.conn_id')
                            ->where('sco.main_id', $parentDoc->id ?? $doc->id)
                            ->where('sce.entity_tag', 'order_no')
                            ->value('sce.entity_value');
                    }
                }
            }

            return [
                'id' => $fileId,
                'qnid' => $file->qnid ?? null,
                'status' => $file->status ?? null,
                'field' => $field,
                'group_key' => $groupKey,
                'entity_tag' => $entityTagVal,
                'relation_id' => $file->relation_id ?? null,
                'order_qnid' => $orderQnid,
                'order_no' => $orderNo,
            ];
        } catch (\Throwable $e) {
            return ['id' => $fileId];
        }
    }

    /**
     * Lightweight diff between two getFormData snapshots — avoids storing 50KB before/after.
     * Returns {changed: {field: [old,new]}, added: [...], removed: [...] } or full if diff too large.
     */
    public static function diff(?array $before, ?array $after): array
    {
        if (empty($before) && empty($after)) return ['changed' => []];
        if (empty($before)) return ['added' => $after];
        if (empty($after)) return ['removed' => array_keys($before)];

        // Flatten entities: "field**group**id" -> value
        $flatBefore = self::flattenFormData($before);
        $flatAfter = self::flattenFormData($after);

        $changed = [];
        $added = [];
        $removed = [];

        foreach ($flatAfter as $k => $v) {
            if (!array_key_exists($k, $flatBefore)) {
                $added[$k] = $v;
            } elseif ((string)$flatBefore[$k] !== (string)$v) {
                $changed[$k] = [$flatBefore[$k], $v];
            }
        }
        foreach ($flatBefore as $k => $v) {
            if (!array_key_exists($k, $flatAfter)) {
                $removed[$k] = $v;
            }
        }

        // If diff is >50% of before size, just store after (not worth diffing)
        if (count($changed) + count($added) + count($removed) > count($flatBefore) * 0.5) {
            return ['changed' => $changed, 'added' => $added, 'removed' => $removed, '_full' => false];
        }

        return ['changed' => $changed, 'added' => $added, 'removed' => $removed];
    }

    private static function flattenFormData(array $data): array
    {
        $flat = [];
        $fmt = $data['formFormat'] ?? $data;
        if (!is_array($fmt)) return $flat;
        foreach ($fmt as $formKey => $rows) {
            if (!is_array($rows)) continue;
            foreach ($rows as $connId => $row) {
                $ents = $row['entities'] ?? $row;
                if (!is_array($ents)) continue;
                foreach ($ents as $tag => $val) {
                    // Unwrap file JSON strings to file id for diff stability
                    if (is_string($val) && str_starts_with(trim($val), '{') && str_contains($val, '"id"')) {
                        try {
                            $j = json_decode($val, true);
                            if (isset($j['id'])) $val = 'file:' . $j['id'];
                        } catch (\Throwable $e) {}
                    }
                    $flat["$formKey.$tag"] = is_array($val) || is_object($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string)$val;
                }
            }
        }
        return $flat;
    }

    /**
     * Cached sys_options title lookup — forever until flushed on seeder.
     */
    public static function optionTitle(string $opKey): ?string
    {
        return Cache::rememberForever("sys:title:$opKey", fn() => DB::table('sys_options')->where('op_key', $opKey)->value('title'));
    }

    public static function flushOrder(int $docId): void
    {
        Cache::forget("audit:order:$docId");
    }

    public static function flushActor(int $userId): void
    {
        Cache::forget("audit:actor:$userId");
    }
}
