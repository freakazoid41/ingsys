<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class VideoProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    private $path = "";
    private $stream = "";
    private $buffer = 1024 * 1024; // 1MB buffer size
    private $start = -1;
    private $end = -1;
    private $size = 0;

    function __construct($filePath)
    {
        $this->path = $filePath;
    }

    /**
     * Akışı açar ve dosya sunumunu başlatır.
     */
    public function start()
    {
        $this->open();
        $this->setHeader();
        $this->stream();
        $this->close();
    }

    /**
     * Dosya akışını açar.
     */
    private function open()
    {
        // Dosyanın varlığını kontrol et
        if (!file_exists($this->path) || !($this->stream = fopen($this->path, 'rb'))) {
            http_response_code(404);
            exit('Dosya bulunamadı veya okunamıyor.');
        }

        $this->size = filesize($this->path);
    }

    /**
     * Dosya akışını kapatır.
     */
    private function close()
    {
        fclose($this->stream);
    }

    /**
     * Range talebine göre uygun HTTP başlıklarını ayarlar.
     */
    private function setHeader()
    {
        ob_get_clean(); // Mevcut çıktı tamponlarını temizle
        
        // Genel başlıklar
        header("Content-Type: " . mime_content_type($this->path));
        header("Cache-Control: public, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Accept-Ranges: bytes"); // Byte Serving için hayati

        $this->start = 0;
        $this->end = $this->size - 1; // Dosyanın son bayt dizini

        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $this->start;
            $c_end = $this->end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            
            // Birden çok aralık talebini desteklemiyoruz
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }

            // --- HATA GİDERİCİ KISIM: Range Başlığını Ayrıştırma ---
            if (strpos($range, '-') !== false) {
                list($c_start, $c_end) = explode('-', $range);
                
                // Eğer $c_end boşsa (örn: bytes=1000-), dosyanın sonuna kadar git
                if ($c_end === '') {
                    $c_end = $this->end;
                } else {
                    $c_end = intval($c_end);
                }
                $c_start = intval($c_start);

            } elseif ($range[0] == '-') {
                // Sona göre aralık (örn: bytes=-500)
                $c_start = $this->size - intval(substr($range, 1));
                $c_end = $this->end;
            }
            // --------------------------------------------------------

            $c_end = ($c_end > $this->end) ? $this->end : $c_end;

            // Aralık sınırlarını kontrol et
            if ($c_start > $c_end || $c_start < 0 || $c_start > $this->size - 1 || $c_end >= $this->size) {
                 // 416 hatasını burada döndür, ancak toplam dosya boyutunu da belirt.
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes */" . $this->size);
                exit;
            }

            $this->start = $c_start;
            $this->end = $c_end;
            $length = $this->end - $this->start + 1;

            // Kısmi içerik başlıkları
            header('HTTP/1.1 206 Partial Content'); 
            header("Content-Length: " . $length);   
            header("Content-Range: bytes $this->start-$this->end/" . $this->size); 
        } else {
            // Range başlığı yok, tüm dosyayı 200 OK ile gönder
            header("Content-Length: " . $this->size);
            header('HTTP/1.1 200 OK');
        }
    }

    /**
     * Hesaplanan aralığa göre dosya içeriğini yayınlar.
     */
    private function stream()
    {
        $i = $this->start;
        set_time_limit(0); // Büyük dosyalar için zaman sınırını kaldır

        fseek($this->stream, $this->start);

        while (!feof($this->stream) && $i <= $this->end) {
            $bytesToRead = $this->buffer;
            if (($i + $bytesToRead) > $this->end) {
                $bytesToRead = $this->end - $i + 1;
            }

            $data = fread($this->stream, $bytesToRead);
            echo $data;
            flush(); // Çıktıyı hemen istemciye gönder
            $i += $bytesToRead;
        }
    }
}
