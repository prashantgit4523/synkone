<?php

namespace App\CustomProviders\Mail;

use GuzzleHttp\Client;
use Swift_Mime_SimpleMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Mail\Transport\Transport;
use App\Models\GlobalSettings\SmtpProvider;
use App\Traits\Integration\TokenValidateTrait;
use App\Models\GlobalSettings\SmtpProviderAlias;

class GoogleMailTransport extends Transport
{
    use TokenValidateTrait;
    
    public function __construct($provider)
    {
        $this->provider = $provider;
        $this->alias = SmtpProviderAlias::where('selected', 1)->first();
        $this->checkTokenExpiration();
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $messageBody = $this->createMessage($message);
        
        $response = Http::withToken($this->provider->access_token)
                    ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                        'raw' => $messageBody
                    ]);
        
        if ($response->ok()) {
            $data = json_decode($response->body(), true);
            if ($data['id']) {
                return true;
            }
        }
        return false;
    }

    public function createMessage($message)
    {
        $sender = $this->getFrom($message);
        $senderName = SmtpProviderAlias::where('email', $sender)->first()?->name;
        $to = $this->getTo($message);
        $subject = $message->getSubject();
        $messageText = $message->getBody();

        $rawMessageString = "From: {$senderName} <{$sender}>\r\n";
        $rawMessageString .= "To: <{$to}>\r\n";
        $rawMessageString .= 'Subject: =?utf-8?B?' . base64_encode($subject) . "?=\r\n";
        $rawMessageString .= "MIME-Version: 1.0\r\n";
        $rawMessageString .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessageString .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
        $rawMessageString .= "{$messageText}\r\n";
        return strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_'));
    }

    public function fetchGmailAliases($update = false)
    {
        try {
            $response = Http::withToken($this->provider->access_token)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/settings/sendAs');

            if ($response->ok()) {
                $aliases = json_decode($response->body(), true);
                
                $aliasEmails = array_map(function ($alias) {
                    return $alias['sendAsEmail'];
                }, $aliases['sendAs']);

                $dbEmails = SmtpProviderAlias::pluck('email')->toArray();
                
                //emails to be deleted
                $delEmails = array_diff($dbEmails, $aliasEmails);

                foreach ($delEmails as $email) {
                $delAlias = SmtpProviderAlias::where('email', $email)->first();

                //updates the selected alias to default account when selected account is deleted.
                if ($delAlias->selected) {
                        SmtpProviderAlias::where('email', $this->provider->from_address)->update(['selected' => 1]);
                }
                $delAlias->delete();
                }
                
                foreach ($aliases['sendAs'] as $alias) {
                    $isPrimary = isset($alias['isPrimary']) && $alias['isPrimary'];

                    $data = [
                        'smtp_provider_id' => 2,
                        'email' => $alias['sendAsEmail'],
                        'name' => $isPrimary ? $this->provider->from_name : $alias['displayName'],
                        'verificationStatus' => $isPrimary ? 'primary' : $alias['verificationStatus']
                    ];

                    if (!$update) {
                        $data['selected'] = $isPrimary ? 1 : 0;
                    }
                    
                    SmtpProviderAlias::updateOrCreate([
                        'email' => $alias['sendAsEmail']
                    ], $data);
                }

                return true;
            }
        
            return false;
        } catch (\Exception $e) {
            Log::error('SMTP refresh alias error: '. $e->getMessage());

            return false;
        }
    }


    /**
     * Get the "to" payload field for the API request.
     *
     * @param \Swift_Mime_SimpleMessage $message
     * @return string
     */
    protected function getTo(Swift_Mime_SimpleMessage $message)
    {
        return array_key_first($message->getTo()) ?? null;
    }

    /**
     * Get the "to" payload field for the API request.
     *
     * @param \Swift_Mime_SimpleMessage $message
     * @return string
     */
    protected function getFrom(Swift_Mime_SimpleMessage $message)
    {
        return array_key_first($message->getFrom()) ?? $this->alias->email;
    }

    private function checkTokenExpiration()
    {
        if (!empty($this->provider->access_token) && !empty($this->provider->refresh_token) && $this->validateToken($this->provider->token_expires)) {
            //checks for token expiration & refresh the token
                $this->refreshSmtpExpiredToken(
                    $this->provider->refresh_token,
                    $this->provider,
                    'https://www.googleapis.com/oauth2/v4/token'
                );
                $this->provider->refresh();
        }
    }

    private function refreshSmtpExpiredToken($refreshToken, $provider, $tokenUrl)
    {
        $httpClient = new Client();
        
        $response = $httpClient->post($tokenUrl, [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => [
                'grant_type' => 'refresh_token',
                'client_id' => config("services.{$provider->driver}.client_id"),
                'client_secret' => config("services.{$provider->driver}.client_secret"),
                'refresh_token' => $refreshToken
            ],
        ]);
        
        $this->updateToken(json_decode($response->getBody(), true), $provider->slug);

        return true;
    }

    private function updateToken($response, $provider)
    {
        $provider = SmtpProvider::where('slug', $provider)->first();
        
        if ($provider) {
            $tokenData = [
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'] ?? $provider->refresh_token,
                'token_expires' => $response['expires_in'] ? time() + $response['expires_in'] : null
            ];
            
            $provider->update($tokenData);

            return true;
        }
        return false;
    }
}