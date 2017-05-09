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
    const ERROR = "Please check your aws_access_key_id and aws_secret_access_key.\nAnd set correct permissions to your user or group.";

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

    /**
     * It is required aws_access_key_id and aws_secret_access_key.
     * Defaults verification of SSL certificate used.
     *
     * @param array   $credentials     It remains aws access_key_id and
     *                                 aws_secret_access_key, region.
     * @param boolean $ssl_verify_peer Require verification of peer name.
     */
    public function __construct($credentials = array(), $ssl_verify_peer = true)
    {
        $this->_aws_key = $credentials['aws_access_key_id'];
        $this->_aws_secret = $credentials['aws_secret_access_key'];
        // default is us-east-1
        $this->_region = $credentials['region'] ? $credentials['region'] : 'us-east-1';

        $this->_host = self::SERVICE . '.' . $this->_region . '.' . self::DOMAIN;
        $this->_endpoint = 'https://' . self::SERVICE . '.' . $this->_region . '.' . self::DOMAIN;

        $this->ssl_verify = $ssl_verify_peer;
    }

    /**
     * List all identities your AWS account.
     *
     * @param string $identity_type The type of the identities to list. Possible
     *                              values are "EmailAddress" and "Domain". If this 
     *                              parameter is omitted, then all identities will 
     *                              be listed.
     *
     * @return string[]
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
        $context = $this->_createStreamContext();
        if ($res = @file_get_contents($this->_endpoint . '?' . $this->_query_parameters, false, $context)) {
            $identities = new SimpleXMLElement($res);
            return $identities->ListIdentitiesResult->Identities->member;
        }
        throw new Exception(self::ERROR);
    }

    /**
     * Send an confirmation email to email address for verification.
     *
     * @param string $email The email address to be verified.
     *
     * @return string;
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
        $context = $this->_createStreamContext();
        if ($res = @file_get_contents($this->_endpoint . '?' . $this->_query_parameters, false, $context)) {
            $xml = simplexml_load_string($res);
            return $xml->ResponseMetadata->RequestId;
        }
        throw new Exception(self::ERROR);
    }

    /**
     * Delete an identity from your AWS account.
     *
     * @param string $identity The identity to be removed from the list of identities
     *                         for the AWS Account. An email address or domain.
     *
     * @return string
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
        $context = $this->_createStreamContext();
        if ($res = @file_get_contents($this->_endpoint . '?' . $this->_query_parameters, false, $context)) {
            $xml = simplexml_load_string($res);
            return $xml->ResponseMetadata->RequestId;
        }
        throw new Exception(self::ERROR);
    }

    /**
     * Get verification status.
     *
     * @param string[] $identities List of string. (email or domain)
     *
     * @return SimpleXMLElement {
     *           ["entry"]=> array(
     *             object {
     *               ["key"]=> string,
     *               ["value"]=> {
     *                 ["VerificationToken"]=> string,
     *                 ["VerificationStatus"]=> string
     *               }
     *             }
     *           )
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
        $context = $this->_createStreamContext();
        if ($res = @file_get_contents($this->_endpoint . '?' . $this->_query_parameters, false, $context)) {
            $xml = simplexml_load_string($res);
            return $xml->GetIdentityVerificationAttributesResult->VerificationAttributes;
        }
        throw new Exception(self::ERROR);
    }

    /**
     * Send the email to some specified addresses.
     *
     * @param array $assets "to", "cc", "bcc" are destination for this email.
     *                      "body" is the message to be sent.
     *                      "subject" is the subject of the message.
     *
     * @return string.
     *
     * @todo Send raw message.
     */
    public function sendEmail($assets = array())
    {
        $this->_action = 'SendEmail';
        $this->_method = 'POST';
        $this->_refreshDate();

        $parameters = array(
            'Message.Body.Text.Data' => $assets['body'],
            'Message.Subject.Data' => $assets['subject'],
            'Source' => $assets['from']
        );

        if (isset($asset['to'])) {
            $this->addAddresses($assets['to']);
        }
        if (isset($asset['cc'])) {
            $this->addAddresses($assets['cc'], 'cc');
        }
        if (isset($asset['bcc'])) {
            $this->addAddresses($assets['bcc'], 'bcc');
        }

        $this->_generateSignature($parameters);
        $context = $this->_createStreamContext();
        if ($res = @file_get_contents($this->_endpoint . '?' . $this->_query_parameters, false, $context)) {
            $xml = simplexml_load_string($res);
            return $xml->SendEmailResult->MessageId;
        }
        throw new Exception(self::ERROR);
    }

    /**
     * Add To: Cc: Bcc: to the destination for a email.
     *
     * @param string[] $addresses   List of emails.
     * @param string   $destination Destination type. Possible values are "to" and
     *                              "cc", "bcc".
     *
     * @return void
     */
    public function addAddresses($addresses, $destination = 'to')
    {
        $address_index = 1;
        if (is_string($addresses)) {
            $parameters['Destination.' . $destination . 'Addresses.member.' . $address_index] = $addresses;
        }
        if (is_array($addresses)) {
            foreach ($addresses as $address) {
                $parameters['Destination.' . $destination . 'Addresses.member.' . $address_index] = $address;
                $address_index++;
            }
        }
    }

    /**
     * Get your AWS account's sending limits.
     *
     * @return SimpleXMLElement {
     *           ["Max24HourSend"]=> string,
     *           ["SentLast24Hours"]=> string,
     *           ["MaxSendRate"]=> string
     *         }
     */
    public function getSendQuota()
    {
        $this->_action = 'GetSendQuota';
        $this->_method = 'GET';
        $this->_refreshDate();

        $this->_generateSignature();
        $context = $this->_createStreamContext();
        if ($res = @file_get_contents($this->_endpoint . '?' . $this->_query_parameters, false, $context)) {
            $xml = simplexml_load_string($res);
            return $xml->GetSendQuotaResult;
        }
        throw new Exception(self::ERROR);
    }

    /**
     * Get SES sending statistics.
     *
     * @return SimpleXMLElement {
     *           ["Complaints"]=> string,
     *           ["Rejects"]=> string,
     *           ["Bounces"]=> string,
     *           ["DeliveryAttempts"]=> string,
     *           ["Timestamp"]=> string
     *         }
     */
    public function getSendStatistics()
    {
        $this->_action = 'GetSendStatistics';
        $this->_method = 'GET';
        $this->_refreshDate();

        $this->_generateSignature();
        $context = $this->_createStreamContext();
        if ($res = @file_get_contents($this->_endpoint . '?' . $this->_query_parameters, false, $context)) {
            $xml = simplexml_load_string($res);
            return $xml->GetSendStatisticsResult->SendDataPoints->member;
        }
        throw new Exception(self::ERROR);
    }

    /**
     * Create and returns a stream context.
     *
     * @return A stream context resource.
     */
    private function _createStreamContext()
    {
        $opts = array(
            'ssl' => array(
                'verify_peer' => $this->ssl_verify,
                'verify_peer_name' => $this->ssl_verify
            ),
            'http' => array(
                'method' => $this->_method,
                'header' => join("\n", $this->_headers) . "\n"
            )
        );

        return stream_context_create($opts);
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
        $this->_headers[] = 'Authorization:' . self::ALGORITHM . ' Credential=' . $this->_aws_key . '/' . $credential_scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;
        $this->_headers[] = 'x-amz-date:' . $this->_amz_date;
        $this->_query_parameters = $request_parameters;
    }
}
?>
