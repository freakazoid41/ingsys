<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationMailJob;
use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * clientActivation renders emails.verify-email directly instead of going through
     * MailService::renderHtmlMessage, which is where the sysCode fallback lives — so the
     * tenant code has to be threaded into the view explicitly.
     */
    public function test_client_activation_mail_renders_with_the_tenant_logo(): void
    {
        $job = new SendNotificationMailJob(['sys_code' => 'CATES']);

        $job->clientActivation([
            'name' => 'Test Kullanıcı',
            'email' => 'aktivasyon@test.local',
            'verify_url' => 'https://example.test/login',
        ]);

        $log = NotificationLog::latest('id')->first();
        $this->assertNotNull($log, 'Aktivasyon maili hiç oluşturulmadı');
        $this->assertStringContainsString('cates.jpg', $log->body);
    }

    public function test_client_activation_mail_uses_the_yatagan_logo_for_the_other_tenant(): void
    {
        $job = new SendNotificationMailJob(['sys_code' => 'YATAGAN']);

        $job->clientActivation([
            'name' => 'Test Kullanıcı',
            'email' => 'aktivasyon2@test.local',
            'verify_url' => 'https://example.test/login',
        ]);

        $log = NotificationLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('yatagan.jpg', $log->body);
    }

    /**
     * emails.layout is included from more than one place, so it must not hard-require sysCode.
     */
    public function test_mail_layout_renders_without_a_sys_code(): void
    {
        $html = view('emails.layout', ['content' => '<p>test</p>'])->render();

        $this->assertNotEmpty($html);
    }
}
