<?php

namespace App\Console\Commands;

use App\Models\Documents;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Talep ve teklif belgelerini bagli tum kayitlariyla birlikte kalici olarak siler.
 *
 * Firma (op-doc-client) belgelerine ve kullanicilara dokunmaz.
 *
 * Bu tablolarda foreign key yok, bu yuzden bagli satirlar elle ve dogru sirayla
 * temizleniyor; aksi halde artik EAV/transaction satirlari geride kalir.
 */
class PurgeDocuments extends Command
{
    protected $signature = 'documents:purge
                            {--type=all : Silinecek tip: request, offer veya all}
                            {--dry-run : Hicbir sey silme, yalnizca ne silinecegini raporla}
                            {--force : Onay sorma (etkilesimsiz calistirma icin)}';

    protected $description = 'Talep ve teklif belgelerini bagli kayitlariyla birlikte siler';

    private const TYPES = [
        'request' => 'op-doc-request',
        'offer' => 'op-doc-offer',
    ];

    public function handle(): int
    {
        $type = strtolower((string) $this->option('type'));
        $dryRun = (bool) $this->option('dry-run');

        $opKeys = $this->resolveTypes($type);
        if ($opKeys === null) {
            $this->error('Bilinmeyen tip: '.$type.'. Gecerli degerler: request, offer, all');

            return self::FAILURE;
        }

        // canliyi kazara silmeye karsi: --force olmadan production'da calismaz
        if (app()->environment('production') && ! $dryRun && ! $this->option('force')) {
            $this->error('Production ortaminda calistirmak icin --force gerekir.');

            return self::FAILURE;
        }

        $scope = $this->collectScope($opKeys);

        $this->report($scope, $opKeys, $dryRun);

        if ($scope['documents']->isEmpty()) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Bu kayitlar kalici olarak silinecek. Devam edilsin mi?', false)) {
            $this->line('Iptal edildi, hicbir sey silinmedi.');

            return self::SUCCESS;
        }

        $this->purge($scope);

        $this->info('Silme tamamlandi.');

        return self::SUCCESS;
    }

    /** @return string[]|null gecersiz tipte null */
    private function resolveTypes(string $type): ?array
    {
        if ($type === 'all') {
            return array_values(self::TYPES);
        }

        return isset(self::TYPES[$type]) ? [self::TYPES[$type]] : null;
    }

    /**
     * Silinecek her seyi tek seferde topla. Silme sirasinda id listesi degistigi icin
     * once toplayip sonra siliyoruz.
     */
    private function collectScope(array $opKeys): array
    {
        $documents = DB::table('documents as d')
            ->join('sys_options as s', 's.id', '=', 'd.type_id')
            ->whereIn('s.op_key', $opKeys)
            ->select('d.id', 'd.qnid', 's.op_key')
            ->get();

        $documentIds = $documents->pluck('id')->all();

        $connIds = $documentIds
            ? DB::table('sys_con_ops')->whereIn('main_id', $documentIds)->pluck('id')->all()
            : [];

        // dosyalar hem EAV kaydindan hem de relation sutunundan bulunabiliyor
        $fileIds = $connIds
            ? DB::table('sys_con_entities')->whereIn('conn_id', $connIds)
                ->where('table_tag', 'document_files')->pluck('entity_value')->all()
            : [];

        $fileIds = array_merge($fileIds, $documentIds
            ? DB::table('document_files')->where('relation', 'documents')
                ->whereIn('relation_id', $documentIds)->pluck('id')->all()
            : []);

        $fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds))));

        return [
            'documents' => $documents,
            'documentIds' => $documentIds,
            'connIds' => $connIds,
            'fileIds' => $fileIds,
        ];
    }

    private function report(array $scope, array $opKeys, bool $dryRun): void
    {
        $this->newLine();
        $this->line($dryRun ? '=== KURU CALISMA (hicbir sey silinmeyecek) ===' : '=== SILME ===');
        $this->line('Kapsam: '.implode(', ', $opKeys));
        $this->newLine();

        if ($scope['documents']->isEmpty()) {
            $this->info('Silinecek belge bulunamadi.');

            return;
        }

        foreach ($scope['documents']->groupBy('op_key') as $opKey => $rows) {
            $this->line(sprintf('  %-18s %d belge', $opKey, $rows->count()));
        }

        $entityCount = $scope['connIds']
            ? DB::table('sys_con_entities')->whereIn('conn_id', $scope['connIds'])->count()
            : 0;

        $this->newLine();
        $this->line('Birlikte silinecek bagli kayitlar:');
        $this->line(sprintf('  %-22s %d', 'sys_con_ops', count($scope['connIds'])));
        $this->line(sprintf('  %-22s %d', 'sys_con_entities', $entityCount));
        $this->line(sprintf('  %-22s %d', 'transactions', $this->transactionQuery($scope)->count()));
        $this->line(sprintf('  %-22s %d', 'user_logs', $this->userLogQuery($scope)->count()));
        $this->line(sprintf('  %-22s %d (diskteki dosyalariyla)', 'document_files', count($scope['fileIds'])));
        $this->newLine();
    }

    private function purge(array $scope): void
    {
        // diskteki dosyalar transaction disinda siliniyor: dosya sistemi geri alinamaz,
        // bu yuzden once DB'yi kesinlestirip sonra dosyalara dokunuyoruz
        DB::transaction(function () use ($scope) {
            if ($scope['connIds']) {
                DB::table('sys_con_entities')->whereIn('conn_id', $scope['connIds'])->delete();
                DB::table('sys_con_ops')->whereIn('id', $scope['connIds'])->delete();
            }

            $this->transactionQuery($scope)->delete();
            $this->userLogQuery($scope)->delete();

            if ($scope['fileIds']) {
                DB::table('document_files')->whereIn('id', $scope['fileIds'])->delete();
            }

            Documents::whereIn('id', $scope['documentIds'])->delete();
        });

        foreach ($scope['fileIds'] as $fileId) {
            // DB satiri gitti; kalan fiziksel dosyayi da temizle
            removeFile($fileId);
        }
    }

    /** Belge transaction'lari (op_id 0) ve dosya durum transaction'lari (op_id 1). */
    private function transactionQuery(array $scope)
    {
        return DB::table('transactions')->where(function ($q) use ($scope) {
            $q->whereIn('target_id', $scope['documentIds'] ?: [0]);

            if ($scope['fileIds']) {
                $q->orWhere(function ($f) use ($scope) {
                    $f->whereIn('target_id', $scope['fileIds'])->where('op_id', 1);
                });
            }
        });
    }

    private function userLogQuery(array $scope)
    {
        return DB::table('user_logs')
            ->where('relation', 'documents')
            ->whereIn('relation_id', $scope['documentIds'] ?: [0]);
    }
}
