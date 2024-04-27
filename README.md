# SparkPost Transport Provider for Simphle Messaging

SparkPost provider for [Simphle Messaging](https://github.com/vtardia/simphle-messaging) wrapping around the official [SparkPost PHP client](https://github.com/SparkPost/php-sparkpost).

## Install

```shell
composer require vtardia/simphle-messaging-sparkpost
```

## Usage

```php
use Simphle\Messaging\Email\Provider\SparkPostEmailProvider;
use GuzzleHttp\Client;

try {
    $message = /* Create a message here... */
    $mailer = new SparkPostEmailProvider(
        token: '<YourSparkPostAPIToken>',
        client: new \Http\Adapter\Guzzle7\Client(new Client()),
        logger: /* optional psr logger */,
        options: ['host' => 'api.[eu.]sparkpost.com']
    );
    
    // Send the email
    $mailer->send($message /*, [more, options]*/);
} catch (InvalidMessageException $e) {
    // Do something...
} catch (EmailTransportException $e) {
    // Do something else...
}
```
