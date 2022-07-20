# Message Broker

This package is to be used with microservices to allow seamless communication between
services.

## Usage

### Events

In the service application, listen to the `MessageReceived` and the `MessageProcessingFailed`
events. These are both fired by the listener.

### Listening

Either by running manually or running via supervisord, you should start the listener
by executing `php artisan message-broker:listen` and provide the `group` as the first
argument and an optional `stream`. The default steam is `microservices`.

### Publishing

To publish a message into the broker, create a `Message` instance then broadcast it.
The broadcast method takes an optional stream to which the message should be sent.
As above, the default steam is `microservices`. The broadcast method returns the ID
of the sent message.

```php
use SmoDav\MessageBroker\Enums\MessageType;
use SmoDav\MessageBroker\Message;

$payload = [
    'ping' => microtime(),
];

$message = new Message(MessageType::TEST, $payload);

$messageID = $message->broadcast();
```

