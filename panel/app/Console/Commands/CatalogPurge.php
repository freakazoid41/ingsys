<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Katalog domains temizligi: urun kategorileri, urunler, ihaleler, teklifler,
 * tedarikci evrak kutuphanesi (op-doc-client-docs) ve istege bagli olarak
 * kVA + kapsam tipi tanimlarini bagli EAV dosya/transaction/log satirlariyla
 * birlikte kalici olarak siler.
 *
 * Siparis/firma verisine dokunmaz.
 *
 * No foreign keys — delete order matters:
 *   sys_con_entities -> sys_con_ops -> transactions/user_logs ->
 *   document_files -> documents
 *
 * Kullanim:
 *   php artisan catalog:purge --dry-run
 *   php artisan catalog:purge --force
 *   php artisan catalog:purge --force --definitions   (kVA + kapsam tanimlari dahil)
 */
class CatalogPurge extends Command
{
    protected $signature = 'catalog:purge
                            {--dry-run : Hicbir sey silme, yalnizca raporla}
                            {--definitions : kVA ve kapsam tipi tanimlarini da sil}
                            {--force : Onay sorma (etkilesimsiz calistirma icin)}';

    protected $description = 'Urun kategorileri, urunler, ihale/teklif, evrak kutuphanesi ve istege bagli tanimlari bagli satirlariyla siler';

    private const OP_KEYS = [
        'op-doc-product-category',
        'op-doc-product',
        'op-doc-ihale',
        'op-doc-ihale-offer',
        'op-doc-ihale-offer-item',
        'op-doc-client-docs',
    ];

    private const DEFINITION_KEYS = [
        'op-doc-kva',
        'op-doc-scope-value',
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('dry-run') && ! $this->option('force')) {
            $this->error('Production ortaminda calistirmak icin --force gerekir.');
            return self::FAILURE;
        }

        $opKeys = self::OP_KEYS;
        if ($this->option('definitions')) {
            $opKeys = array_merge($opKeys, self::DEFINITION_KEYS);
        }

        $typeIds = DB::table('sys_options')->whereIn('op_key', $opKeys)->pluck('id')->all();
        if (! $typeIds) {
            $this->info('Katalog tipleri sozlukte yok — silinecek bir sey yok.');
            return self::SUCCESS;
        }

        $documents = DB::table('documents as d')
            ->join('sys_options as s', 's.id', '=', 'd.type_id')
            ->whereIn('d.type_id', $typeIds)
            ->select('d.id', 'd.qnid', 'd.status', 's.op_key')
            ->get();

        $documentIds = $documents->pluck('id')->all();

        $connIds = $documentIds
            ? DB::table('sys_con_ops')->whereIn('main_id', $documentIds)->pluck('id')->all()
            : [];

        $fileIds = $connIds
            ? DB::table('sys_con_entities')->whereIn('conn_id', $connIds)
                ->where('table_tag', 'document_files')->pluck('entity_value')->all()
            : [];

        $fileIds = array_merge($fileIds, $documentIds
            ? DB::table('document_files')->where('relation', 'documents')
                ->whereIn('relation_id', $documentIds)->pluck('id')->all()
            : []);

        $fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds))));

        // capture encrypted names BEFORE the rows are deleted (removeFile() looks the row up)
        $fileDescriptions = $fileIds
            ? DB::table('document_files')->whereIn('id', $fileIds)->pluck('description', 'id')->all()
            : [];

        $this->newLine();
        $this->line($this->option('dry-run') ? '=== KURU CALISMA ===' : '=== KATALOG TEMIZLIGI ===');

        if ($documents->isEmpty()) {
            $this->info('Silinecek belge bulunamadi.');
            return self::SUCCESS;
        }

        foreach ($documents->groupBy('op_key') as $opKey => $rows) {
            $this->line(sprintf('  %-28s %d belge (aktif: %d)', $opKey, $rows->count(), $rows->where('status', 1)->count()));
        }

        $entityCount = $connIds ? DB::table('sys_con_entities')->whereIn('conn_id', $connIds)->count() : 0;
        $this->line(sprintf('  %-28s %d', 'sys_con_ops', count($connIds)));
        $this->line(sprintf('  %-28s %d', 'sys_con_entities', $entityCount));
        $this->line(sprintf('  %-28s %d', 'transactions', DB::table('transactions')->whereIn('target_id', $documentIds ?: [0])->count()));
        $this->line(sprintf('  %-28s %d', 'user_logs', DB::table('user_logs')->where('relation', 'documents')->whereIn('relation_id', $documentIds ?: [0])->count()));
        $this->line(sprintf('  %-28s %d (diskteki dosyalariyla)', 'document_files', count($fileIds)));
        $this->newLine();

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Bu kayitlar kalici olarak silinecek. Devam edilsin mi?', false)) {
            $this->line('Iptal edildi, hicbir sey silinmedi.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($documentIds, $connIds, $fileIds) {
            if ($connIds) {
                DB::table('sys_con_entities')->whereIn('conn_id', $connIds)->delete();
                DB::table('sys_con_ops')->whereIn('id', $connIds)->delete();
            }
            DB::table('transactions')->whereIn('target_id', $documentIds)->delete();
            if ($fileIds) {
                DB::table('transactions')->where('op_id', 1)->whereIn('target_id', $fileIds)->delete();
                DB::table('document_files')->whereIn('id', $fileIds)->delete();
            }
            DB::table('user_logs')->where('relation', 'documents')->whereIn('relation_id', $documentIds)->delete();
            DB::table('documents')->whereIn('id', $documentIds)->delete();
        });

        foreach ($fileIds as $fileId) {
            // DB satiri gitti; kalan fiziksel dosyayi da temizle (description onceden alindi)
            try {
                if (empty($fileDescriptions[$fileId])) continue;
                $enc = new \App\Providers\EncryptionProvider();
                $path = storage_path('app/public').'/documents/'.$enc->decrypt($fileDescriptions[$fileId]);
                if (is_file($path)) @unlink($path);
            } catch (\Throwable $e) {
                $this->warn('  dosya silinemedi: '.$fileId.' — '.$e->getMessage());
            }
        }

        $this->info('Katalog temizligi tamamlandi.');
        return self::SUCCESS;
    }
}
