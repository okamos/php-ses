<?php
/**
 * SimpleEmailService Class Doc Comment.
 *
 * Amazon SimpleEmailService for PHP.
 *
 * @category Class
 * @package  AmazonSimpleEmailService
 * @author   Okamos <okamoto@okamos.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/okamos/php-ses
 */
class SimpleEmailService
{
    const SERVICE = 'email';
    const DOMAIN = 'amazonaws.com';
    const ALGORITHM = 'AWS4-HMAC-SHA256';

    private $_aws_key;
    private $_aws_secret;
    private $_region;

    private $_host;
    private $_endpoint;

    private $_amz_date;
    private $_date;

    private $_action;
    private $_method;

    private $_headers;
    private $_query_parameters;

    private $_client;

    /**
     * It is required aws_access_key_id and aws_secret_access_key.
     * Defaults verification of SSL certificate used.
     *
     * @param string $access_key AWS access_key_id.
     * @param string $secret_key AWS secret_access_key.
     * @param string $region     AWS region. default us-east-1.
     */
    public function __construct($access_key, $secret_key, $region = 'us-east-1')
    {
        $this->_aws_key = $access_key;
        $this->_aws_secret = $secret_key;
        $this->_region = $region;

        $this->_host = self::SERVICE . '.' . $this->_region . '.' . self::DOMAIN;
        $this->_endpoint = 'https://' . self::SERVICE . '.' . $this->_region . '.' . self::DOMAIN;
        $this->_client = new GuzzleHttp\Client(); 
    }

    /**
     * List all identities your AWS account.
     *
     * @param string $identity_type The type of the identities to list. Possible
     *                              values are "EmailAddress" and "Domain". If this 
     *                              parameter is omitted, then all identities will 
     *                              be listed.
     *
     * @return string[] or Error
     */
    public function listIdentities($identity_type = '')
    {
        $this->_action = 'ListIdentities';
        $this->_method = 'GET';
        $this->_refreshDate();

        if (!preg_match('/^(EmailAddress|Domain|)$/', $identity_type)) {
            throw new Exception('IdentityType must be EmailAddress or Domain');
            return;
        }

        $parameters = array();
        if ($identity_type) {
            $parameters['IdentityType'] = $identity_type;
        }

        $this->_generateSignature($parameters);
        $res = $this->_request();
        if ($res['code'] == 200) {
            $members = $res['body']->ListIdentitiesResult->Identities->member;
            $ret = [];
            foreach ($members as $member) {
                $ret[] = (string) $member;
            }
            return $ret;
        } else {
            return new SimpleEmailServiceError((string) $res['body']->Error->Code);
        }
    }

    /**
     * Send an confirmation email to email address for verification.
     *
     * @param string $email The email address to be verified.
     *
     * @return string RequestId or Error
     */
    public function verifyEmailIdentity($email)
    {
        $this->_action = 'VerifyEmailIdentity';
        $this->_method = 'GET';
        $this->_refreshDate();

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email');
            return;
        }

        $parameters = array(
            'EmailAddress' => $email
        );

