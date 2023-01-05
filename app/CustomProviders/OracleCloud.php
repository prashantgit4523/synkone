<?php

namespace App\CustomProviders;

use Hitrov\OCI\Signer;
use App\CustomProviders\MockKeyProvider;
use App\CustomProviders\Interfaces\ICustomAuth;
use Hitrov\OCI\KeyProvider\KeyProviderInterface;
use App\CustomProviders\Interfaces\IInfrastructure;
use App\CustomProviders\Interfaces\IHaveHowToImplement;

class OracleCloud extends CustomProvider implements ICustomAuth, KeyProviderInterface
{
    public function __construct()
    {
        parent::__construct('oraclecloud');
    }

    public function attempt(array $fields): bool
    {
        
        $keyProvider = new MockKeyProvider($fields); // implements KeyProviderInterface;
        $signer = new Signer();
        $signer->setKeyProvider($keyProvider);

        $signingHeadersNames = $signer->getSigningHeadersNames('POST');
        
        $signingString = $signer->getSigningString('https://iaas.us-ashburn-1.oraclecloud.com/20160918/vcns', 'POST', '{
            "compartmentId": '.$fields['oci_tenancy_id'].',
            "displayName": "Apex Virtual Cloud Network",
            "cidrBlock": "172.16.0.0/16"
          }', 'application/json');

        $signature = $signer->calculateSignature($signingString, $fields['private_key']);

        $keyId = $signer->getKeyId();

        $signingHeadersNamesString = implode(' ', $signingHeadersNames);
        $authorizationHeader = $signer->getAuthorizationHeader($keyId, $signingHeadersNamesString, $signature);

        dd($authorizationHeader);
    }

    public function getPrivateKey(): string
    {
        return $this->fields['private_key'];    
    }

    public function getKeyId(): string
    {
        return implode('/', [
            $this->fields['oci_tenancy_id'],
            $this->fields['oci_user_id'],
            $this->fields['oci_key_fingerprint'],
        ]);
    }
}
