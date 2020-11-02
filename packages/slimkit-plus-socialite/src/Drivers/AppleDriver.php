<?php


namespace SlimKit\PlusSocialite\Drivers;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AppleDriver extends DriverAbstract
{
    protected $appleKeyUri = 'https://appleid.apple.com/auth/keys';
    protected static $supported_algs = array(
        'HS256' => array('hash_hmac', 'SHA256'),
        'HS512' => array('hash_hmac', 'SHA512'),
        'HS384' => array('hash_hmac', 'SHA384'),
        'RS256' => array('openssl', 'SHA256'),
        'RS384' => array('openssl', 'SHA384'),
        'RS512' => array('openssl', 'SHA512'),
    );
    /**
     * Get base URI.
     *
     * @return string
     * @author Seven Du <shiweidu@outlook.com>
     */
    protected function getBaseURI(): string
    {
        return 'https://appleid.apple.com/auth/token';
    }

    public function provider(): string
    {
        return 'apple';
    }

    /**
     * @param string $accessToken
     * @param null|string $userId
     * @return string
     */
    public function unionid(string $accessToken): string
    {
        $userId = request()->input('userId');
        date_default_timezone_set('PRC');
        $token = explode('.', $accessToken);
        $jwt_header = json_decode(base64_decode($token[0]), true);
        $jwt_data = json_decode(base64_decode($token[1]), true);
        $jwt_sign = $token[2];
        Log::debug($jwt_sign);
        if ($userId !== $jwt_data['sub']) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, '请求内容不匹配');
        }
        if($jwt_data['exp'] < time()) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, '凭据已过期');
        }
        $appKeys = $this->getAppleKeys();
        $ths_app_key = [];
        foreach ($appKeys['keys'] as $appKey) {
            if ($appKey['kid'] === $jwt_header['kid']) {
                $ths_app_key = $appKey;
            }
        }
        $pem = self::createPemFromModulusAndExponent($ths_app_key['n'], $ths_app_key['e']);
        $pKey = openssl_pkey_get_public($pem);
        if (!$pKey) {
            Log::debug('生成pem失败');
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, '生成pem失败');
        }
        $publicKeyDetails = openssl_pkey_get_details($pKey);
        $pub_key = $publicKeyDetails['key'];
        $alg = $jwt_header['alg'];

        $ok = self::verify("$token[0].$token[1]", static::urlsafeB64Decode($jwt_sign), $pub_key, $alg);
        if( !$ok ){
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, '签名验证失败');
        }

        return $userId;
    }

    /**
     *
     * Create a public key represented in PEM format from RSA modulus and exponent information
     *
     * @param string $n the RSA modulus encoded in Base64
     * @param string $e the RSA exponent encoded in Base64
     * @return string the RSA public key represented in PEM format
     */
    protected static function createPemFromModulusAndExponent($n, $e): string
    {
        $modulus = static::urlsafeB64Decode($n);
        $publicExponent = static::urlsafeB64Decode($e);

        $components = array(
            'modulus' => pack('Ca*a*', 2, self::encodeLength(strlen($modulus)), $modulus),
            'publicExponent' => pack('Ca*a*', 2, self::encodeLength(strlen($publicExponent)), $publicExponent)
        );

        $RSAPublicKey = pack(
            'Ca*a*a*',
            48,
            self::encodeLength(strlen($components['modulus']) + strlen($components['publicExponent'])),
            $components['modulus'],
            $components['publicExponent']
        );

        // sequence(oid(1.2.840.113549.1.1.1), null)) = rsaEncryption.
        $rsaOID = pack('H*', '300d06092a864886f70d0101010500'); // hex version of MA0GCSqGSIb3DQEBAQUA
        $RSAPublicKey = chr(0) . $RSAPublicKey;
        $RSAPublicKey = chr(3) . self::encodeLength(strlen($RSAPublicKey)) . $RSAPublicKey;

        $RSAPublicKey = pack(
            'Ca*a*',
            48,
            self::encodeLength(strlen($rsaOID . $RSAPublicKey)),
            $rsaOID . $RSAPublicKey
        );

        $RSAPublicKey = "-----BEGIN PUBLIC KEY-----\r\n" .
            chunk_split(base64_encode($RSAPublicKey), 64) .
            '-----END PUBLIC KEY-----';

        return $RSAPublicKey;
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     */
    protected static function urlsafeB64Decode($input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * DER-encode the length
     *
     * DER supports lengths up to (2**8)**127, however, we'll only support lengths up to (2**8)**4.  See
     * {@link http://itu.int/ITU-T/studygroups/com17/languages/X.690-0207.pdf#p=13 X.690 paragraph 8.1.3} for more information.
     *
     * @access private
     * @param int $length
     * @return string
     */
    protected static function encodeLength($length): string
    {
        if ($length <= 0x7F) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }

    /**
     * Get the number of bytes in cryptographic strings.
     *
     * @param string
     *
     * @return int
     */
    protected static function safeStrlen($str): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }
        return strlen($str);
    }

    /**
     * Verify a signature with the message, key and method. Not all methods
     * are symmetric, so we must have a separate verify and sign method.
     *
     * @param string            $msg        The original message (header and body)
     * @param string            $signature  The original signature
     * @param string|resource   $key        For HS*, a string key works. for RS*, must be a resource of an openssl public key
     * @param string            $alg        The algorithm
     *
     * @return bool
     *
     * @throws DomainException Invalid Algorithm or OpenSSL failure
     */
    protected static function verify($msg, $signature, $key, $alg): ?bool
    {
        if (empty(static::$supported_algs[$alg])) {
            throw new DomainException('Algorithm not supported');
        }

        list($function, $algorithm) = static::$supported_algs[$alg];
        switch($function) {
            case 'openssl':
                $success = openssl_verify($msg, $signature, $key, $algorithm);
                if ($success === 1) {
                    return true;
                } elseif ($success === 0) {
                    return false;
                }
                // returns 1 on success, 0 on failure, -1 on error.
                throw new DomainException(
                    'OpenSSL error: ' . openssl_error_string()
                );
            case 'hash_hmac':
            default:
                $hash = hash_hmac($algorithm, $msg, $key, true);
                if (function_exists('hash_equals')) {
                    return hash_equals($signature, $hash);
                }
                $len = min(static::safeStrlen($signature), static::safeStrlen($hash));

                $status = 0;
                for ($i = 0; $i < $len; $i++) {
                    $status |= (ord($signature[$i]) ^ ord($hash[$i]));
                }
                $status |= (static::safeStrlen($signature) ^ static::safeStrlen($hash));

                return ($status === 0);
        }
    }

    /**
     * 获取苹果的keys
     * @return mixed
     */
    protected function getAppleKeys()
    {
        $client = new Client([
            'base_uri' => $this->appleKeyUri,
            'timeout' => 0,
        ]);
        try {
            $keys = Cache::remember('app_public_keys', 86400, function () use ($client) {
                return json_decode($client->get('')->getBody()->getContents(), true);
            });
        } catch (RequestException $exception) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getResponse());
        }
        if (!$keys) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, '获取keys失败');
        }

        return $keys;
    }
}
