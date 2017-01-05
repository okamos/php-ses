<?php
class SimpleEmailService {
  const SERVICE = 'email';
  const DOMAIN = 'amazonaws.com';
  const ALGORITHM = 'AWS4-HMAC-SHA256';
  const ERROR = "Please check your aws_access_key_id and aws_secret_access_key.\nAnd set correct permissions to your user or group.";

  private $aws_key;
  private $aws_secret;
  private $region;

  private $host;
  private $endpoint;

  private $amz_date;
  private $date;

  private $action;
  private $method;

  private $headers;
  private $query_parameters;

  public function __construct($credentials = array(), $enabled_ssl_verify_peer = true) {
    $this -> aws_key = $credentials['aws_access_key_id'];
    $this -> aws_secret = $credentials['aws_secret_access_key'];
    // default is us-east-1
    $this -> region = $credentials['region'] ? $credentials['region'] : 'us-east-1';

    $this -> host = self::SERVICE . '.' . $this -> region . '.' . self::DOMAIN;
    $this -> endpoint = 'https://' . self::SERVICE . '.' . $this -> region . '.' . self::DOMAIN;

    $this -> amz_date = gmdate('Ymd\THis\Z');
    $this -> date = gmdate('Ymd');

    $this -> ssl_verify = $enabled_ssl_verify_peer;
  }

  public function list_identities($identity_type = 'EmailAddress') {
    $this -> action = 'ListIdentities';
    $this -> method = 'GET';

    if (!preg_match('/EmailAddress|Domain/', $identity_type)) {
      error_log('IdentityType must EmailAddress or Domain');
      return;
    }

    $parameters = array(
      'IdentityType' => $identity_type
    );

    $this -> generate_signature($parameters);
    $context = $this -> create_stream_context();
    if ($res = @file_get_contents($this -> endpoint . '?' . $this -> query_parameters, false, $context)) {
      $identities = new SimpleXMLElement($res);
      return $identities -> ListIdentitiesResult -> Identities -> member;
    } else {
      error_log(self::ERROR);
      return;
    }
  }

  public function verify_email_identity($email) {
    $this -> action = 'VerifyEmailIdentity';
    $this -> method = 'GET';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      error_log('Invalid email');
      return;
    }

    $parameters = array(
      'EmailAddress' => $email
    );

    $this -> generate_signature($parameters);
    $context = $this -> create_stream_context();
    if ($res = @file_get_contents($this -> endpoint . '?' . $this -> query_parameters, false, $context)) {
      $xml = simplexml_load_string($res);
      return $xml -> ResponseMetadata -> RequestId;
    } else {
      error_log(self::ERROR);
      return;
    }
  }

  public function send_email($assets = array()) {
    $this -> action = 'SendEmail';
    $this -> method = 'POST';

    $parameters = array(
      'Message.Body.Text.Data' => $assets['body'],
      'Message.Subject.Data' => $assets['subject'],
      'Source' => $assets['from']
    );

    $address_index = 1;
    if (isset($assets['to']) && is_array($assets['to'])) {
      foreach ($assets['to'] as $address) {
        $parameters['Destination.ToAddresses.member.' . $address_index] = $address;
        $address_index++;
      }
    }

    if (isset($assets['cc']) && is_array($assets['cc'])) {
      $address_index = 1;
      foreach ($assets['cc'] as $address) {
        $parameters['Destination.CcAddresses.member.' . $address_index] = $address;
        $address_index++;
      }
    }

    if (isset($assets['bcc']) && is_array($assets['bcc'])) {
      $address_index = 1;
      foreach ($assets['bcc'] as $address) {
        $parameters['Destination.BccAddresses.member.' . $address_index] = $address;
        $address_index++;
      }
    }

    $this -> generate_signature($parameters);
    $context = $this -> create_stream_context();
    if ($res = @file_get_contents($this -> endpoint . '?' . $this -> query_parameters, false, $context)) {
      $xml = simplexml_load_string($res);
      return $xml -> SendEmailResult -> MessageId;
    } else {
      error_log(self::ERROR);
    }
  }

  private function create_stream_context() {
    $opts = array(
      'ssl' => array(
        // this is not recomend.
        'verify_peer' => $this -> ssl_verify,
        'verify_peer_name' => $this -> ssl_verify
      ),
      'http' => array(
        'method' => $this -> method,
        'header' => join("\n", $this -> headers) . "\n"
      )
    );

    return stream_context_create($opts);
  }

  // return binary hmac sha256
  private function generate_signature_key() {
    $date_h = hash_hmac('sha256', $this -> date, 'AWS4' . $this -> aws_secret, true);
    $region_h = hash_hmac('sha256', $this -> region, $date_h, true);
    $service_h = hash_hmac('sha256', self::SERVICE, $region_h, true);
    $signing_h = hash_hmac('sha256', 'aws4_request', $service_h, true);

    return $signing_h;
  }

  private function generate_signature($parameters) {
    $canonical_uri = '/';

    $parameters['Action'] = $this -> action;
    ksort($parameters);

    $request_parameters = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

    $canonical_headers = 'host:' . $this -> host . "\n" . 'x-amz-date:' . $this -> amz_date . "\n";
    $signed_headers = 'host;x-amz-date';
    $payload_hash = hash('sha256', '');

    $canonical_request = $this -> method . "\n" . $canonical_uri . "\n" . $request_parameters . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

    # task2
    $credential_scope = $this -> date . '/' . $this -> region . '/' . self::SERVICE . '/aws4_request';
    $string_to_sign =  self::ALGORITHM . "\n" . $this -> amz_date . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);

    # task3
    $signing_key = $this -> generate_signature_key();
    $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
    $this -> headers[] = 'Authorization:' . self::ALGORITHM . ' Credential=' . $this -> aws_key . '/' . $credential_scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;
    $this -> headers[] = 'x-amz-date:' . $this -> amz_date;
    $this -> query_parameters = $request_parameters;
  }
}
?>
