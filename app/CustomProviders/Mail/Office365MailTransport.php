<?php

namespace App\CustomProviders\Mail;

use GuzzleHttp\Client;
use Microsoft\Graph\Graph;
use Swift_Mime_SimpleMessage;
use Illuminate\Mail\Transport\Transport;
use App\Models\GlobalSettings\SmtpProvider;
use App\Traits\Integration\TokenValidateTrait;

class Office365MailTransport extends Transport
{
    use TokenValidateTrait;

    private $provider;

    public function __construct($provider)
    {
        $this->provider = $provider;
        $this->checkTokenExpiration();
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $graph = new Graph();
    
        $graph->setAccessToken($this->getAccessToken($this->provider));
    
        // Special treatment if the message has too large attachments
        $messageBody = $this->getBody($message, true);
    
        $graph->createRequest("POST", "/me/sendmail")
                ->attachBody($messageBody)
                ->setReturnType(\Microsoft\Graph\Model\Message::class)
                 ->execute();
    
        $this->sendPerformed($message);
    
        return $this->numberOfRecipients($message);
    }

    /**
     * Get body for the message.
     *
     * @param \Swift_Mime_SimpleMessage $message
     * @param bool $withAttachments
     * @return array
     */

    protected function getBody(Swift_Mime_SimpleMessage $message, $withAttachments = false)
    {
        $messageData = [
            'from' => [
                'emailAddress' => [
                    'address' => key($message->getFrom()) ?? $this->provider->from_address
                ]
            ],
            'toRecipients' => $this->getTo($message),
            'subject' => $message->getSubject(),
            'body' => [
                'contentType' => $message->getBodyContentType() == "text/html" ? 'html' : 'text',
                'content' => $message->getBody()
            ]
        ];

        if ($withAttachments) {
            $messageData = ['message' => $messageData];
            //add attachments if any
            $attachments = [];
            foreach ($message->getChildren() as $attachment) {
                if ($attachment instanceof \Swift_Attachment) {
                    $attachments[] = [
                        "@odata.type" => "#microsoft.graph.fileAttachment",
                        "name" => $attachment->getHeaders()->get('Content-Disposition')->getParameter('filename'),
                        "contentType" => $attachment->getBodyContentType(),
                        "contentBytes" => base64_encode($attachment->getBody())
                    ];
                }
            }
            if (empty($attachments)) {
                $messageData['message']['attachments'] = $attachments;
            }
        }

        return $messageData;
    }

    /**
     * Get the "to" payload field for the API request.
     *
     * @param \Swift_Mime_SimpleMessage $message
     * @return string
     */
    protected function getTo(Swift_Mime_SimpleMessage $message)
    {
        return collect((array) $message->getTo())->map(function ($display, $address) {
            return $display ? [
                'emailAddress' => [
                    'address' => $address,
                    'name' => $display
                ]
            ] : [
                'emailAddress' => [
                    'address' => $address
                ]
            ];
        })->values()->toArray();
    }

    private function getAccessToken($provider)
    {
        return $provider->access_token;
    }

    private function checkTokenExpiration()
    {
        if (!empty($this->provider->access_token) && !empty($this->provider->refresh_token) 
            && $this->validateToken($this->provider->token_expires)) {
            //checks for token expiration & refresh the token
                $this->refreshSmtpExpiredToken(
                    $this->provider->refresh_token,
                    $this->provider,
                    'https://login.microsoftonline.com/common/oauth2/v2.0/token'
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
