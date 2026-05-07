<?php

namespace App\Mail;

use Illuminate\Support\Facades\Log;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class Smtp2goTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.smtp2go.com/v3/email/send';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $senderName,
        private readonly string $senderEmail,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('SMTP2GO API key is not configured.');
        }

        $original = $message->getOriginalMessage();
        $email = MessageConverter::toEmail($original);
        $envelope = $message->getEnvelope();

        $payload = array_filter([
            'api_key' => $this->apiKey,
            'to' => $this->addressesFor($envelope->getRecipients()),
            'sender' => sprintf('%s <%s>', $this->senderName, $this->senderEmail),
            'subject' => $email->getSubject() ?? '(no subject)',
            'html_body' => $email->getHtmlBody(),
            'text_body' => $email->getTextBody(),
            'reply_to_address' => $this->replyToAddressFor($email->getReplyTo()),
        ], fn ($value): bool => $value !== null && $value !== []);

        $ch = curl_init(self::ENDPOINT);

        if ($ch === false) {
            throw new \RuntimeException('SMTP2GO cURL initialization failed.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-Smtp2go-Api-Key: '.$this->apiKey],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 15,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new \RuntimeException('SMTP2GO cURL error: '.$error);
        }

        $response = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);

        if ($status !== 200 || ($response['data']['succeeded'] ?? 0) < 1) {
            $failures = implode('; ', $response['data']['failures'] ?? ['unknown']);

            Log::error('SMTP2GO delivery failure', [
                'status' => $status,
                'failures' => $failures,
                'response' => $response,
            ]);

            throw new \RuntimeException('SMTP2GO rejected the message: '.$failures);
        }
    }

    public function __toString(): string
    {
        return 'smtp2go';
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<string>
     */
    private function addressesFor(array $addresses): array
    {
        return array_map(
            fn (Address $address): string => $address->toString(),
            $addresses,
        );
    }

    /**
     * @param  list<Address>  $addresses
     */
    private function replyToAddressFor(array $addresses): ?string
    {
        return $addresses === []
            ? null
            : $addresses[0]->toString();
    }
}
