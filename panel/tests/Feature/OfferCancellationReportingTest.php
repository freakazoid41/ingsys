<?php

namespace Tests\Feature;

use App\Models\Documents;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\Transactions;
use App\Models\User;
use App\Providers\ReportServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cancelled offers must stay in the historical totals while disappearing from everything that
 * describes work still in flight: pending counters, notification badges and calendar reminders.
 */
class OfferCancellationReportingTest extends TestCase
{
    use RefreshDatabase;

    private function optionFor(string $opKey, string $groupKey): Sys_options
    {
        return Sys_options::firstOrCreate(
            ['op_key' => $opKey],
            [
                'title' => $opKey,
                'ttitle' => $opKey,
                'ctitle' => $opKey,
                'group_key' => $groupKey,
                'status' => 1,
            ]
        );
    }

    private function seedOffer(int $documentStatus): Documents
    {
        $docType = $this->optionFor('op-doc-offer', 'op-doc-forms');
        $formType = $this->optionFor('op-doc-offer-form', 'op-doc-forms');

        $document = Documents::create([
            'type_id' => $docType->id,
            'status' => $documentStatus,
            'title' => 'Teklif',
            'person_id' => '-',
            'grp_code' => 'GDZ',
        ]);

        $conn = Sys_con_ops::create([
            'main_id' => $document->id,
            'conn_id' => 0,
            'type_id' => $formType->id,
            'sub_type_id' => 0,
        ]);

        Sys_con_entities::create([
            'conn_id' => $conn->id,
            'table_tag' => 'sys_con_ops',
            'entity_tag' => 'cliid',
            'entity_value' => 'client-qnid-1',
        ]);

        return $document;
    }

    private function addOfferTransaction(Documents $document, string $opKey): void
    {
        $type = $this->optionFor($opKey, 'op-trans-op-doc-offer');

        Transactions::create([
            'op_id' => 0,
            'type_id' => $type->id,
            'log_id' => 0,
            'target_id' => $document->id,
            'note' => 'test',
            'description' => 'test',
        ]);
    }

    public function test_top_stats_keep_cancelled_offers_in_the_total_but_not_in_approved(): void
    {
        $active = $this->seedOffer(1);
        $this->addOfferTransaction($active, 'doc_trans_offer_approved');

        $cancelled = $this->seedOffer(0);
        $this->addOfferTransaction($cancelled, 'doc_trans_offer_approved');

        $stats = (new ReportServiceProvider)->dashboardInfo('topstats', null);

        $this->assertSame(2, $stats['totalOffers']);
        $this->assertSame(1, $stats['approvedOffers']);
    }

    public function test_awaiting_offers_ignores_cancelled_offers(): void
    {
        $this->seedOffer(0);

        $stats = (new ReportServiceProvider)->dashboardInfo('topstats', null);

        $this->assertSame(0, $stats['awaitingOffers']);
    }

    public function test_new_offer_notifications_ignore_cancelled_offers(): void
    {
        $this->seedOffer(0);

        $offers = (new ReportServiceProvider)->getOffers();

        $this->assertCount(0, $offers);
    }

    public function test_monthly_offer_chart_reports_cancelled_offers_as_their_own_group(): void
    {
        $active = $this->seedOffer(1);
        $this->addOfferTransaction($active, 'doc_trans_offer_approved');
        $this->seedOffer(0);

        $groups = collect((new ReportServiceProvider)->dashboardInfo('monthlyoffers', true))
            ->keyBy('key');

        $this->assertSame(1, $groups['cancelled']['value'] ?? null);
        $this->assertSame('İptal Edildi', $groups['cancelled']['label'] ?? null);
        $this->assertSame(1, $groups['doc_trans_offer_approved']['value'] ?? null);
    }

    public function test_calendar_reminders_ignore_cancelled_offers(): void
    {
        $cancelled = $this->seedOffer(0);
        $conn = Sys_con_ops::where('main_id', $cancelled->id)->first();
        Sys_con_entities::create([
            'conn_id' => $conn->id,
            'table_tag' => 'sys_con_ops',
            'entity_tag' => 'contract_end_date',
            'entity_value' => '15/'.date('m').'/'.date('Y'),
        ]);

        $events = (new ReportServiceProvider)->dashboardInfo('importantinfo', null);

        $this->assertCount(0, $events);
    }

    public function test_monthly_distribution_keeps_cancelled_offers_in_the_total(): void
    {
        $this->seedOffer(1);
        $this->seedOffer(0);

        $distribution = (new ReportServiceProvider)->dashboardInfo('monthlydistribution', null);

        $this->assertSame(2, $distribution['-']['totalOffers'] ?? null);
    }

    public function test_offer_export_still_produces_a_file_when_every_offer_is_cancelled(): void
    {
        $this->seedOffer(0);

        $user = User::create([
            'name' => 'Admin',
            'role' => 'admin',
            'email' => 'export@test.local',
            'password' => bcrypt('password'),
            'person_id' => 4242,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->withSession(['type_key' => 'op-pert-admin', 'person_id' => 4242])
            ->get('/export/offers');

        $response->assertOk();
    }

    public function test_offer_pdf_can_still_be_produced_for_a_cancelled_offer(): void
    {
        $document = $this->seedOffer(0);

        $user = User::create([
            'name' => 'Admin',
            'role' => 'admin',
            'email' => 'pdf@test.local',
            'password' => bcrypt('password'),
            'person_id' => 4242,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->withSession(['type_key' => 'op-pert-admin', 'person_id' => 4242])
            ->post('/export/offer', ['id' => $document->qnid]);

        $response->assertOk();
    }
}
