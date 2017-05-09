# Amazon Simple Email Service for PHP
[![Travis](https://travis-ci.org/okamos/php-ses.svg?branch=master)]()
[![license](https://img.shields.io/github/license/okamos/php-ses.svg)]()  
php-ses is a PHP library for Amazon's Simple Email Service's REST API [Amazon SES](https://aws.amazon.com/ses/)

## Installation
Install via [Composer](https://getcomposer.org/)

```bash
composer require okamos/php-ses
```

## Getting started
To get started you need to require ses.php

```php
<php?
require_once('vendor/ses.php');
```

This library need your AWS access key id and aws secret access key.

```php
$ses = new SimpleEmailService(
    array(
        'aws_access_key_id' => 'AKI...',
        'aws_secret_access_key' => 'yout_secret...',
        'region' => 'us-west-2' // default is us-east-1
    )
);

// if you can't use verification of SSL certificate
$ses = new SimpleEmailService(
    array(
        'aws_access_key_id' => 'AKI...',
        'aws_secret_access_key' => 'yout_secret...',
        'region' => 'us-west-2' // default is us-east-1
    ), false
);

// method name's first character is must be lower case
$identities = $ses->listIdentities(); // string[]
```

## Available API
* ListIdentities
* VerifyEmailIdentity
* DeleteIdentity
* SendEmail
* GetSendQuota
* GetSendStatistics
* GetIdentityVerificationAttributes

## Usage

Listing identities.

```php
// List all identities your domains.
$identities = $ses->ListIdentities('Domain');
// List all identities your email addresses.
$identities = $ses->ListIdentities('EmailAddress');
$identities[0]; // your@email.com
```

Verify Email.

```php
$ses->verifyEmailIdentity('your-email@example.com'); // return string(RequestId)
```

Delete an identity.

```php
$ses->deleteIdentity('your-email@example.com'); // return string(RequestId)
```

Get verification token and status.

```php
$identities = [
    'your-email@exmaple.com',
    'your-domain.com'
];
$entries = $ses->getIdentityVerificationAttributes($identities);
$entries->entry[0]->value->VerificationToken; // string(token)
$entries->entry[1]->value->VerificationStatus; // string(Pending | Success | Failed | TemporaryFailure)
```

Get your AWS account's send quota.

```php
$sendQuota = $ses->getSendQuota();
$sendQuota->Max24HourSend // string
$sendQuota->SentLast24Hours // string
$sendQuota->MaxSendRate // string
```

Get your sending statistics.

```php
$data = $ses->getSendStatistics();
$data->Complaints // string
$data->Rejects // string
$data->Bounces // string
$data->DeliveryAttempts // string
$data->Timestamp // string
```
