<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class EncryptionProvider extends ServiceProvider
{   

    public function __construct(){

    }
    /**
     * @link http://php.net/manual/en/function.openssl-get-cipher-methods.php Available methods.
     * @var string Cipher method. Recommended AES-128-CBC, AES-192-CBC, AES-256-CBC
     */
    protected $encryptMethod = 'AES-128-CBC';


    /**
     * Decrypt string.
     * 
     * @link https://stackoverflow.com/questions/41222162/encrypt-in-php-openssl-and-decrypt-in-javascript-cryptojs Reference.
     * @param string $encryptedString The encrypted string that is base64 encode.
     * @param string $key The key.
     * @return mixed Return original string value. Return null for failure get salt, iv.
     */
    public function decrypt($encryptedString, $key = 'pickle')
    {
        $json = json_decode(base64_decode($encryptedString), true);

        // eski format: base64(JSON{ciphertext, iv, salt, iterations})
        if (is_array($json) && isset($json['salt'], $json['iv'], $json['ciphertext'])) {
            try {
                $salt = hex2bin($json["salt"]);
                $iv = hex2bin($json["iv"]);
            } catch (Exception $e) {
                return null;
            }

            $cipherText = base64_decode($json['ciphertext']);

            $iterations = intval(abs($json['iterations']));
            if ($iterations <= 0) {
                $iterations = 999;
            }
            $hashKey = hash_pbkdf2('sha512', $key, $salt, $iterations, 128);
            unset($iterations, $json, $salt);

            $decrypted= openssl_decrypt($cipherText , $this->encryptMethod, hex2bin($hashKey), OPENSSL_RAW_DATA, $iv);
            unset($cipherText, $hashKey, $iv);

            return $decrypted;
        }

        // kompakt format: base64url(salt[16] . iv[16] . ciphertext)
        $bin = base64_decode(strtr($encryptedString, '-_', '+/'));
        if ($bin === false || strlen($bin) <= 32) {
            return null;
        }

        $salt       = substr($bin, 0, 16);
        $iv         = substr($bin, 16, 16);
        $cipherText = substr($bin, 32);

        $hashKey = hash_pbkdf2('sha512', $key, $salt, 999, 128);

        return openssl_decrypt($cipherText, $this->encryptMethod, hex2bin($hashKey), OPENSSL_RAW_DATA, $iv);
    }// decrypt


    /**
     * Encrypt string.
     * 
     * @link https://stackoverflow.com/questions/41222162/encrypt-in-php-openssl-and-decrypt-in-javascript-cryptojs Reference.
     * @param string $string The original string to be encrypt.
     * @param string $key The key.
     * @return string Return encrypted string.
     */
    public function encrypt($string, $key = 'pickle')
    {
        $ivLength = openssl_cipher_iv_length($this->encryptMethod);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $salt = openssl_random_pseudo_bytes(16);
        $hashKey = hash_pbkdf2('sha512', $key, $salt, 999, 128);

        $cipherText = openssl_encrypt($string, $this->encryptMethod, hex2bin($hashKey), OPENSSL_RAW_DATA, $iv);
        unset($hashKey);

        // kompakt format: base64url(salt[16] . iv[16] . ciphertext) — URL-safe, JSON/hex şişmesi yok
        return rtrim(strtr(base64_encode($salt . $iv . $cipherText), '+/', '-_'), '=');
    }// encrypt


    /**
     * Get encrypt method length number (128, 192, 256).
     * 
     * @return integer.
     */
    protected function encryptMethodLength()
    {
        $number = filter_var($this->encryptMethod, FILTER_SANITIZE_NUMBER_INT);

        return intval(abs($number));
    }// encryptMethodLength


    /**
     * Set encryption method.
     * 
     * @link http://php.net/manual/en/function.openssl-get-cipher-methods.php Available methods.
     * @param string $cipherMethod
     */
    public function setCipherMethod($cipherMethod)
    {
        $this->encryptMethod = $cipherMethod;
    }// setCipherMethod
}
