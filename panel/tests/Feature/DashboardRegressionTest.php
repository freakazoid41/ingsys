<?php

namespace Tests\Feature;

use App\Providers\ReportServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class DashboardRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_dashboard_type_returns_404_not_500(): void
    {
        $this->expectException(NotFoundHttpException::class);

        (new ReportServiceProvider())->dashboardInfo('bilinmeyen-tip', null);
    }

    public function test_valid_dashboard_types_still_work(): void
    {
        $result = (new ReportServiceProvider())->dashboardInfo('topstats', null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('totalRequests', $result);
        $this->assertArrayHasKey('totalOffers', $result);
    }
}
