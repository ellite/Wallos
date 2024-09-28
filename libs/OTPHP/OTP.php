<?php

declare(strict_types=1);

namespace OTPHP;

use Exception;
use InvalidArgumentException;
use ParagonIE\ConstantTime\Base32;
use RuntimeException;
use function assert;
use function chr;
use function count;
use function is_string;
use const STR_PAD_LEFT;

abstract class OTP implements OTPInterface
{
    use ParameterTrait;

    private const DEFAULT_SECRET_SIZE = 64;

    /**
     * @param non-empty-string $secret
     */
    protected function __construct(string $secret)
    {
        $this->setSecret($secret);
    }

    public function getQrCodeUri(string $uri, string $placeholder): string
    {
        $provisioning_uri = urlencode($this->getProvisioningUri());

        return str_replace($placeholder, $provisioning_uri, $uri);
    }

    /**
     * @param 0|positive-int $input
     */
    public function at(int $input): string
    {
        return $this->generateOTP($input);
    }

    /**
     * @return non-empty-string
     */
    final protected static function generateSecret(): string
    {
        return Base32::encodeUpper(random_bytes(self::DEFAULT_SECRET_SIZE));
    }

    /**
     * The OTP at the specified input.
     *
     * @param 0|positive-int $input
     *
     * @return non-empty-string
     */
    protected function generateOTP(int $input): string
    {
        $hash = hash_hmac($this->getDigest(), $this->intToByteString($input), $this->getDecodedSecret(), true);
        $unpacked = unpack('C*', $hash);
        $unpacked !== false || throw new InvalidArgumentException('Invalid data.');
        $hmac = array_values($unpacked);

        $offset = ($hmac[count($hmac) - 1] & 0xF);
        $code = ($hmac[$offset] & 0x7F) << 24 | ($hmac[$offset + 1] & 0xFF) << 16 | ($hmac[$offset + 2] & 0xFF) << 8 | ($hmac[$offset + 3] & 0xFF);
        $otp = $code % (10 ** $this->getDigits());

        return str_pad((string) $otp, $this->getDigits(), '0', STR_PAD_LEFT);
    }

    /**
     * @param array<non-empty-string, mixed> $options
     */
    protected function filterOptions(array &$options): void
    {
        foreach ([
            'algorithm' => 'sha1',
            'period' => 30,
            'digits' => 6,
        ] as $key => $default) {
            if (isset($options[$key]) && $default === $options[$key]) {
                unset($options[$key]);
            }
        }

        ksort($options);
    }

    /**
     * @param non-empty-string $type
     * @param array<non-empty-string, mixed> $options
     *
     * @return non-empty-string
     */
    protected function generateURI(string $type, array $options): string
    {
        $label = $this->getLabel();
        is_string($label) || throw new InvalidArgumentException('The label is not set.');
        $this->hasColon($label) === false || throw new InvalidArgumentException('Label must not contain a colon.');
        $options = [...$options, ...$this->getParameters()];
        $this->filterOptions($options);
        $params = str_replace(['+', '%7E'], ['%20', '~'], http_build_query($options, '', '&'));

        return sprintf(
            'otpauth://%s/%s?%s',
            $type,
            rawurlencode(($this->getIssuer() !== null ? $this->getIssuer() . ':' : '') . $label),
            $params
        );
    }

    /**
     * @param non-empty-string $safe
     * @param non-empty-string $user
     */
    protected function compareOTP(string $safe, string $user): bool
    {
        return hash_equals($safe, $user);
    }

    /**
     * @return non-empty-string
     */
    private function getDecodedSecret(): string
    {
        try {
            $decoded = Base32::decodeUpper($this->getSecret());
        } catch (Exception) {
            throw new RuntimeException('Unable to decode the secret. Is it correctly base32 encoded?');
        }
        assert($decoded !== '');

        return $decoded;
    }

    private function intToByteString(int $int): string
    {
        $result = [];
        while ($int !== 0) {
            $result[] = chr($int & 0xFF);
            $int >>= 8;
        }

        return str_pad(implode('', array_reverse($result)), 8, "\000", STR_PAD_LEFT);
    }
}
