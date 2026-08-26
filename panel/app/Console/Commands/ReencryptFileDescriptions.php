<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Providers\EncryptionProvider;

class ReencryptFileDescriptions extends Command
{
    protected $signature = 'files:reencrypt-descriptions
                            {--dry-run : Sadece rapor, veritabanına yazma}
                            {--rollback= : Verilen yedek dosyasından eski değerleri geri yükle}';

    protected $description = 'document_files.description değerlerini kısa salt formatıyla yeniden şifreler, eski değerleri yedekler';

    public function handle()
    {
        if ($this->option('rollback')) {
            return $this->rollback($this->option('rollback'));
        }

        $enc    = new EncryptionProvider();
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('document_files')->select('id', 'description')->orderBy('id')->get();

        $backup  = [];
        $changes = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $json = json_decode(base64_decode($row->description ?? ''), true);

            // JSON formatı değilse zaten kompakt (veya bilinmeyen) — dokunma
            if (!is_array($json) || !isset($json['salt'])) {
                $skipped++;
                continue;
            }

            $plain = $enc->decrypt($row->description);
            if ($plain === false || $plain === null || $plain === '') {
                $this->warn("id {$row->id}: decrypt başarısız, atlandı");
                $skipped++;
                continue;
            }

            $new = $enc->encrypt($plain);
            if ($enc->decrypt($new) !== $plain) {
                $this->error("id {$row->id}: doğrulama başarısız, atlandı");
                $skipped++;
                continue;
            }

            $backup[]  = ['id' => $row->id, 'description' => $row->description];
            $changes[] = ['id' => $row->id, 'description' => $new];
        }

        $this->info(count($changes) . ' kayıt dönüştürülecek, ' . $skipped . ' kayıt atlandı.');

        if (empty($changes)) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry-run: veritabanına yazılmadı.');
            return self::SUCCESS;
        }

        // önce yedek yaz — yedek yazılamazsa hiçbir şey güncellenmez
        $backupPath = storage_path('app/reencrypt-backup-' . date('YmdHis') . '.json');
        if (file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT)) === false) {
            $this->error('Yedek dosyası yazılamadı: ' . $backupPath);
            return self::FAILURE;
        }
        $this->info('Yedek yazıldı: ' . $backupPath);

        $updated = 0;
        foreach ($changes as $change) {
            $updated += DB::table('document_files')
                ->where('id', $change['id'])
                ->update(['description' => $change['description']]);
        }

        $this->info($updated . ' kayıt güncellendi.');
        $this->info('Geri almak için: php artisan files:reencrypt-descriptions --rollback=' . $backupPath);

        return self::SUCCESS;
    }

    protected function rollback(string $path)
    {
        if (!is_file($path)) {
            $this->error('Yedek dosyası bulunamadı: ' . $path);
            return self::FAILURE;
        }

        $backup = json_decode(file_get_contents($path), true);
        if (!is_array($backup)) {
            $this->error('Yedek dosyası okunamadı veya bozuk.');
            return self::FAILURE;
        }

        $restored = 0;
        foreach ($backup as $row) {
            if (!isset($row['id'], $row['description'])) {
                continue;
            }
            $restored += DB::table('document_files')
                ->where('id', $row['id'])
                ->update(['description' => $row['description']]);
        }

        $this->info($restored . ' kayıt geri yüklendi.');

        return self::SUCCESS;
    }
}
