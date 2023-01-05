<?php

namespace App\CustomProviders;

use Hitrov\OCI\KeyProvider\KeyProviderInterface;

class MockKeyProvider implements KeyProviderInterface
{
    private $fields;

    public function __construct($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return string
     */
    public function getKeyId(): string
    {
        return implode('/', [
            $this->fields['oci_tenancy_id'],
            $this->fields['oci_user_id'],
            $this->fields['oci_key_fingerprint']
        ]);
    }

    /**
     * @return string
     */
    public function getPrivateKey(): string
    {
        return $this->fields['private_key'];
    }
}