<?php

declare(strict_types=1);

namespace OTPHP;

interface OTPInterface
{
    public const DEFAULT_DIGITS = 6;

    public const DEFAULT_DIGEST = 'sha1';

    /**
     * Create a OTP object from an existing secret.
     *
     * @param non-empty-string $secret
     */
    public static function createFromSecret(string $secret): self;

    /**
     * Create a new OTP object. A random 64 bytes secret will be generated.
     */
    public static function generate(): self;

    /**
     * @param non-empty-string $secret
     */
    public function setSecret(string $secret): void;

    public function setDigits(int $digits): void;

    /**
     * @param non-empty-string $digest
     */
    public function setDigest(string $digest): void;

    /**
     * Generate the OTP at the specified input.
     *
     * @param 0|positive-int $input
     *
     * @return non-empty-string Return the OTP at the specified timestamp
     */
    public function at(int $input): string;

    /**
     * Verify that the OTP is valid with the specified input. If no input is provided, the input is set to a default
     * value or false is returned.
     *
     * @param non-empty-string $otp
     * @param null|0|positive-int $input
     * @param null|0|positive-int $window
     */
    public function verify(string $otp, null|int $input = null, null|int $window = null): bool;

    /**
     * @return non-empty-string The secret of the OTP
     */
    public function getSecret(): string;

    /**
     * @param non-empty-string $label The label of the OTP
     */
    public function setLabel(string $label): void;

    /**
     * @return non-empty-string|null The label of the OTP
     */
    public function getLabel(): null|string;

    /**
     * @return non-empty-string|null The issuer
     */
    public function getIssuer(): ?string;

    /**
     * @param non-empty-string $issuer
     */
    public function setIssuer(string $issuer): void;

    /**
     * @return bool If true, the issuer will be added as a parameter in the provisioning URI
     */
    public function isIssuerIncludedAsParameter(): bool;

    public function setIssuerIncludedAsParameter(bool $issuer_included_as_parameter): void;

    /**
     * @return positive-int Number of digits in the OTP
     */
    public function getDigits(): int;

    /**
     * @return non-empty-string Digest algorithm used to calculate the OTP. Possible values are 'md5', 'sha1', 'sha256' and 'sha512'
     */
    public function getDigest(): string;

    /**
     * @param non-empty-string $parameter
     */
    public function getParameter(string $parameter): mixed;

    /**
     * @param non-empty-string $parameter
     */
    public function hasParameter(string $parameter): bool;

    /**
     * @return array<non-empty-string, mixed>
     */
    public function getParameters(): array;

    /**
     * @param non-empty-string $parameter
     */
    public function setParameter(string $parameter, mixed $value): void;

    /**
     * Get the provisioning URI.
     *
     * @return non-empty-string
     */
    public function getProvisioningUri(): string;

    /**
     * Get the provisioning URI.
     *
     * @param non-empty-string $uri         The Uri of the QRCode generator with all parameters. This Uri MUST contain a placeholder that will be replaced by the method.
     * @param non-empty-string $placeholder the placeholder to be replaced in the QR Code generator URI
     */
    public function getQrCodeUri(string $uri, string $placeholder): string;
}
