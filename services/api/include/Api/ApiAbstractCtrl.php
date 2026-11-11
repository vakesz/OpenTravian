<?php

declare(strict_types=1);

namespace Api;

use Exceptions\MissingParameterException;

class ApiAbstractCtrl
{
    /** @var array<array-key, mixed> */
    protected array $response;
    /** @var array<array-key, mixed> */
    protected array $payload;

    /**
     * @param array<array-key, mixed> $response Bound by reference: controllers write the JSON `data` member through it.
     * @param array<array-key, mixed> $payload
     */
    public function __construct(array &$response, array $payload)
    {
        $this->response = &$response;
        $this->payload = $payload;
    }

    /**
     * A required scalar payload member as a string. A missing, null or
     * non-scalar value (array, object) is a client error, never a PHP warning.
     */
    protected function requireString(string $key): string
    {
        $value = $this->payload[$key] ?? null;
        if (!is_scalar($value)) {
            throw new MissingParameterException($key);
        }

        return (string) $value;
    }

    /** A required scalar payload member as an integer; see requireString(). */
    protected function requireInt(string $key): int
    {
        return (int) $this->requireString($key);
    }

    /** An optional payload member that is only used when it really is a string. */
    protected function optionalString(string $key): ?string
    {
        $value = $this->payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
