<?php

declare(strict_types=1);

namespace OTPHP;

use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Throwable;
use function assert;
use function count;

/**
 * This class is used to load OTP object from a provisioning Uri.
 *
 * @see \OTPHP\Test\FactoryTest
 */
final class Factory implements FactoryInterface
{
    public static function loadFromProvisioningUri(string $uri, ?ClockInterface $clock = null): OTPInterface
    {
        try {
            $parsed_url = Url::fromString($uri);
            $parsed_url->getScheme() === 'otpauth' || throw new InvalidArgumentException('Invalid scheme.');
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('Not a valid OTP provisioning URI', $throwable->getCode(), $throwable);
        }
        if ($clock === null) {
            trigger_deprecation(
                'spomky-labs/otphp',
                '11.3.0',
                'The parameter "$clock" will become mandatory in 12.0.0. Please set a valid PSR Clock implementation instead of "null".'
            );
            $clock = new InternalClock();
        }

        $otp = self::createOTP($parsed_url, $clock);

        self::populateOTP($otp, $parsed_url);

        return $otp;
    }

    private static function populateParameters(OTPInterface $otp, Url $data): void
    {
        foreach ($data->getQuery() as $key => $value) {
            $otp->setParameter($key, $value);
        }
    }

    private static function populateOTP(OTPInterface $otp, Url $data): void
    {
        self::populateParameters($otp, $data);
        $result = explode(':', rawurldecode(mb_substr($data->getPath(), 1)));

        if (count($result) < 2) {
            $otp->setIssuerIncludedAsParameter(false);

            return;
        }

        if ($otp->getIssuer() !== null) {
            $result[0] === $otp->getIssuer() || throw new InvalidArgumentException(
                'Invalid OTP: invalid issuer in parameter'
            );
            $otp->setIssuerIncludedAsParameter(true);
        }

        assert($result[0] !== '');

        $otp->setIssuer($result[0]);
    }

    private static function createOTP(Url $parsed_url, ClockInterface $clock): OTPInterface
    {
        switch ($parsed_url->getHost()) {
            case 'totp':
                $totp = TOTP::createFromSecret($parsed_url->getSecret(), $clock);
                $totp->setLabel(self::getLabel($parsed_url->getPath()));

                return $totp;
            case 'hotp':
                $hotp = HOTP::createFromSecret($parsed_url->getSecret());
                $hotp->setLabel(self::getLabel($parsed_url->getPath()));

                return $hotp;
            default:
                throw new InvalidArgumentException(sprintf('Unsupported "%s" OTP type', $parsed_url->getHost()));
        }
    }

    /**
     * @param non-empty-string $data
     * @return non-empty-string
     */
    private static function getLabel(string $data): string
    {
        $result = explode(':', rawurldecode(mb_substr($data, 1)));
        $label = count($result) === 2 ? $result[1] : $result[0];
        assert($label !== '');

        return $label;
    }
}
