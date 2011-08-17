# silverstripe-sms

silverstripe-sms is a [Silverstripe](http://www.silverstripe.com/) module that has support for sending [SMS](http://en.wikipedia.org/wiki/SMS) (Short Message Service).

# Usage

```php
$to = 61410123456;
$from = 61410123456; // Your number or can also be text if supported by provider (ie. 'John Smith')
$message = 'Here is the message.';
SMS::send($to, $message, $from);
```

# Providers

The module has been built using a quasi factory pattern so that in the future more providers can be added.

## [BurstSMS](http://burstsms.com/)

### Configuration

```php
SMS::configure('Burst', array('apiKey' => 'YOUR-BURST-API-KEY', 'apiSecret' => 'YOUR-BURST-API-SECRET'));
```
