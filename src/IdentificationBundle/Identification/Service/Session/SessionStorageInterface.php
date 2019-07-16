<?php

namespace IdentificationBundle\Identification\Service\Session;

/**
 * Interface SessionStorageInterface
 */
interface SessionStorageInterface
{
    /**
     * @param string $key
     *
     * @return string|null
     */
    public function readStorageValue(string $key): ?string;

    /**
     * @param string $key
     * @param $value
     */
    public function storeStorageValue(string $key, $value): void;

    /**
     * @param string $key
     */
    public function cleanStorageValue(string $key): void;

    /**
     * @param string $key
     * @param string $result
     */
    public function storeOperationResult(string $key, string $result): void;

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function readOperationResult(string $key);

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function cleanOperationResult(string $key);
}