<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Tender front panel dashboard data (GET /v1/dashboard/ihale-stats).
 * Extracted from ReportServiceProvider (that provider was turning into a
 * god object). Supplier offer rows are scoped to bound clients.
 */
class TenderDashboardService
{
    private function ihaleLatestStatusSub(string $group)
    {
        return DB::table('transactions as t')
            ->join('sys_options as so', 'so.id', '=', 't.type_id')
            ->where('so.group_key', $group)
            ->selectRaw('distinct on (t.target_id) t.target_id, so.op_key as op_key')
            ->orderBy('t.target_id')
            ->orderByDesc('t.id');
    }

    public function stats(): array
    {
        $tenderType = (int) DB::table('sys_options')->where('op_key', 'op-doc-ihale')->value('id');
        $offerType = (int) DB::table('sys_options')->where('op_key', 'op-doc-ihale-offer')->value('id');
        if ($tenderType <= 0 || $offerType <= 0) {
            return ['tenders' => [], 'offers' => [], 'recentTenders' => [], 'recentOffers' => [], 'monthly' => []];
        }

        $isReseller = session('type_key') === 'op-pert-reseller';
        $clientQnids = [];
        if ($isReseller) {
            $currentStatus = session('currentStatus');
            $clientQnids = is_array($currentStatus) ? ($currentStatus['clientQnidList'] ?? []) : [];
        }

        $applyOfferScope = function ($query) use ($isReseller, $clientQnids) {
            if (! $isReseller) {
                return $query;
            }
            if (empty($clientQnids)) {
                return $query->whereRaw('1=0');
            }
            return $query->whereExists(function ($sub) use ($clientQnids) {
                $sub->select(DB::raw(1))
                    ->from('sys_con_ops as so')
                    ->join('sys_con_entities as se', 'se.conn_id', '=', 'so.id')
                    ->whereColumn('so.main_id', 'd.id')
                    ->where('se.entity_tag', 'supplier_qnid')
                    ->whereIn('se.entity_value', $clientQnids);
            });
        };

        $tenderRows = DB::table('documents as d')
            ->leftJoinSub($this->ihaleLatestStatusSub('op-trans-op-doc-ihale'), 'lt', 'lt.target_id', '=', 'd.id')
            ->where('d.type_id', $tenderType)
            ->where('d.status', 1)
            ->selectRaw("coalesce(lt.op_key, 'none') as status, count(*) as cnt")
            ->groupByRaw("coalesce(lt.op_key, 'none')")
            ->pluck('cnt', 'status')
            ->all();

        $offerRows = $applyOfferScope(
            DB::table('documents as d')
                ->leftJoinSub($this->ihaleLatestStatusSub('op-trans-op-doc-ihale-offer'), 'lt', 'lt.target_id', '=', 'd.id')
                ->where('d.type_id', $offerType)
                ->where('d.status', 1)
                ->selectRaw("coalesce(lt.op_key, 'none') as status, count(*) as cnt")
                ->groupByRaw("coalesce(lt.op_key, 'none')")
        )->pluck('cnt', 'status')->all();

        $tenders = [
            'total' => array_sum($tenderRows),
            'created' => (int) ($tenderRows['tender-created'] ?? 0),
            'active' => (int) ($tenderRows['tender-activated'] ?? 0),
            'completed' => (int) ($tenderRows['tender-complated'] ?? 0),
        ];
        $offers = [
            'total' => array_sum($offerRows),
            'submitted' => (int) ($offerRows['offer-sended'] ?? 0),
            'rejected' => (int) ($offerRows['offer-rejected'] ?? 0),
            'accepted' => (int) ($offerRows['offer-accepted'] ?? 0),
        ];

        $recentTenders = DB::table('documents as d')
            ->leftJoinSub($this->ihaleLatestStatusSub('op-trans-op-doc-ihale'), 'lt', 'lt.target_id', '=', 'd.id')
            ->where('d.type_id', $tenderType)
            ->where('d.status', 1)
            ->orderByDesc('d.id')
            ->limit(5)
            ->get(['d.id', 'd.qnid', 'd.created_at', 'lt.op_key as status'])
            ->map(function ($row) {
                $ents = DB::table('sys_con_ops as so')
                    ->join('sys_con_entities as se', 'se.conn_id', '=', 'so.id')
                    ->where('so.main_id', $row->id)
                    ->where('so.conn_id', 0)
                    ->whereIn('se.entity_tag', ['ihale_kodu', 'teklif_son_tarihi'])
                    ->pluck('se.entity_value', 'se.entity_tag');
                $row->kod = $ents['ihale_kodu'] ?? '-';
                $row->deadline = $ents['teklif_son_tarihi'] ?? '';
                $row->product_count = DB::table('sys_con_ops as so')
                    ->join('sys_con_entities as se', 'se.conn_id', '=', 'so.id')
                    ->where('so.main_id', $row->id)
                    ->where('se.entity_tag', 'like', 'product_qnid**%')
                    ->count();
                return $row;
            })
            ->all();

        $recentOffersQuery = DB::table('documents as d')
            ->leftJoinSub($this->ihaleLatestStatusSub('op-trans-op-doc-ihale-offer'), 'lt', 'lt.target_id', '=', 'd.id')
            ->where('d.type_id', $offerType)
            ->where('d.status', 1)
            ->orderByDesc('d.id')
            ->limit(5);
        $recentOffers = $applyOfferScope($recentOffersQuery)
            ->get(['d.id', 'd.qnid', 'd.created_at', 'lt.op_key as status'])
            ->map(function ($row) {
                $ents = DB::table('sys_con_ops as so')
                    ->join('sys_con_entities as se', 'se.conn_id', '=', 'so.id')
                    ->where('so.main_id', $row->id)
                    ->where('so.conn_id', 0)
                    ->whereIn('se.entity_tag', ['ihale_kodu', 'supplier_title'])
                    ->pluck('se.entity_value', 'se.entity_tag');
                $row->kod = $ents['ihale_kodu'] ?? '-';
                $row->supplier_title = $ents['supplier_title'] ?? '-';
                return $row;
            })
            ->all();

        $start = now()->startOfMonth()->subMonths(5);
        $tenderMonthly = DB::table('documents')
            ->where('type_id', $tenderType)
            ->where('status', 1)
            ->where('created_at', '>=', $start)
            ->selectRaw("to_char(date_trunc('month', created_at), 'YYYY-MM') as ym, count(*) as cnt")
            ->groupByRaw("to_char(date_trunc('month', created_at), 'YYYY-MM')")
            ->pluck('cnt', 'ym')
            ->all();
        $offerMonthly = $applyOfferScope(
            DB::table('documents as d')
                ->where('d.type_id', $offerType)
                ->where('d.status', 1)
                ->where('d.created_at', '>=', $start)
                ->selectRaw("to_char(date_trunc('month', d.created_at), 'YYYY-MM') as ym, count(*) as cnt")
                ->groupByRaw("to_char(date_trunc('month', d.created_at), 'YYYY-MM')")
        )->pluck('cnt', 'ym')->all();

        $labels = [];
        $tenderSeries = [];
        $offerSeries = [];
        for ($i = 0; $i < 6; $i++) {
            $ym = $start->copy()->addMonths($i)->format('Y-m');
            $labels[] = $start->copy()->addMonths($i)->format('m/Y');
            $tenderSeries[] = (int) ($tenderMonthly[$ym] ?? 0);
            $offerSeries[] = (int) ($offerMonthly[$ym] ?? 0);
        }

        return [
            'tenders' => $tenders,
            'offers' => $offers,
            'recentTenders' => $recentTenders,
            'recentOffers' => $recentOffers,
            'monthly' => ['labels' => $labels, 'tenders' => $tenderSeries, 'offers' => $offerSeries],
            'scope' => $isReseller ? 'supplier' : 'admin',
        ];
    }


}
