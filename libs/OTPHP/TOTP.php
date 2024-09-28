<?php

declare(strict_types=1);

namespace OTPHP;

use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use function assert;
use function is_int;

/**
 * @see \OTPHP\Test\TOTPTest
 */
final class TOTP extends OTP implements TOTPInterface
{
    private readonly ClockInterface $clock;

    public function __construct(string $secret, ?ClockInterface $clock = null)
    {
        parent::__construct($secret);
        if ($clock === null) {
            trigger_deprecation(
                'spomky-labs/otphp',
                '11.3.0',
                'The parameter "$clock" will become mandatory in 12.0.0. Please set a valid PSR Clock implementation instead of "null".'
            );
            $clock = new InternalClock();
        }

        $this->clock = $clock;
    }

    public static function create(
        null|string $secret = null,
        int $period = self::DEFAULT_PERIOD,
        string $digest = self::DEFAULT_DIGEST,
        int $digits = self::DEFAULT_DIGITS,
        int $epoch = self::DEFAULT_EPOCH,
        ?ClockInterface $clock = null
    ): self {
        $totp = $secret !== null
            ? self::createFromSecret($secret, $clock)
            : self::generate($clock)
        ;
        $totp->setPeriod($period);
        $totp->setDigest($digest);
        $totp->setDigits($digits);
        $totp->setEpoch($epoch);

        return $totp;
    }

    public static function createFromSecret(string $secret, ?ClockInterface $clock = null): self
    {
        $totp = new self($secret, $clock);
        $totp->setPeriod(self::DEFAULT_PERIOD);
        $totp->setDigest(self::DEFAULT_DIGEST);
        $totp->setDigits(self::DEFAULT_DIGITS);
        $totp->setEpoch(self::DEFAULT_EPOCH);

        return $totp;
    }

    public static function generate(?ClockInterface $clock = null): self
    {
        return self::createFromSecret(self::generateSecret(), $clock);
    }

    public function getPeriod(): int
    {
        $value = $this->getParameter('period');
        (is_int($value) && $value > 0) || throw new InvalidArgumentException('Invalid "period" parameter.');

        return $value;
    }

    public function getEpoch(): int
    {
        $value = $this->getParameter('epoch');
        (is_int($value) && $value >= 0) || throw new InvalidArgumentException('Invalid "epoch" parameter.');

        return $value;
    }

    public function expiresIn(): int
    {
        $period = $this->getPeriod();

        return $period - ($this->clock->now()->getTimestamp() % $this->getPeriod());
    }

    /**
     * The OTP at the specified input.
     *
     * @param 0|positive-int $input
     */
    public function at(int $input): string
    {
        return $this->generateOTP($this->timecode($input));
    }

    public function now(): string
    {
        $timestamp = $this->clock->now()
            ->getTimestamp();
        assert($timestamp >= 0, 'The timestamp must return a positive integer.');

        return $this->at($timestamp);
    }

    /**
     * If no timestamp is provided, the OTP is verified at the actual timestamp. When used, the leeway parameter will
     * allow time drift. The passed value is in seconds.
     *
     * @param 0|positive-int $timestamp
     * @param null|0|positive-int $leeway
     */
    public function verify(string $otp, null|int $timestamp = null, null|int $leeway = null): bool
    {
        $timestamp ??= $this->clock->now()
            ->getTimestamp();
        $timestamp >= 0 || throw new InvalidArgumentException('Timestamp must be at least 0.');

        if ($leeway === null) {
            return $this->compareOTP($this->at($timestamp), $otp);
        }

        $leeway = abs($leeway);
        $leeway < $this->getPeriod() || throw new InvalidArgumentException(
            'The leeway must be lower than the TOTP period'
        );
        $timestampMinusLeeway = $timestamp - $leeway;
        $timestampMinusLeeway >= 0 || throw new InvalidArgumentException(
            'The timestamp must be greater than or equal to the leeway.'
        );

        return $this->compareOTP($this->at($timestampMinusLeeway), $otp)
            || $this->compareOTP($this->at($timestamp), $otp)
            || $this->compareOTP($this->at($timestamp + $leeway), $otp);
    }

    public function getProvisioningUri(): string
    {
        $params = [];
        if ($this->getPeriod() !== 30) {
            $params['period'] = $this->getPeriod();
        }

        if ($this->getEpoch() !== 0) {
            $params['epoch'] = $this->getEpoch();
        }

        return $this->generateURI('totp', $params);
    }

    public function setPeriod(int $period): void
    {
        $this->setParameter('period', $period);
    }

    public function setEpoch(int $epoch): void
    {
        $this->setParameter('epoch', $epoch);
    }

    /**
     * @return array<non-empty-string, callable>
     */
    protected function getParameterMap(): array
    {
        return [
            ...parent::getParameterMap(),
            'period' => static function ($value): int {
                (int) $value > 0 || throw new InvalidArgumentException('Period must be at least 1.');

                return (int) $value;
            },
            'epoch' => static function ($value): int {
                (int) $value >= 0 || throw new InvalidArgumentException(
                    'Epoch must be greater than or equal to 0.'
                );

                return (int) $value;
            },
        ];
    }

    /**
     * @param array<non-empty-string, mixed> $options
     */
    protected function filterOptions(array &$options): void
    {
        parent::filterOptions($options);

        if (isset($options['epoch']) && $options['epoch'] === 0) {
            unset($options['epoch']);
        }

        ksort($options);
    }

    /**
     * @param 0|positive-int $timestamp
     *
     * @return 0|positive-int
     */
    private function timecode(int $timestamp): int
    {
        $timecode = (int) floor(($timestamp - $this->getEpoch()) / $this->getPeriod());
        assert($timecode >= 0);

        return $timecode;
    }
}
