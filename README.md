# Amazon Simple Email Service for PHP
php-ses is a PHP library for Amazon's Simple Email Service's REST API [Amazon SES](https://aws.amazon.com/ses/)

## Getting started
To get started you need to require ses.php

```php
require_once('vendor/ses.php');
```

This library need your AWS access key id and aws secret access key.

```php
$client = new SimpleEmailService(array(
  'aws_access_key_id' => 'AKI...',
  'aws_secret_access_key' => 'yout_secret...',
  'region' => 'us-west-2' // default is us-east-1
));
```

## Available API
* ListIdentities
* VerifyEmailIdentity
* DeleteIdentity
* SendEmail
* GetSendQuota
* GetSendStatistics
