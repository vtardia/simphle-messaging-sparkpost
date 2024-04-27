<?php

declare(strict_types=1);

namespace Simphle\Messaging\Email\Provider;

use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Simphle\Messaging\Email\EmailContact;
use Simphle\Messaging\Email\EmailMessageInterface;
use Simphle\Messaging\Email\EmailMessageValidator;
use Simphle\Messaging\Email\Exception\EmailTransportException;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;
use SparkPost\SparkPostResponse;

class SparkPostEmailProvider implements EmailProviderInterface
{
    use EmailMessageValidator;

    private SparkPost $mailer;

    public function __construct(
        private readonly string $token,
        private readonly ClientInterface $client,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly array $options = []
    ) {
        if (!class_exists(SparkPost::class)) {
            throw new RuntimeException('PostmarkClient is not installed on this system');
        }
        /** @psalm-suppress ArgumentTypeCoercion Until SP updates */
        $this->mailer = new SparkPost(
            $this->client,
            array_replace_recursive($this->options, [
                'key' => $this->token,
                'async' => false
            ])
        );
    }

    public function send(EmailMessageInterface $message, array $options = []): void
    {
        try {
            // Merging send options with global options
            $options = array_replace_recursive($this->options, $options);

            // Standard validation
            // Sender must be your valid SparkPost authorised domain sender
            // E.g. if your main domain is example.com, and you configured a sender subdomain
            // like sp.example.com, the sender needs to be yourname@sp.example.com
            [$sender, $recipients, $subject, $html, $text] = $this->validate($message);

            $attachments = [];
            $images = [];
            foreach ($message->getAttachments() as $attachment) {
                $item = [
                    'name' => $attachment->name,
                    'type' =>  mime_content_type(
                        $options['baseDir'] . DIRECTORY_SEPARATOR . $attachment->path
                    ),
                    'data' => base64_encode((string) file_get_contents(
                        $options['baseDir'] . DIRECTORY_SEPARATOR . $attachment->path
                    ))
                ];
                if ($attachment->inline) {
                    $images[] = $item;
                } else {
                    $attachments[] = $item;
                }
            }

            /** @var SparkPostResponse $result */
            $result = $this->mailer->transmissions->post([
                'content' => [
                    'from' => [
                        'email' => $sender->address,
                        'name' => $sender->name,
                    ],
                    'subject' => $subject,
                    'text' => $text,
                    'html' => $html,
                    'attachments' => $attachments,
                    'inline_images' => $images,
                ],
                'recipients' => array_map(fn(EmailContact $r) => [
                    'address' => [
                        'email' => $r->address,
                        'name' => $r->name,
                    ]
                ], $recipients),
                'cc' => array_map(fn(EmailContact $cc) => [
                    'address' => [
                        'email' => $cc->address,
                        'name' => $cc->name,
                    ]
                ], $message->getCC()),
                'bcc' => array_map(fn(EmailContact $cc) => [
                    'address' => [
                        'email' => $cc->address,
                        'name' => $cc->name,
                    ]
                ], $message->getBCC()),
            ]);
            $this->logger->info('Message sent with result', [
                'status' => $result->getStatusCode(),
                'results' => $result->getBody()['results']
            ]);
        } catch (SparkPostException $e) {
            $this->logger->error('[SparkPost] Message could not be sent', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'body' => $e->getBody(),
            ]);
            throw new EmailTransportException($e->getMessage());
        }
    }
}