        $this->_generateSignature($parameters);
        $res = $this->_request();
        if ($res['code'] == 200) {
            return (string) $res['body']->ResponseMetadata->RequestId;
        } else {
            return new SimpleEmailServiceError((string) $res['body']->Error->Code);
        }
    }

    /**
     * Delete an identity from your AWS account.
     *
     * @param string $identity The identity to be removed from the list of identities
     *                         for the AWS Account. An email address or domain.
     *
     * @return string RequestId or Error
     */
    public function deleteIdentity($identity)
    {
        $this->_action = 'DeleteIdentity';
        $this->_method = 'GET';
        $this->_refreshDate();

        if (!(filter_var($identity, FILTER_VALIDATE_EMAIL) || preg_match('/^([a-z\d]+(-[a-z\d]+)*\.)+[a-z]{2,}$/', $identity))) {
            throw new Exception('Identity must be EmailAddress or Domain');
            return;
        }

        $parameters = array(
            'Identity' => $identity
        );

        $this->_generateSignature($parameters);
        $res = $this->_request();
        if ($res['code'] == 200) {
            return (string) $res['body']->ResponseMetadata->RequestId;
        } else {
            return new SimpleEmailServiceError((string) $res['body']->Error->Code);
        }
    }

    /**
     * Get verification status.
     *
     * @param string[] $identities List of string. (email or domain)
     *
     * @return object[] {
     *           Email  => string,
     *           Token  => string,
     *           Status => string
     *         }
     */
    public function getIdentityVerificationAttributes($identities)
    {
        $this->_action = 'GetIdentityVerificationAttributes';
        $this->_method = 'GET';
        $this->_refreshDate();

        $parameters = array();

        $index = 1;
        if (is_array($identities)) {
            foreach ($identities as $identity) {
                if (!(filter_var($identity, FILTER_VALIDATE_EMAIL) || preg_match('/^([a-z\d]+(-[a-z\d]+)*\.)+[a-z]{2,}$/', $identity))) {
                    throw new Exception('Identity must be EmailAddress or Domain');
                    return;
                }
                $parameters['Identities.member.' . $index] = $identity;
                $index++;
            }
        }

        $this->_generateSignature($parameters);
        $res = $this->_request();
        if ($res['code'] == 200) {
            $entries = $res['body']->GetIdentityVerificationAttributesResult->VerificationAttributes->entry;
            $ret = array();
            foreach ($entries as $entry) {
                $ret[] = array(
                    'Email' => (string) $entry->key,
                    'Token' => (string) $entry->value->VerificationToken,
                    'Status' => (string) $entry->value->VerificationStatus
                );
            }
            return $ret;
        } else {
            return new SimpleEmailServiceError((string) $res['body']->Error->Code);
        }
    }

    /**
     * Send the email to some specified addresses.
     *
     * @param SimpleEmailServiceEnvelope $envelope Instance of
     *                                   SimpleEmailServiceEnvelope class.
     *
     * @return string MessageId.
     */
    public function sendEmail($envelope)
    {
        $validate = $envelope->validate();
        if (is_object($validate)) {
            return $validate;
        }

        $parameters = $envelope->buildParameters();
        $this->_action = $envelope->action;
        $this->_method = 'POST';
        $this->_refreshDate();

        $this->_generateSignature($parameters);
        $res = $this->_request();
        if ($res['code'] == 200) {
            return (string) $res['body']->SendEmailResult->MessageId;
        } else {
            return new SimpleEmailServiceError((string) $res['body']->Error->Code);
        }
    }


    /**
     * Get your AWS account's sending limits.
     *
     * @return object {
     *           Max24HourSend   => string,
     *           SentLast24Hours => string,
     *           MaxSendRate     => string
     *         }
     */
    public function getSendQuota()
    {
        $this->_action = 'GetSendQuota';
        $this->_method = 'GET';
        $this->_refreshDate();

        $this->_generateSignature();
        $res = $this->_request();
        if ($res['code'] == 200) {
            $result = $res['body']->GetSendQuotaResult;
            return array(
                'MaxSendRate' => (string) $result->Max24HourSend,
                'SentLast24Hours' => (string) $result->SentLast24Hours,
                'MaxSendRate' => (string) $result->MaxSendRate
            );
        } else {
            return new SimpleEmailServiceError((string) $res['body']->Error->Code);
        }
        throw new Exception(self::ERROR);
    }

    /**
     * Get SES sending statistics.
     *
     * @return object {
     *           Complaints       => string,
     *           Rejects          => string,
     *           Bounces          => string,
     *           DeliveryAttempts => string,
     *           Timestamp        => string
     *         }
     */
    public function getSendStatistics()
    {
        $this->_action = 'GetSendStatistics';
        $this->_method = 'GET';
        $this->_refreshDate();

        $this->_generateSignature();
        $res = $this->_request();
        if ($res['code'] == 200) {
            $result = $res['body']->GetSendStatisticsResult->SendDataPoints->member;
            return array(
                'Complaints' => (string) $result->Complaints,
                'Rejects' => (string) $result->Rejects,
                'Bounces' => (string) $result->Bounces,
                'DeliveryAttempts' => (string) $result->DeliveryAttempts,
                'Timestamp' => (string) $result->Timestamp
            );
        } else {
            return new SimpleEmailServiceError((string) $res['body']->Error->Code);
        }
    }

    /**
     * Create and returns binary hmac sha256
     *
     * @return hmac sha256.
     */
    private function _generateSignatureKey()
    {
        $date_h = hash_hmac('sha256', $this->_date, 'AWS4' . $this->_aws_secret, true);
        $region_h = hash_hmac('sha256', $this->_region, $date_h, true);
        $service_h = hash_hmac('sha256', self::SERVICE, $region_h, true);
        $signing_h = hash_hmac('sha256', 'aws4_request', $service_h, true);

        return $signing_h;
    }

    /**
     *  Refresh amzdate and date
     *
     *  @return void
     */
    private function _refreshDate()
    {
        $this->_amz_date = gmdate('Ymd\THis\Z');
        $this->_date = gmdate('Ymd');
    }

    /**
     * Signing AWS Requests with Signature Version 4
     *
     * @param array $parameters It contains request parameters.
     *
     * @ref http://docs.aws.amazon.com/general/latest/gr/sigv4_signing.html
     *
     * @return void
     */
    private function _generateSignature($parameters = array())
    {
        $this->_headers = [];
        $canonical_uri = '/';

        $parameters['Action'] = $this->_action;
        ksort($parameters);

        $request_parameters = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        $canonical_headers = 'host:' . $this->_host . "\n" . 'x-amz-date:' . $this->_amz_date . "\n";
        $signed_headers = 'host;x-amz-date';
        $payload_hash = hash('sha256', '');

        // task1
        $canonical_request = $this->_method . "\n" . $canonical_uri . "\n" . $request_parameters . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        // task2
        $credential_scope = $this->_date . '/' . $this->_region . '/' . self::SERVICE . '/aws4_request';
        $string_to_sign =  self::ALGORITHM . "\n" . $this->_amz_date . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);

        // task3
        $signing_key = $this->_generateSignatureKey();
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        $this->_headers['Authorization'] = self::ALGORITHM . ' Credential=' . $this->_aws_key . '/' . $credential_scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;
        $this->_headers['x-amz-date'] = $this->_amz_date;
        $this->_query_parameters = $request_parameters;
    }

    /**
     * Request handler.
     *
     * @return object {
     *           code => integer,
     *           body => SimpleXMLElement
     *         }
     */
    private function _request()
    {
        $res = $this->_client->request(
            $this->_method,
            $this->_endpoint . '?' . $this->_query_parameters,
            [
                'headers' => $this->_headers,
                'http_errors' => false
            ]
        );
        return array(
            'code' => $res->getStatusCode(),
            'body' => new SimpleXMLElement($res->getBody())
        );
    }
}
?>
