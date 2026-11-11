<?php

namespace App\Http\Controllers;

use App\Providers\DocumentServiceProvider;
use App\Providers\PersonsServiceProvider;
use App\Providers\EmailServiceProvider;
use App\Services\ClientDocsService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    /**
     * Order / client only — offers are legacy (old system). New system has:
     * clients, orders, order items, order serials, transactions.
     */
    public const TEDARIK_ORDER_APPROVED_STATUSES = ['doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_files_rejected'];
    public const TEDARIK_FILE_STATUSES = ['doc_file_accepted','doc_file_rejected'];

    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    protected DocumentServiceProvider $docs;
    protected EmailServiceProvider $emails;
    protected PermissionService $perms;

    public function __construct(
        DocumentServiceProvider $documentService = null,
        EmailServiceProvider $emailService = null,
        PermissionService $permissionService = null
    ) {
        $this->docs  = $documentService ?: new DocumentServiceProvider();
        $this->emails = $emailService ?: new EmailServiceProvider();
        $this->perms = $permissionService ?: new PermissionService();
    }

    private function isValidUuid(?string $v): bool
    {
        return is_string($v) && preg_match(self::UUID_REGEX, $v) === 1;
    }

    private function decodeDataField(?string $raw): ?array
    {
        if ($raw === null || $raw === '') return null;
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) return null;
        return is_array($decoded) ? $decoded : null;
    }

    private function mergeTempUploadReferences(array $source, array &$files): void
    {
        foreach ($source as $fkey => $value) {
            if (strpos($fkey, 'dynamicFile') !== false && is_string($value)) {
                $files[$fkey] = $value;
            }
        }
    }

    private const OFFER_KEYS = ['op-doc-ihale-offer', 'op-doc-ihale-offer-item'];

    /**
     * Supplier ownership for tender offers. Resellers may only touch offers whose
     * supplier_qnid is one of their bound clients (session clientQnidList).
     * Admins (non-reseller) bypass. Fail-closed: unknown supplier → 403.
     * Returns true or a 403 JsonResponse.
     */
    private function assertOfferOwnership(?string $key, string $method, Request $request, ?array $decodedData)
    {
        if (!in_array($key, self::OFFER_KEYS, true)) return true;
        if (! $this->currentRoleIsReseller()) return true;
        $allowed = $this->currentSupplierList();

        $suppliers = [];
        if (is_array($decodedData)) {
            foreach (($decodedData['dynamicF'] ?? []) as $entry) {
                foreach (($entry['entities'] ?? []) as $k => $v) {
                    if ($k === 'supplier_qnid' && is_string($v) && $v !== '') $suppliers[] = $v;
                }
            }
        }
        if (empty($suppliers) && in_array($method, ['GET','PUT','DELETE'], true) && !empty($request->id)) {
            try {
                $detail = $this->docs->getFormData($request->id);
                foreach (($detail['formFormat']['op-doc-ihale-offer-form'] ?? []) as $cr) {
                    foreach (($cr['entities'] ?? []) as $k => $v) {
                        if ($k === 'supplier_qnid' && is_string($v) && $v !== '') $suppliers[] = $v;
                    }
                }
            } catch (\Throwable $e) {}
        }
        if (empty($suppliers)) {
            return response()->json(['success'=>false,'msg'=>'Teklif firma bağlantısı bulunamadı.'],403);
        }
        foreach ($suppliers as $s) {
            if (!in_array($s, $allowed, true)) {
                return response()->json(['success'=>false,'msg'=>'Bu teklif sizin firmanıza ait değil.'],403);
            }
        }
        return true;
    }

    /**
     * Görüş files are admin-only: resellers may not attach or remove them while
     * editing their own offer. Runs before registerContent so forged keys never
     * reach versioning or removedData processing.
     */
    private function stripResellerGorus(?string $key, array &$files, ?array &$decoded): void
    {
        if (!in_array($key, self::OFFER_KEYS, true)) return;
        if (! $this->currentRoleIsReseller()) return;
        foreach ($files as $fkey => $f) {
            $fn = explode('*-*', (string) $fkey)[1] ?? '';
            if (str_starts_with($fn, 'gorus_')) unset($files[$fkey]);
        }
        if (is_array($decoded) && isset($decoded['removedData']) && is_array($decoded['removedData'])) {
            $decoded['removedData'] = array_values(array_filter($decoded['removedData'], function ($row) {
                return !str_starts_with((string)($row['key'] ?? ''), 'gorus_');
            }));
        }
    }

    private function resolveDocumentKey(Request $request, string $method, ?array $decodedData = null): ?string
    {
        if ($method === 'POST') {
            return $decodedData['typeKey'] ?? null;
        }
        $form = $this->docs->getFormData($request->id);
        return $form['document']->op_key ?? null;
    }

    private function buildOrderNotifPayload(string $orderQnid, array $extra = []): array
    {
        try {
            $detail = $this->docs->getFormData($orderQnid);
            $entities = [];
            foreach (($detail['formFormat']['op-doc-order-form'] ?? []) as $cr) {
                foreach (($cr['entities'] ?? []) as $k => $v) if (!isset($entities[$k])) $entities[$k] = $v;
            }
            $sys = $entities['sys_code'] ?? ($detail['document']->grp_code ?? ($GLOBALS['SYS_CODE'] ?? 'GDZ'));
            return array_merge([
                'order_no' => $entities['order_no'] ?? $orderQnid,
                'spec_code' => $entities['spec_code'] ?? '',
                'sys_code' => $sys,
                'bukrs' => $sys,
                'BUKRS' => $sys,
                'ctitle' => $entities['ctitle'] ?? '',
                'qnid' => $orderQnid,
                'order_qnid' => $orderQnid,
                'order_spec' => $entities['spec_code'] ?? '',
                'order_sys_code' => $sys,
            ], $extra);
        } catch (\Throwable $e) {
            return array_merge(['qnid'=>$orderQnid,'order_qnid'=>$orderQnid,'sys_code'=>'GDZ','bukrs'=>'GDZ'], $extra);
        }
    }

    /**
     * Resolve the REAL order behind a file through its own entity connection —
     * no parent climbing. The file's sys_con_entities row (table_tag =
     * 'document_files', entity_value = file id) points at the conn whose
     * main_id is the owning document; for order files that IS the order
     * (base or EBELN-X clone). Never hops past it to the original.
     */
    private function resolveFileOrderDoc(string $fileQnid): ?\App\Models\Documents
    {
        try {
            $fileRow = \App\Models\Document_files::where('qnid', $fileQnid)->first();
            if (!$fileRow) return null;
            $isOrder = fn ($doc) => $doc && ((\App\Models\Sys_options::find($doc->type_id)->op_key ?? null) === 'op-doc-order');
            // 1) the file's own connection — direct owner
            $ent = \App\Models\Sys_con_entities::where('table_tag', 'document_files')
                ->where('entity_value', (string) $fileRow->id)
                ->orderByDesc('id')->first();
            if ($ent) {
                $conn = \App\Models\Sys_con_ops::find((int) $ent->conn_id);
                $main = $conn ? \App\Models\Documents::find((int) $conn->main_id) : null;
                if ($isOrder($main)) return $main;
                // item/serial slot: the slot owner's parent is its order
                if ($main && (int) $main->parent_id !== 0) {
                    $order = \App\Models\Documents::find((int) $main->parent_id);
                    if ($isOrder($order)) return $order;
                }
            }
            // 2) fallback: relation pointer, taken as-is when it is an order
            $rel = \App\Models\Documents::find((int) $fileRow->relation_id);
            if ($isOrder($rel)) return $rel;
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    public function index(Request $request)
    {
        $method = strtoupper($request->method());

        if (in_array($method, ['GET','PUT','DELETE'], true) && !empty($request->id) && !$this->isValidUuid($request->id)) {
            return response()->json(['success'=>false,'msg'=>'Geçersiz belge kimliği (uuid bekleniyor)'],422);
        }

        $decodedData = null;
        if ($method === 'POST') {
            $decodedData = $this->decodeDataField($request->input('data'));
            if ($decodedData === null) {
                return response()->json(['success'=>false,'msg'=>'Geçersiz data JSON'],422);
            }
        } elseif ($method === 'PUT') {
            $rawAll = $request->all();
            if (empty($rawAll)) {
                $rawAll = function_exists('parsePut') ? parsePut() : [];
            }
            $decodedData = $this->decodeDataField($rawAll['data'] ?? null);
            $request->attributes->set('_put_raw', $rawAll);
            $request->attributes->set('_put_decoded', $decodedData);
        }

        $key = $this->resolveDocumentKey($request, $method, $decodedData);

        if (!docPermCheck($key, ($method === 'GET' ? 'read' : 'edit'))) {
            if (!($key === 'op-doc-client' && session('type_key') === 'op-pert-reseller' && in_array($request->id, session('currentStatus')['clientQnidList'] ?? [], true) && in_array($method, ['GET','PUT'], true))) {
                return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır...'],403);
            }
        }

        // İhale teklifi sahiplik kontrolü: tedarikçi (reseller) yalnızca kendi
        // bağlı carisine ait teklifleri okuyabilir/yazabilir. Adminler bypass.
        // (docPermCheck yukarıda modül kapısını kontrol eder; bu, satır sahipliğidir.)
        $offerGuardDecoded = $decodedData;
        if ($method === 'PUT') {
            $offerGuardDecoded = $request->attributes->get('_put_decoded');
            if ($offerGuardDecoded === null) {
                $rawAll = $request->all();
                if (empty($rawAll) && function_exists('parsePut')) $rawAll = parsePut();
                $offerGuardDecoded = $this->decodeDataField($rawAll['data'] ?? null);
            }
        }
        $offerGuard = $this->assertOfferOwnership($key, $method, $request, $offerGuardDecoded);
        if ($offerGuard !== true) return $offerGuard;

        return match($method) {
            'GET'    => $this->handleShow($request),
            'POST'   => $this->handleStore($request, $decodedData, $key),
            'PUT'    => $this->handleUpdate($request, $key),
            'DELETE' => $this->handleDestroy($request, $key),
            default  => response()->json(['success'=>false,'msg'=>'Desteklenmeyen metod'],405),
        };
    }

    private function handleShow(Request $request)
    {
        $res = $this->docs->getFormData($request->id);
        return response()->json(['success'=>!empty($res), 'data'=>$res]);
    }

    private function handleStore(Request $request, ?array $decoded, ?string $key)
    {
        $files = $request->files->all();
        $this->mergeTempUploadReferences($request->all(), $files);
        $this->stripResellerGorus($key, $files, $decoded);
        $res = $this->docs->registerContent(0, $decoded, $files);
        // Pipeline step 2: new Talep Dökümanı → notify Müdürlük Talep Onay (05-01 group, planlama-01)
        if ($key === 'op-doc-requestf' && (($res['id'] ?? 0) > 0)) {
            try {
                $detail = $res['detail'] ?? $this->docs->getFormData($res['qnid'] ?? '');
                $ents = [];
                foreach (($detail['formFormat']['op-doc-requestf-form'] ?? []) as $cr) {
                    foreach (($cr['entities'] ?? []) as $k => $v) if (!isset($ents[$k])) $ents[$k] = $v;
                }
                $me = auth()->user();
                $this->emails->sendPlanlamaTalepSent([
                    'qnid' => $res['qnid'] ?? null,
                    'talep_qnid' => $res['qnid'] ?? null,
                    'title' => $ents['title'] ?? '-',
                    'request_no' => $ents['request_no'] ?? '-',
                    'mudur' => $ents['firma_adi'] ?? '-',
                    'uploader' => $me->email ?? '-',
                    'sys_code' => $GLOBALS['SYS_CODE'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('planlama-01 dispatch failed', ['msg' => $e->getMessage()]);
            }
        }
        return response()->json(['success'=>($res['id'] ?? 0) > 0, 'data'=>$res]);
    }

    private function handleUpdate(Request $request, ?string $key)
    {
        $data = $request->attributes->get('_put_raw') ?? $request->all();
        if (empty($data) && function_exists('parsePut')) {
            $data = parsePut();
        }
        $decoded = $request->attributes->get('_put_decoded');
        if ($decoded === null) {
            $decoded = $this->decodeDataField($data['data'] ?? null);
            if ($decoded === null) {
                return response()->json(['success'=>false,'msg'=>'Geçersiz data JSON'],422);
            }
        }

        $files = $request->files->all();
        $this->mergeTempUploadReferences($data, $files);
        $this->stripResellerGorus($key, $files, $decoded);

        $res = $this->docs->registerContent($request->id, $decoded, $files);

        // Talep Dökümanı update hardening: firma_adi is display text derived from the
        // bound müdürlük (mudur_qnid) — never trust it from the payload on edits.
        // Rejected Talep re-saved (revised) → back to awaiting (re-queue for approval) + notify 05-01 again
        if ($key === 'op-doc-requestf' && (($res['id'] ?? 0) > 0)) {
            try {
                $detail = $res['detail'] ?? $this->docs->getFormData($request->id);
                $ents = [];
                foreach (($detail['formFormat']['op-doc-requestf-form'] ?? []) as $cr) {
                    foreach (($cr['entities'] ?? []) as $k => $v) if (!isset($ents[$k])) $ents[$k] = $v;
                }
                if (!empty($ents['mudur_qnid'])) {
                    try {
                        $cliDetail = $this->docs->getFormData($ents['mudur_qnid']);
                        $cents = [];
                        foreach (($cliDetail['formFormat']['op-doc-client-form'] ?? []) as $cr) {
                            foreach (($cr['entities'] ?? []) as $k => $v) if (!isset($cents[$k])) $cents[$k] = $v;
                        }
                        if (!empty($cents['lifnr'])) {
                            $canonical = strtoupper(trim($cents['client_system'] ?? 'GDZ')) . '-' . trim($cents['lifnr']) . ' ' . trim($cents['title'] ?? '');
                            if ($canonical !== '' && ($ents['firma_adi'] ?? '') !== $canonical) {
                                $fconnIds = \Illuminate\Support\Facades\DB::table('sys_con_ops')->where('main_id', $res['id'])->pluck('id');
                                if ($fconnIds->isNotEmpty()) {
                                    \Illuminate\Support\Facades\DB::table('sys_con_entities')
                                        ->whereIn('conn_id', $fconnIds)
                                        ->where('entity_tag', 'firma_adi')
                                        ->where('table_tag', 'sys_con_ops')
                                        ->update(['entity_value' => $canonical, 'updated_at' => now()]);
                                }
                                $ents['firma_adi'] = $canonical;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('planlama firma_adi re-assert failed', ['msg' => $e->getMessage()]);
                    }
                }
                $st = $detail['document']->status ?? null;
                $arr = is_string($st) ? (json_decode($st, true) ?: []) : (array)$st;
                $last = end($arr);
                if (($last['op_key'] ?? null) === 'tr-request-man-rejected') {
                    $back = $this->docs->setStatus($request->id, 'tr-request-man-awaiting', 'Revize edildi — yeniden onaya gönderildi');
                    if ($back['success'] ?? false) {
                        $res['detail'] = $back['detail'] ?? $this->docs->getFormData($request->id);
                        $me = auth()->user();
                        $this->emails->sendPlanlamaTalepSent([
                            'qnid' => $request->id,
                            'talep_qnid' => $request->id,
                            'title' => $ents['title'] ?? '-',
                            'request_no' => $ents['request_no'] ?? '-',
                            'mudur' => $ents['firma_adi'] ?? '-',
                            'uploader' => $me->email ?? '-',
                            'note' => 'Revize edilerek yeniden gönderildi',
                            'sys_code' => $GLOBALS['SYS_CODE'] ?? null,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('planlama requeue failed', ['msg' => $e->getMessage(), 'id' => $request->id]);
            }
        }

        $transferMode = $decoded['transfer_mode'] ?? null;
        if ($key === 'op-doc-order' && in_array($transferMode, ['at_once','partial'], true)) {
            $selectedItems = $decoded['selected_items'] ?? [];
            $itemSerials   = $decoded['item_serials'] ?? [];
            $transferRes = $this->docs->processOrderTransfer($request->id, $transferMode, $selectedItems, $itemSerials);
            if (!empty($transferRes['transfer_no'])) {
                $res['transfer_no'] = $transferRes['transfer_no'];
                $res['clone_qnid']  = $transferRes['clone_qnid'] ?? null;
            }
            $res['transfer_msg'] = $transferRes['msg'] ?? null;

            if (($transferRes['success'] ?? false) === true) {
                try {
                    $orderQnidForNotif = $transferRes['clone_qnid'] ?? $request->id;
                    $payloadNotif = $this->buildOrderNotifPayload($orderQnidForNotif, [
                        'transfer_no'   => $transferRes['transfer_no'] ?? $orderQnidForNotif,
                        'transfer_mode' => $transferMode,
                        'fileTitle'     => 'Transfer dosyaları',
                    ]);
                    $this->emails->sendTedarikOrderSent($payloadNotif);
                    $this->emails->sendTedarikFileWaiting($payloadNotif);
                } catch (\Throwable $e) {
                    Log::warning('tedarik-02/03 dispatch failed', ['msg'=>$e->getMessage(), 'qnid'=>$request->id]);
                }
            }
        }

        if ($key === 'op-doc-client' && session('type_key') === 'op-pert-reseller') {
            session(['currentStatus' => (new PersonsServiceProvider())->clientPermInfo(session('person_id'), session('type_key'))]);
        }

        if ($key === 'op-doc-client') {
            $clientInfo = array_values($res['detail']['formFormat']['op-doc-client-form'])[0]['entities'] ?? [];
            $clientContacts = array_filter($clientInfo, fn($k) => str_starts_with($k, 'cont_email') || str_starts_with($k, 'cont_phone'), ARRAY_FILTER_USE_KEY);
            $this->emails->sendClientChanged($clientContacts, $clientInfo);
            $this->docs->updatePersonClients($request->id, $clientInfo);
        }

        return response()->json(['success'=>($res['id'] ?? 0) > 0, 'data'=>$res]);
    }

    private function handleDestroy(Request $request, ?string $key)
    {
        if ($key === 'op-doc-order' && !docPermCheck('op-doc-order','cancel')) {
            return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır (per-05-04 İptal / Parça Sil)...'],403);
        }
        $res = $this->docs->removeContent($request->id);
        return response()->json(['success'=>$res['success'] ?? false]);
    }

    public function transaction(Request $request){
        if(session('type_key') != 'op-pert-admin' && strtoupper($request->method()) != 'GET') return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],403);
       
        switch(strtoupper($request->method())){
            case "PUT":
                break;
            case "DELETE":
                $res =  $this->docs->removeTransaction($request->id);
                return response()->json(['success' => $res['success']]);
        }
        return response()->json(['success'=>false,'msg'=>'Desteklenmeyen metod'],405);
    }

    public function setStatus(Request $request){
        $validateUser = Validator::make($request->all(),[
            'id'       => 'required|uuid',
            'op_key'   => 'required',
        ]);
                
        if (!$this->isValidUuid($request->id)) {
            return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);
        }

        $formTmp = $this->docs->getFormData($request->id);
        $docOpKey = $formTmp['document']->op_key ?? null;
        if (!$docOpKey) {
            return response()->json(['success'=>false,'msg'=>'Belge bulunamadı'],404);
        }

        // Talep Dökümanı approval chain — per-key permission (generic docPermCheck 'status' does not cover these):
        // man-approved/man-rejected = Müdürlük Talep Onay (per-041-05-01), std-approved = Standardizasyon Yöneticiliği (per-041-05-04)
        if ($docOpKey === 'op-doc-requestf' && in_array($request->op_key, ['tr-request-man-approved','tr-request-man-rejected','tr-request-std-approved'], true)) {
            if ($validateUser->fails()) {
                return response()->json(['success' => false, 'message' => 'Missing Parameters', 'error' => $validateUser->errors()],422);
            }
            $need = $request->op_key === 'tr-request-std-approved' ? 'per-041-05-04' : 'per-041-05-01';
            if (!checkPerm($need)) {
                return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır ('.$need.')...'],403);
            }
        } else if ($docOpKey === 'op-doc-order' && in_array($request->op_key, ['doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_files_rejected'], true)) {
            // Kalite / Red requires admin file approver — supplier send (transfer_sent/ready) stays per-05-03 via generic map.
            // Frontend OForm::canKalite already gates per-07-02; backend enforces same so reseller cannot self-approve via API.
            if ($validateUser->fails()) {
                return response()->json(['success'=>false,'message'=>'Missing Parameters','error'=>$validateUser->errors()],422);
            }
            $authUser = auth('sanctum')->user() ?? auth()->user();
            if (!$this->perms->has($authUser, 'per-07-02')) {
                return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır (per-07-02 Kalite Onayı)...'],403);
            }
        } else {
            $validated = docPermCheck($docOpKey,'status');
            if($validateUser->fails() || !$validated){
                return response()->json([
                    'success' => false,
                    'message' => 'Missing Parameters',
                    'error'   => $validateUser->errors()
                ],422);
            }
        }

        $response = $this->docs->setStatus($request->id,$request->op_key,$request->note);
        if(!($response['success'] ?? false)){
            return response()->json($response, 422);
        }
        if(($response['detail']['document']->op_key ?? null) == 'op-doc-order' && in_array($request->op_key, self::TEDARIK_ORDER_APPROVED_STATUSES, true)){
            try{
                $detail = $response['detail'] ?? $this->docs->getFormData($request->id);
                $payloadNotif = $this->buildOrderNotifPayload($request->id, [
                    'order_no'  => $this->extractOrderNo($detail) ?? $request->id,
                    'note'      => $request->note ?? '',
                ]);
                if(in_array($request->op_key, ['doc_trans_order_rejected','doc_trans_order_files_rejected'], true)){
                    $this->emails->sendTedarikOrderRejected($payloadNotif);
                } else {
                    $this->emails->sendTedarikOrderApproved($payloadNotif);
                }
            }catch(\Throwable $e){
                Log::warning('tedarik-06/07 dispatch failed', ['msg'=>$e->getMessage(), 'id'=>$request->id]);
            }
        }
        // Pipeline step 3: Talep Dökümanı man-approve → 05-04 group; man-reject → uploader directly
        if(($response['detail']['document']->op_key ?? null) == 'op-doc-requestf' && in_array($request->op_key, ['tr-request-man-approved','tr-request-man-rejected'], true)){
            try{
                $detail = $response['detail'] ?? $this->docs->getFormData($request->id);
                $ents = [];
                foreach(($detail['formFormat']['op-doc-requestf-form'] ?? []) as $cr){
                    foreach(($cr['entities'] ?? []) as $k=>$v) if(!isset($ents[$k])) $ents[$k]=$v;
                }
                $me = auth()->user();
                $base = [
                    'qnid' => $request->id,
                    'talep_qnid' => $request->id,
                    'title' => $ents['title'] ?? '-',
                    'request_no' => $ents['request_no'] ?? '-',
                    'mudur' => $ents['firma_adi'] ?? '-',
                    'actor' => $me->email ?? '-',
                    'note' => $request->note ?? '',
                    'sys_code' => $GLOBALS['SYS_CODE'] ?? null,
                ];
                if($request->op_key === 'tr-request-man-approved'){
                    $this->emails->sendPlanlamaTalepApproved($base);
                } else {
                    $doc = \App\Models\Documents::where('qnid', $request->id)->first();
                    $uploaderEmail = null;
                    if ($doc && is_numeric($doc->person_id)) {
                        $uploaderEmail = \Illuminate\Support\Facades\DB::table('users')->where('person_id', (int)$doc->person_id)->where('status', 1)->value('email');
                    }
                    $base['uploader_email'] = $uploaderEmail;
                    $this->emails->sendPlanlamaTalepRejected($base);
                }
            }catch(\Throwable $e){
                Log::warning('planlama-02/03 dispatch failed', ['msg'=>$e->getMessage(), 'id'=>$request->id]);
            }
        }
        return $response;
    }

    private function extractOrderNo(array $detail): ?string
    {
        $ents = [];
        foreach(($detail['formFormat']['op-doc-order-form'] ?? []) as $cr){
            foreach(($cr['entities'] ?? []) as $k=>$v) if(!isset($ents[$k])) $ents[$k]=$v;
        }
        return $ents['order_no'] ?? null;
    }

    // Legacy offers — removed in new system (clients/orders/items only).
    // Stub keeps old routes from 500-ing; new code must not call these.
    public function cancelOffer(Request $request){ return response()->json(['success'=>false,'msg'=>'Offers removed in new system'],410); }
    public function reopenOffer(Request $request){ return response()->json(['success'=>false,'msg'=>'Offers removed in new system'],410); }

    public function cancelOrder(Request $request){
        $validateUser = Validator::make($request->all(),['id' => 'required|uuid']);
        if($validateUser->fails()){
            return response()->json(['success'=>false,'msg'=>'Missing Parameters','error'=>$validateUser->errors()],422);
        }
        if(!docPermCheck('op-doc-order','cancel')){
            return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır (per-05-04 İptal / Parça Sil)...'],403);
        }
        $form = $this->docs->getFormData($request->id);
        $document = $form['document'] ?? null;
        if(!is_object($document) || ($document->op_key ?? null) !== 'op-doc-order'){
            return response()->json(['success'=>false,'msg'=>'Sipariş bulunamadı veya bu belge tipi iptal edilemez.'],422);
        }
        $response = $this->docs->cancelOrder($request->id,$request->note);
        return response()->json($response, ($response['success'] ?? false) ? 200 : 422);
    }

    public function renameOrder(Request $request){
        $validate = Validator::make($request->all(),['id' => 'required|uuid','order_no' => 'required|string|max:64']);
        if($validate->fails()){
            return response()->json(['success'=>false,'msg'=>'Missing Parameters','error'=>$validate->errors()],422);
        }
        if(!docPermCheck('op-doc-order','rename')){
            return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır (per-05-05 Numara Düzenle)...'],403);
        }
        $form = $this->docs->getFormData($request->id);
        $document = $form['document'] ?? null;
        if(!is_object($document) || ($document->op_key ?? null) !== 'op-doc-order'){
            return response()->json(['success'=>false,'msg'=>'Sipariş bulunamadı veya bu belge tipi düzenlenemez.'],422);
        }
        $response = $this->docs->renameOrder($request->id, $request->order_no);
        return response()->json($response, ($response['success'] ?? false) ? 200 : 422);
    }

    public function setFileStatus(Request $request){
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),['id'=>'required','op_key'=>'required']);
        if($validateUser->fails() || !$this->perms->has($authUser, 'per-07-02')){
            return response()->json(['success'=>false,'message'=>'Missing Parameters','error'=>$validateUser->errors()],422);
        }
        $result = $this->docs->documentFileStatus($request->id,$request->op_key,$request->note);
        if($result['success']){
            refreshAllUserPermissions();
        }
        if($result['success']){
            $payload = ['type'=>'cliFileStatus','contacts'=>[],'status'=>$result['data'],'fileTitle'=>$result['fileTitle'],'note'=>$result['note'] ?? ''];
            foreach ($result['connections'] as $row) {
                if(strpos($row->entity_tag, 'cont_email') !== false || strpos($row->entity_tag, 'cont_phone') !== false){
                    $payload['contacts'][$row->entity_tag] = $row->entity_value;
                }
                if(strpos($row->entity_tag, 'title') !== false || strpos($row->entity_tag, 'clicode') !== false ){
                    $payload[$row->entity_tag]= $row->entity_value;
                }
            }
            $this->emails->sendClientFileStatus($payload);
        }
        if($result['success'] && in_array($request->op_key, self::TEDARIK_FILE_STATUSES, true)){
            try{
                $orderDoc = $this->resolveFileOrderDoc($request->id);
                if($orderDoc){
                    $payloadNotif = $this->buildOrderNotifPayload($orderDoc->qnid, [
                        'fileTitle' => $result['fileTitle'] ?? 'Dosya',
                        'note'      => $result['note'] ?? '',
                        'file_qnid' => $request->id,
                    ]);
                    if($request->op_key === 'doc_file_accepted'){
                        $this->emails->sendTedarikFileApproved($payloadNotif);
                    } else {
                        $this->emails->sendTedarikFileRejected($payloadNotif);
                    }
                }
            }catch(\Throwable $e){
                Log::warning('tedarik-04/05 dispatch failed', ['msg'=>$e->getMessage(), 'file'=>$request->id]);
            }
        }
        return $result;
    }
    
    
    public function tempUpload(Request $request){
        if(!$request->hasFile('file')){
            return response()->json(['success'=>false,'msg'=>'Dosya bulunamadı'],422);
        }
        $file = $request->file('file');
        $validUntil = $request->input('valid_until');
        $result = tempUploadFile($file, $validUntil ?: null);
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function setFileStatusAll(Request $request){
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),['id'=>'required','op_key'=>'required']);
        if($validateUser->fails() || !$this->perms->has($authUser, 'per-07-02')){
            return response()->json(['success'=>false,'message'=>'Missing Parameters','error'=>$validateUser->errors()],422);
        }
        $files = $this->docs->getDocumentFiles($request->id);
        $result = ['success' => false];
        $anySuccess = false;
        $tedarikPending = [];
        if($files['success']){
            foreach($files['data'] as $file){
                $result = $this->docs->documentFileStatus($file->qnid,$request->op_key,$request->note);
                if($result['success']){
                    $anySuccess = true;
                    $payload = ['type'=>'cliFileStatus','contacts'=>[],'status'=>$result['data'],'fileTitle'=>$result['fileTitle'],'note'=>$result['note'] ?? ''];
                    foreach ($result['connections'] as $row) {
                        if(strpos($row->entity_tag, 'cont_email') !== false || strpos($row->entity_tag, 'cont_phone') !== false){
                            $payload['contacts'][$row->entity_tag] = $row->entity_value;
                        }
                        if(strpos($row->entity_tag, 'title') !== false || strpos($row->entity_tag, 'clicode') !== false ){
                            $payload[$row->entity_tag]= $row->entity_value;
                        }
                    }
                    $this->emails->sendClientFileStatus($payload);

                    if(in_array($request->op_key, self::TEDARIK_FILE_STATUSES, true)){
                        try{
                            $orderDoc = $this->resolveFileOrderDoc($file->qnid);
                            if($orderDoc){
                                $payloadTmp = $this->buildOrderNotifPayload($orderDoc->qnid, [
                                    'note' => $request->note ?? '',
                                    'fileTitle' => $result['fileTitle'] ?? 'Dosya',
                                ]);
                                $qnidKey = $orderDoc->qnid;
                                if(!isset($tedarikPending[$qnidKey])){
                                    $tedarikPending[$qnidKey] = array_merge($payloadTmp, ['fileTitles'=>[]]);
                                }
                                $tedarikPending[$qnidKey]['fileTitles'][] = $result['fileTitle'] ?? 'Dosya';
                            }
                        }catch(\Throwable $e){
                            Log::warning('bulk tedarik-04/05 collect failed', ['msg'=>$e->getMessage(), 'file'=>$file->qnid]);
                        }
                    }
                }
            }
            if(!empty($tedarikPending)){
                foreach($tedarikPending as $payloadNotif){
                    $titles = array_values(array_unique(array_filter($payloadNotif['fileTitles'])));
                    $payloadNotif['fileTitle'] = count($titles) > 1 ? count($titles).' dosya: '.implode(', ', array_slice($titles,0,3)).(count($titles)>3?' …':'') : ($titles[0] ?? 'Dosya');
                    unset($payloadNotif['fileTitles']);
                    try{
                        if($request->op_key === 'doc_file_accepted'){
                            $this->emails->sendTedarikFileApproved($payloadNotif);
                        } else {
                            $this->emails->sendTedarikFileRejected($payloadNotif);
                        }
                    }catch(\Throwable $e){
                        Log::warning('bulk tedarik-04/05 dispatch failed', ['msg'=>$e->getMessage()]);
                    }
                }
            }
            if($anySuccess){
                refreshAllUserPermissions();
            }
        }
        return $result;
    }

    public function disableDocument(Request $request){
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),['id'=>'required']);
        if($validateUser->fails() || !$this->perms->has($authUser, 'per-07')){
            return response()->json(['success'=>false,'message'=>'Missing Parameters','error'=>$validateUser->errors()],422);
        }
        $result = $this->docs->disableDocument($request->id);
        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function fileDetail(Request $request){
        $fileQnid = $request->id;
        if(empty($fileQnid)) return response()->json(['success'=>false,'msg'=>'Missing id'],422);
        if(!$this->isValidUuid($fileQnid)) return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);

        $bundle = $this->docs->fetchFileDetailBundle($fileQnid);
        if(!$bundle) return response()->json(['success'=>false,'msg'=>'Belge bulunamadı'],404);
        if(isset($bundle['forbidden']) && $bundle['forbidden'] === true) return response()->json(['success'=>false,'msg'=>'Yetki yok'],403);

        return response()->json([
            'success'=>true,
            'data'=>[
                'file_qnid' => $bundle['file_qnid'],
                'order' => $bundle['orderHeader'],
                'items' => $bundle['items'],
                'files' => $bundle['files'],
            ]
        ]);
    }

    public function downloadAllOrderFiles(Request $request, $qnid = null){
        $orderQnid = $qnid ?? $request->input('qnid') ?? $request->input('id') ?? $request->route('qnid');
        if(empty($orderQnid)) return response()->json(['success'=>false,'msg'=>'Sipariş qnid gerekli'],422);
        if(!$this->isValidUuid($orderQnid)){
            return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);
        }
        $order = $this->docs->resolveOrderFromQnid($orderQnid);
        if(!$order || ($order->type_key ?? null) !== 'op-doc-order') return response()->json(['success'=>false,'msg'=>'Sipariş bulunamadı'],404);

        if(!$this->docs->canCurrentResellerAccessOrder($order)){
            return response()->json(['success'=>false,'msg'=>'Yetki yok'],403);
        }

        $orderId = $order->id;
        $files = $this->docs->fetchOrderFilesForDownload($orderId);
        if(empty($files)){
            return response()->json(['success'=>false,'msg'=>'İndirilecek form bulunamadı'],404);
        }

        $orderNo = $this->docs->fetchOrderNoById($orderId);
        $zipBase = preg_replace('/[^A-Za-z0-9_\-]/','-', $orderNo ?? $orderQnid);
        $zipName = 'order-'.$zipBase.'-tum-formlar.zip';
        $zipPath = sys_get_temp_dir().'/'.$zipName.'-'.uniqid().'.zip';
        $zip = new \ZipArchive();
        if($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true){
            return response()->json(['success'=>false,'msg'=>'ZIP oluşturulamadı'],500);
        }
        $enc = new \App\Providers\EncryptionProvider();
        $used = [];
        $added = 0;
        $skipped = 0;
        try {
            foreach($files as $f){
                try{
                    $plain = $enc->decrypt($f->file_desc);
                }catch(\Throwable $e){
                    $skipped++; continue;
                }
                $path = storage_path('app/public/documents/'.$plain);
                if(!file_exists($path)){
                    $alt = storage_path('app/public/temp/'.$plain);
                    if(file_exists($alt)) $path = $alt;
                    else { $skipped++; continue; }
                }
                $ext = pathinfo($plain, PATHINFO_EXTENSION);
                if(!$ext) $ext = 'pdf';
                $typeSlug = preg_replace('/[^A-Za-z0-9_\-]/','-', $f->file_type ?? 'form');
                $typeSlug = trim($typeSlug,'-');
                if($typeSlug==='') $typeSlug='form';
                $base = $typeSlug.'-'.substr($f->file_qnid,0,8).'.'.$ext;
                $name = $base; $c=1;
                while(isset($used[$name])){
                    $name = pathinfo($base, PATHINFO_FILENAME).'-'.$c++.'.'.$ext;
                }
                $used[$name]=true;
                $zip->addFile($path, $name);
                $added++;
            }
            $zip->close();
            if($added===0){
                @unlink($zipPath);
                return response()->json(['success'=>false,'msg'=>'Dosyalar diskte bulunamadı'],404);
            }
            Log::info('downloadAllOrderFiles', ['order_qnid'=>$orderQnid,'order_no'=>$orderNo ?? '', 'added'=>$added,'skipped'=>$skipped]);
            return response()->download($zipPath, 'order-'.$zipBase.'-tum-formlar.zip')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($zipPath);
            throw $e;
        }
    }

    public function listOrderFiles(Request $request, $qnid = null){
        $orderQnid = $qnid ?? $request->input('qnid') ?? $request->input('id') ?? $request->route('qnid');
        if(empty($orderQnid)) return response()->json(['success'=>false,'msg'=>'Sipariş qnid gerekli'],422);
        if(!$this->isValidUuid($orderQnid)){
            return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);
        }
        $order = $this->docs->resolveOrderFromQnid($orderQnid);
        if(!$order || ($order->type_key ?? null) !== 'op-doc-order') return response()->json(['success'=>false,'msg'=>'Sipariş bulunamadı'],404);

        if(!$this->docs->canCurrentResellerAccessOrder($order)){
            return response()->json(['success'=>false,'msg'=>'Yetki yok'],403);
        }

        $orderId = $order->id;
        $files = $this->docs->fetchOrderFilesWithStatus($orderId);
        foreach($files as &$f){
            if(is_string($f->last_status)){
                try{ $f->last_status = json_decode($f->last_status, true) ?? json_decode($f->last_status); }catch(\Throwable $e){}
            }
            try{
                $enc = new \App\Providers\EncryptionProvider();
                $plain = $enc->decrypt($f->file_desc);
                $f->display_name = $plain;
            }catch(\Throwable $e){ $f->display_name = $f->file_qnid; }
        }
        unset($f);
        return response()->json(['success'=>true,'data'=>['files'=>$files]]);
    }

    // Talep Dökümanı uploaded-excel history (version chain via replaced_id, newest first)
    // Talep Dökümanı uploaded-excel history, newest first.
    // EAV rule: every upload appends a NEW sys_con_entities row with the same
    // entity_tag, so one query by tag returns the full version history —
    // no replaced_id chain walking needed (same mechanic as old_versions).
    public function requestFiles(Request $request, $qnid = null){
        $q = $qnid ?? $request->input('qnid') ?? $request->input('id') ?? $request->route('qnid');
        if(empty($q)) return response()->json(['success'=>false,'msg'=>'Talep qnid gerekli'],422);
        if(!$this->isValidUuid($q)){
            return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);
        }
        $doc = \App\Models\Documents::where('qnid', $q)->first();
        if(!$doc) return response()->json(['success'=>false,'msg'=>'Belge bulunamadı'],404);
        $docOpKey = \App\Models\Sys_options::where('id', $doc->type_id)->value('op_key');
        if($docOpKey !== 'op-doc-requestf') return response()->json(['success'=>false,'msg'=>'Talep Dökümanı değil'],404);
        if(!docPermCheck('op-doc-requestf','read')){
            return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır...'],403);
        }

        $connIds = \App\Models\Sys_con_ops::where('main_id', $doc->id)->pluck('id');
        if($connIds->isEmpty()) return response()->json(['success'=>true,'data'=>['files'=>[]]]);

        // all entity rows for this slot, newest link first
        $fileIds = \Illuminate\Support\Facades\DB::table('sys_con_entities as se')
            ->whereIn('se.conn_id', $connIds)
            ->where('se.table_tag', 'document_files')
            ->where('se.entity_tag', 'like', 'talep_excel_file**%')
            ->orderByDesc('se.id')
            ->pluck('se.entity_value')
            ->map(fn($v) => is_numeric($v) ? (int)$v : null)
            ->filter()
            ->unique()
            ->values();
        if($fileIds->isEmpty()) return response()->json(['success'=>true,'data'=>['files'=>[]]]);

        $files = \App\Models\Document_files::whereIn('id', $fileIds)->orderByDesc('id')->get();

        // latest file transaction per file in ONE query (DISTINCT ON, newest first)
        $in = implode(',', $fileIds->map(fn($v) => (int)$v)->toArray());
        $lastRows = \Illuminate\Support\Facades\DB::select(
            "SELECT DISTINCT ON (t.target_id) t.target_id, so.op_key, so.title, t.created_at, t.note
             FROM transactions as t
             inner join sys_options as so on so.id = t.type_id
             WHERE t.target_id IN ($in) AND t.op_id = 1
             ORDER BY t.target_id, t.id DESC"
        );
        $lastMap = [];
        foreach($lastRows as $lr) $lastMap[(int)$lr->target_id] = $lr;

        $enc = new \App\Providers\EncryptionProvider();
        $out = [];
        foreach($files as $fr){
            try { $plain = $enc->decrypt($fr->description); } catch(\Throwable $e) { $plain = $fr->qnid; }
            $last = $lastMap[(int)$fr->id] ?? null;
            $out[] = [
                'id' => (int)$fr->id,
                'qnid' => $fr->qnid,
                'status' => (int)$fr->status,
                'replaced_id' => (int)($fr->replaced_id ?? 0),
                'display_name' => $plain,
                'created_at' => (string)$fr->created_at,
                'last_status' => $last ? ['op_key'=>$last->op_key,'title'=>$last->title,'created_at'=>$last->created_at,'note'=>$last->note] : null,
            ];
        }
        return response()->json(['success'=>true,'data'=>['files'=>$out]]);
    }

    // ---------------------------------------------------------------------
    // Supplier document library (shared files across tenders)
    // ---------------------------------------------------------------------

    private function currentRoleIsReseller(): bool
    {
        if (session('type_key') === 'op-pert-reseller') return true;
        $u = auth('sanctum')->user() ?? auth()->user();
        return $u && ($u->role === 'immutable-reseller');
    }

    private function currentSupplierList(): array
    {
        $list = session('currentStatus')['clientQnidList'] ?? [];
        if (! empty($list)) return $list;

        // Bearer-token requests have no session currentStatus — fall back to the
        // bound client entities of the authenticated person (fails closed).
        $u = auth('sanctum')->user() ?? auth()->user();
        if (! $u) return [];
        $formId = \App\Models\Sys_options::where('op_key', 'op-doc-user-client-form')->value('id');

        return \Illuminate\Support\Facades\DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->where('so.main_id', $u->person_id)
            ->where('so.type_id', $formId)
            ->where('se.entity_tag', 'like', 'cliid%')
            ->pluck('se.entity_value')
            ->all();
    }

    private function isReseller(): bool
    {
        return $this->currentRoleIsReseller();
    }

    private function canUseClientDocs(string $supplierQnid, bool $forWrite = false): bool
    {
        $auth = auth('sanctum')->user() ?? auth()->user();
        if (! $auth) return false;

        if ($this->isReseller()) {
            if (! in_array($supplierQnid, $this->currentSupplierList(), true)) return false;
            return $this->perms->has($auth, 'per-041-03-01');
        }

        // office / admin side
        if (! $this->perms->has($auth, 'per-041-03')) return false;
        return $forWrite ? ($this->perms->has($auth, 'per-041-03') || $this->perms->has($auth, 'per-041-03-01')) : true;
    }

    /** GET /v1/client-docs/{supplierQnid} — active slots + full version history */
    public function clientDocs(Request $request, $supplier)
    {
        $supplier = (string) $supplier;
        if (! $this->isValidUuid($supplier)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz tedarikçi kimliği'], 422);
        }
        if (! $this->canUseClientDocs($supplier)) {
            return response()->json(['success' => false, 'msg' => 'Bu evrak kütüphanesine erişim yetkiniz yok.'], 403);
        }

        $svc = new ClientDocsService();
        return response()->json($svc->listing($supplier));
    }

    /** POST /v1/client-docs/slot — upload/replace one shared file slot */
    public function clientDocsSlot(Request $request)
    {
        $supplier = (string) $request->input('supplier_qnid', '');
        if (! $this->isValidUuid($supplier)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz tedarikçi kimliği'], 422);
        }
        if (! $this->canUseClientDocs($supplier, true)) {
            return response()->json(['success' => false, 'msg' => 'Dosya yükleme yetkiniz yok.'], 403);
        }

        $reference = $request->input('reference');
        if (is_string($reference)) {
            $reference = json_decode($reference, true);
        }
        if (! is_array($reference)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz dosya referansı'], 422);
        }

        $svc = new ClientDocsService();
        $rsp = $svc->uploadSlot([
            'supplier_qnid' => $supplier,
            'supplier_title' => (string) $request->input('supplier_title', ''),
            'product_code' => (string) $request->input('product_code', ''),
            'standard' => (string) $request->input('standard', ''),
            'file_title' => (string) $request->input('file_title', ''),
            'kva' => (string) $request->input('kva', ''),
            'evrak_no' => (string) $request->input('evrak_no', ''),
            'reference' => $reference,
        ]);

        return response()->json($rsp, $rsp['success'] ? 200 : 422);
    }

    /** POST /v1/tender-products — server-side paginated product rows of one tender */
    public function tenderProducts(Request $request)
    {
        $auth = auth('sanctum')->user() ?? auth()->user();
        if (! $auth || (! $this->perms->has($auth, 'per-041-03') && ! $this->perms->has($auth, 'per-041-03-01'))) {
            return response()->json(['success' => false, 'msg' => 'Yetkiniz yok.'], 403);
        }

        $payload = json_decode((string) $request->input('tableReq', '{}'), true);
        if (! is_array($payload)) $payload = [];

        $qnid = (string) ($payload['qnid'] ?? '');
        if (! $this->isValidUuid($qnid)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz ihale kimliği'], 422);
        }

        $doc = \App\Models\Documents::where('qnid', $qnid)->first();
        $type = $doc ? \App\Models\Sys_options::where('id', $doc->type_id)->value('op_key') : null;
        if (! $doc || $type !== 'op-doc-ihale') {
            return response()->json(['success' => false, 'msg' => 'İhale bulunamadı'], 404);
        }

        $formId = \App\Models\Sys_options::where('op_key', 'op-doc-ihale-form')->value('id');
        $connId = \App\Models\Sys_con_ops::where('main_id', $doc->id)->where('type_id', $formId)->where('conn_id', 0)->value('id');
        $entities = $connId
            ? \App\Models\Sys_con_entities::where('conn_id', $connId)->where('entity_tag', 'like', '%**ihale_product_rows**%')->get(['entity_tag', 'entity_value'])
            : collect();

        $rows = [];
        foreach ($entities as $e) {
            $parts = explode('**', $e->entity_tag);
            if (count($parts) < 3) continue;
            $rowKey = $parts[2];
            $rows[$rowKey][$parts[0]] = $e->entity_value;
        }

        $list = [];
        foreach ($rows as $rowKey => $r) {
            if (empty($r['product_qnid'])) continue;
            $list[] = [
                'row_key' => $rowKey,
                'product_qnid' => $r['product_qnid'],
                'product_title' => $r['product_title'] ?? $r['product_qnid'],
                'quantity' => $r['quantity'] ?? '',
                'unit' => $r['unit'] ?? '',
            ];
        }

        // enrich ALL rows with product code + category (one batch query, then search can use prod_code)
        $allQnids = array_column($list, 'product_qnid');
        $prodDocs = \App\Models\Documents::whereIn('qnid', $allQnids)->get(['id', 'qnid'])->keyBy('qnid');
        $prodFormId = \App\Models\Sys_options::where('op_key', 'op-doc-product-form')->value('id');
        $prodConns = \App\Models\Sys_con_ops::whereIn('main_id', $prodDocs->pluck('id')->all())
            ->where('type_id', $prodFormId)->where('conn_id', 0)->pluck('id', 'main_id');
        $prodEnts = \App\Models\Sys_con_entities::whereIn('conn_id', $prodConns->values()->all())
            ->whereIn('entity_tag', ['prod_code', 'category_qnid', 'category_title'])
            ->get(['conn_id', 'entity_tag', 'entity_value']);

        $byConn = [];
        foreach ($prodEnts as $e) $byConn[$e->conn_id][$e->entity_tag] = $e->entity_value;

        foreach ($list as &$r) {
            $pdoc = $prodDocs[$r['product_qnid']] ?? null;
            $pconn = $pdoc ? ($prodConns[$pdoc->id] ?? null) : null;
            $meta = $pconn ? ($byConn[$pconn] ?? []) : [];
            $r['prod_code'] = $meta['prod_code'] ?? '';
            $r['category_qnid'] = $meta['category_qnid'] ?? '';
            $r['category_title'] = $meta['category_title'] ?? '';
        }
        unset($r);

        // search (PickleTable sends filter:[{key:'all',value:'...'}]) — title + product code, never qnid
        $search = '';
        foreach (($payload['filter'] ?? []) as $f) {
            if (($f['key'] ?? '') === 'all') $search = trim((string) ($f['value'] ?? ''));
        }
        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $list = array_values(array_filter($list, function ($r) use ($needle) {
                $hay = mb_strtolower(($r['product_title'] ?? '').' '.($r['prod_code'] ?? ''), 'UTF-8');
                return mb_strpos($hay, $needle) !== false;
            }));
        }

        $total = count($list);
        $page = max(1, (int) ($payload['scale']['page'] ?? 1));
        $limit = max(1, (int) ($payload['scale']['limit'] ?? 10));
        $pageRows = array_slice($list, ($page - 1) * $limit, $limit);

        $out = [];
        foreach ($pageRows as $r) {
            $out[] = [
                'id' => $r['product_qnid'],
                'row_key' => $r['row_key'],
                'product_qnid' => $r['product_qnid'],
                'product_title' => $r['product_title'],
                'quantity' => $r['quantity'],
                'unit' => $r['unit'],
                'prod_code' => $r['prod_code'] ?? '',
                'category_qnid' => $r['category_qnid'] ?? '',
                'category_title' => $r['category_title'] ?? '',
            ];
        }

        return response()->json([
            'data' => $out,
            'totalCount' => $total,
            'filteredCount' => count($out),
            'pageCount' => $limit > 0 ? (int) ceil($total / $limit) : 1,
            'last_page' => $limit > 0 ? (int) ceil($total / $limit) : 1,
        ]);
    }

    private function tenderLastStatus(int $docId): ?string
    {
        return \Illuminate\Support\Facades\DB::table('transactions as t')
            ->join('sys_options as so', 'so.id', '=', 't.type_id')
            ->where('t.target_id', $docId)
            ->where('so.group_key', 'op-trans-op-doc-ihale')
            ->orderByDesc('t.id')
            ->value('so.op_key');
    }

    /** POST /v1/tenders/activate — tender-created → tender-activated (offers open) */
    public function activateTender(Request $request)
    {
        $auth = auth('sanctum')->user() ?? auth()->user();
        $qnid = (string) $request->input('id', '');
        if (! $this->isValidUuid($qnid)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz ihale kimliği'], 422);
        }
        if (! $auth || ! $this->perms->has($auth, 'per-041-03')) {
            return response()->json(['success' => false, 'msg' => 'Yetkiniz yok.'], 403);
        }

        $doc = \App\Models\Documents::where('qnid', $qnid)->first();
        $type = $doc ? \App\Models\Sys_options::where('id', $doc->type_id)->value('op_key') : null;
        if (! $doc || $type !== 'op-doc-ihale') {
            return response()->json(['success' => false, 'msg' => 'İhale bulunamadı'], 404);
        }

        $last = $this->tenderLastStatus((int) $doc->id);
        if ($last === 'tender-activated' || $last === 'tender-complated') {
            return response()->json(['success' => false, 'msg' => 'İhale zaten aktif veya tamamlanmış.'], 422);
        }

        $status = $this->docs->setStatus($qnid, 'tender-activated', 'İhale aktif edildi — teklif alımı açıldı');

        return response()->json([
            'success' => ! empty($status['success']),
            'msg' => $status['msg'] ?? null,
        ], ! empty($status['success']) ? 200 : 422);
    }

    /** POST /v1/tenders/complete — tender-activated → tender-complated (winner + frozen offers) */
    public function completeTender(Request $request)
    {
        $auth = auth('sanctum')->user() ?? auth()->user();
        $qnid = (string) $request->input('id', '');
        $winnerQnid = (string) $request->input('winner_supplier_qnid', '');
        $winnerTitle = (string) $request->input('winner_supplier_title', '');

        if (! $this->isValidUuid($qnid)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz ihale kimliği'], 422);
        }
        if (! $this->isValidUuid($winnerQnid)) {
            return response()->json(['success' => false, 'msg' => 'Kazanan tedarikçi seçilmedi'], 422);
        }
        if (! $auth || ! $this->perms->has($auth, 'per-041-03')) {
            return response()->json(['success' => false, 'msg' => 'Yetkiniz yok.'], 403);
        }

        $doc = \App\Models\Documents::where('qnid', $qnid)->first();
        $type = $doc ? \App\Models\Sys_options::where('id', $doc->type_id)->value('op_key') : null;
        if (! $doc || $type !== 'op-doc-ihale') {
            return response()->json(['success' => false, 'msg' => 'İhale bulunamadı'], 404);
        }

        return $this->completeTenderWithWinner($doc, $winnerQnid, $winnerTitle);
    }

    /** Shared completion: winner entities + offer snapshots + tender-complated */
    private function completeTenderWithWinner(\App\Models\Documents $doc, string $winnerQnid, string $winnerTitle)
    {
        $last = $this->tenderLastStatus((int) $doc->id);
        if ($last === 'tender-complated') {
            return response()->json(['success' => false, 'msg' => 'İhale zaten tamamlanmış.'], 422);
        }
        if ($last !== 'tender-activated') {
            return response()->json(['success' => false, 'msg' => 'İhale önce aktif edilmeli.'], 422);
        }

        $svc = new ClientDocsService();

        // Winner must have a real offer on this tender
        $offers = \Illuminate\Support\Facades\DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->join('documents as d', 'd.id', '=', 'so.main_id')
            ->join('sys_options as sp', 'sp.id', '=', 'd.type_id')
            ->where('sp.op_key', 'op-doc-ihale-offer')
            ->where('se.entity_tag', 'ihale_qnid')
            ->where('se.entity_value', $doc->qnid)
            ->pluck('d.qnid')
            ->unique()
            ->values();

        $winnerOfferQnid = null;
        foreach ($offers as $offerQnid) {
            $details = $svc->offerSupplier($offerQnid);
            if ($details && $details['supplier_qnid'] === $winnerQnid) {
                $winnerOfferQnid = $offerQnid;
                break;
            }
        }
        if (! $winnerOfferQnid) {
            return response()->json(['success' => false, 'msg' => 'Seçilen tedarikçinin bu ihaleye teklifi yok'], 422);
        }

        if ($winnerTitle === '') {
            $winnerTitle = (string) \Illuminate\Support\Facades\DB::table('documents')->where('qnid', $winnerQnid)->value('title');
        }

        // Freeze winner on the tender header (EAV)
        $formId = \App\Models\Sys_options::where('op_key', 'op-doc-ihale-form')->value('id');
        $connId = \App\Models\Sys_con_ops::where('main_id', $doc->id)->where('type_id', $formId)->where('conn_id', 0)->value('id');
        if ($connId) {
            foreach ([
                'winner_supplier_qnid' => $winnerQnid,
                'winner_supplier_title' => $winnerTitle,
                'winner_offer_qnid' => $winnerOfferQnid,
            ] as $tag => $value) {
                \Illuminate\Support\Facades\DB::table('sys_con_entities')->updateOrInsert(
                    ['conn_id' => $connId, 'entity_tag' => $tag, 'table_tag' => 'sys_con_ops'],
                    ['entity_value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        $snap = $svc->snapshotOffers($doc->qnid);
        $status = $this->docs->setStatus($doc->qnid, 'tender-complated', 'İhale tamamlandı — kazanan: '.$winnerTitle.'; teklifler kilitlendi');

        return response()->json([
            'success' => ! empty($status['success']),
            'msg' => $status['msg'] ?? null,
            'winner_offer_qnid' => $winnerOfferQnid,
            'winner_supplier_qnid' => $winnerQnid,
            'winner_supplier_title' => $winnerTitle,
            'snapshot' => $snap,
        ], ! empty($status['success']) ? 200 : 422);
    }

    /** Offer header context: tender, supplier, current offer status */
    private function offerContext(string $offerQnid): ?array
    {
        $row = \Illuminate\Support\Facades\DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->join('documents as d', 'd.id', '=', 'so.main_id')
            ->join('sys_options as sp', 'sp.id', '=', 'd.type_id')
            ->where('d.qnid', $offerQnid)
            ->where('sp.op_key', 'op-doc-ihale-offer')
            ->where('se.entity_tag', 'ihale_qnid')
            ->select('d.id as doc_id', 'd.qnid', 'so.id as conn_id', 'se.entity_value as ihale_qnid')
            ->first();
        if (! $row) {
            return null;
        }

        $supplier = \Illuminate\Support\Facades\DB::table('sys_con_entities')
            ->where('conn_id', $row->conn_id)->where('entity_tag', 'supplier_qnid')->value('entity_value');
        $supplierTitle = \Illuminate\Support\Facades\DB::table('sys_con_entities')
            ->where('conn_id', $row->conn_id)->where('entity_tag', 'supplier_title')->value('entity_value');
        $status = \Illuminate\Support\Facades\DB::table('transactions as t')
            ->join('sys_options as so', 'so.id', '=', 't.type_id')
            ->where('t.target_id', $row->doc_id)
            ->where('so.group_key', 'op-trans-op-doc-ihale-offer')
            ->orderByDesc('t.id')
            ->value('so.op_key');

        return [
            'doc_id' => (int) $row->doc_id,
            'qnid' => $row->qnid,
            'conn_id' => (int) $row->conn_id,
            'ihale_qnid' => $row->ihale_qnid,
            'supplier_qnid' => $supplier,
            'supplier_title' => $supplierTitle,
            'status' => $status,
        ];
    }

    /** POST /v1/offers/accept — offer-accepted + tender-complated (winner = offer supplier) */
    public function acceptOffer(Request $request)
    {
        $auth = auth('sanctum')->user() ?? auth()->user();
        $qnid = (string) $request->input('id', '');
        if (! $this->isValidUuid($qnid)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz teklif kimliği'], 422);
        }
        if (! $auth || ! $this->perms->has($auth, 'per-041-03')) {
            return response()->json(['success' => false, 'msg' => 'Teklif değerlendirme yetkiniz yok.'], 403);
        }

        $ctx = $this->offerContext($qnid);
        if (! $ctx) {
            return response()->json(['success' => false, 'msg' => 'Teklif bulunamadı'], 404);
        }
        if ($ctx['status'] === 'offer-accepted') {
            return response()->json(['success' => false, 'msg' => 'Teklif zaten kabul edilmiş.'], 422);
        }

        $tender = \App\Models\Documents::where('qnid', $ctx['ihale_qnid'])->first();
        if (! $tender) {
            return response()->json(['success' => false, 'msg' => 'İhale bulunamadı'], 404);
        }
        if ($this->tenderLastStatus((int) $tender->id) !== 'tender-activated') {
            return response()->json(['success' => false, 'msg' => 'İhale aktif değil veya zaten tamamlanmış.'], 422);
        }

        // Accepting an offer completes the tender with that supplier as winner
        $complete = $this->completeTenderWithWinner($tender, (string) $ctx['supplier_qnid'], (string) $ctx['supplier_title']);
        $cdata = $complete->getData(true);
        if (empty($cdata['success'])) {
            return $complete;
        }

        $status = $this->docs->setStatus($qnid, 'offer-accepted', 'Teklif kabul edildi — kazanan seçildi');

        return response()->json([
            'success' => ! empty($status['success']),
            'msg' => $status['msg'] ?? null,
            'tender' => $cdata,
        ], ! empty($status['success']) ? 200 : 422);
    }

    /** POST /v1/offers/reject — offer-rejected with reason (supplier may fix and resend) */
    public function rejectOffer(Request $request)
    {
        $auth = auth('sanctum')->user() ?? auth()->user();
        $qnid = (string) $request->input('id', '');
        $note = trim((string) $request->input('note', ''));
        if (! $this->isValidUuid($qnid)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz teklif kimliği'], 422);
        }
        if (mb_strlen($note) < 3) {
            return response()->json(['success' => false, 'msg' => 'Red nedeni zorunludur.'], 422);
        }
        if (! $auth || ! $this->perms->has($auth, 'per-041-03')) {
            return response()->json(['success' => false, 'msg' => 'Teklif değerlendirme yetkiniz yok.'], 403);
        }

        $ctx = $this->offerContext($qnid);
        if (! $ctx) {
            return response()->json(['success' => false, 'msg' => 'Teklif bulunamadı'], 404);
        }
        if ($ctx['status'] === 'offer-accepted') {
            return response()->json(['success' => false, 'msg' => 'Kabul edilmiş teklif reddedilemez.'], 422);
        }

        $tender = \App\Models\Documents::where('qnid', $ctx['ihale_qnid'])->first();
        if (! $tender || $this->tenderLastStatus((int) $tender->id) !== 'tender-activated') {
            return response()->json(['success' => false, 'msg' => 'İhale aktif değil veya zaten tamamlanmış.'], 422);
        }

        $status = $this->docs->setStatus($qnid, 'offer-rejected', $note);

        return response()->json([
            'success' => ! empty($status['success']),
            'msg' => $status['msg'] ?? null,
        ], ! empty($status['success']) ? 200 : 422);
    }

    /** POST /v1/offers/file-reject — reject one offer file; auto-rejects the offer with the reason */
    public function rejectOfferFile(Request $request)
    {
        $auth = auth('sanctum')->user() ?? auth()->user();
        $offerQnid = (string) $request->input('id', '');
        $fileQnid = (string) $request->input('file_qnid', '');
        $fileIdIn = (int) $request->input('file_id', 0);
        $note = trim((string) $request->input('note', ''));

        if (! $this->isValidUuid($offerQnid)) {
            return response()->json(['success' => false, 'msg' => 'Geçersiz teklif kimliği'], 422);
        }
        if (mb_strlen($note) < 3) {
            return response()->json(['success' => false, 'msg' => 'Red nedeni zorunludur.'], 422);
        }
        if (! $auth || ! $this->perms->has($auth, 'per-041-03')) {
            return response()->json(['success' => false, 'msg' => 'Teklif değerlendirme yetkiniz yok.'], 403);
        }

        $ctx = $this->offerContext($offerQnid);
        if (! $ctx) {
            return response()->json(['success' => false, 'msg' => 'Teklif bulunamadı'], 404);
        }
        if ($ctx['status'] === 'offer-accepted') {
            return response()->json(['success' => false, 'msg' => 'Kabul edilmiş teklifin dosyası reddedilemez.'], 422);
        }
        $tender = \App\Models\Documents::where('qnid', $ctx['ihale_qnid'])->first();
        if (! $tender || $this->tenderLastStatus((int) $tender->id) !== 'tender-activated') {
            return response()->json(['success' => false, 'msg' => 'İhale aktif değil veya zaten tamamlanmış.'], 422);
        }

        // Resolve the file and make sure it belongs to this supplier's library
        $fileId = $fileIdIn;
        if (! $fileId && $fileQnid !== '') {
            $fileId = (int) \App\Models\Document_files::where('qnid', $fileQnid)->value('id');
        }
        if (! $fileId) {
            return response()->json(['success' => false, 'msg' => 'Dosya bulunamadı'], 404);
        }

        $svc = new ClientDocsService();
        $registry = $svc->registryDoc((string) $ctx['supplier_qnid']);
        $connId = $svc->connId($registry);
        $belongs = $connId && \Illuminate\Support\Facades\DB::table('sys_con_entities')
            ->where('conn_id', $connId)
            ->where('table_tag', 'document_files')
            ->where('entity_value', (string) $fileId)
            ->exists();
        if (! $belongs) {
            return response()->json(['success' => false, 'msg' => 'Bu dosya tedarikçinin evrak kütüphanesinde değil'], 422);
        }

        $typeId = \App\Models\Sys_options::where('op_key', 'offer-file-rejected')->value('id');
        if (! $typeId) {
            return response()->json(['success' => false, 'msg' => 'offer-file-rejected durumu tanımlı değil'], 422);
        }

        $actor = \App\Services\AuditService::actor();
        $log = \App\Models\UserLog::create([
            'user_id' => $actor['user_id'] ?? ($auth->id ?? 0),
            'sys_code' => $GLOBALS['SYS_CODE'] ?? $actor['sys_code'] ?? 'GDZ',
            'relation' => 'documents',
            'relation_id' => $ctx['doc_id'],
            'type_id' => $typeId,
            'description' => json_encode([
                'file_id' => $fileId,
                'file_qnid' => $fileQnid,
                'actor' => $actor,
                'desc' => 'Teklif dosyası reddedildi — teklif otomatik reddedildi',
                'note' => $note,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        \App\Models\Transactions::create([
            'op_id' => 1,
            'type_id' => $typeId,
            'log_id' => $log->id,
            'target_id' => $fileId,
            'note' => mb_substr($note, 0, 300),
            'description' => mb_substr(($actor['name'] ?? '').' <'.($actor['email'] ?? '').'> | '.$note, 0, 300),
        ]);

        // Auto-reject the offer with the same explanation
        $status = $this->docs->setStatus($offerQnid, 'offer-rejected', 'Dosya reddedildi: '.$note);

        return response()->json([
            'success' => ! empty($status['success']),
            'msg' => $status['msg'] ?? null,
            'file_id' => $fileId,
            'note' => $note,
        ], ! empty($status['success']) ? 200 : 422);
    }

}
