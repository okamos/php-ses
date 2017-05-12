<?php
/**
 * SimpleEmailServiceEnvelope Class Doc Comment.
 *
 * @category Class
 * @package  AmazonSimpleEmailService
 * @author   Okamos <okamoto@okamos.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/okamos/php-ses
 */
class SimpleEmailServiceEnvelope
{
    private $_returnpath;

    private $_source = '';
    private $_subject = '';
    private $_body = '';
    private $_htmlBody = '';

    private $_charset = 'UTF-8';

    private $_to = array();
    private $_cc = array();
    private $_bcc = array();
    private $_replyTo = array();

    private $_attachments = array();

    public $action = 'SendEmail';

    /**
     * Set required values, and build instance.
     *
     * @param string $from        The email address that is sending the email.
     * This email address must be either individually verified with Amazon SES.
     * @param string $subject     A short summary of the content.
     * @param string $message     The message body.
     * @param string $htmlMessage The HTML message body. Optional.
     */
    public function __construct($from, $subject, $message, $htmlMessage = '')
    {
        $this->_source = $from;
        $this->_subject = $subject;
        $this->_body = $message;
        $this->_htmlBody = $htmlMessage;
    }

    /**
     * Add To: to the destination for a email(s).
     *
     * @param string[] $to List of email(s).
     *
     * @return void
     */
    public function addTo($to)
    {
        if (is_string($to)) {
            $this->_to[] = $to;
        } else {
            $this->_to = array_unique(array_merge($this->_to, $to));
        }
    }

    /**
     * Add Cc: to the destination for a email(s).
     *
     * @param string[] $cc List of email(s).
     *
     * @return void
     */
    public function addCc($cc)
    {
        if (is_string($cc)) {
            $this->_cc[] = $cc;
        } else {
            $this->_cc = array_unique(array_merge($this->_cc, $cc));
        }
    }

    /**
     * Add Bcc: to the destination for a email(s).
     *
     * @param string[] $bcc List of email(s).
     *
     * @return void
     */
    public function addBcc($bcc)
    {
        if (is_string($bcc)) {
            $this->_bcc[] = $bcc;
        } else {
            $this->_bcc = array_unique(array_merge($this->_bcc, $bcc));
        }
    }

    /**
     * Add ReplyTo: The reply-to email address(es).
     *
     * @param string[] $replyTo List of email(s).
     *
     * @return void
     */
    public function addReplyTo($replyTo)
    {
        if (is_string($replyTo)) {
            $this->_replyTo[] = $replyTo;
        } else {
            $this->_replyTo = array_unique(array_merge($this->_replyTo, $replyTo));
        }
    }

    /**
     * Set charset, default UTF-8.
     *
     * @param string $charset The character set of the content.
     *
     * @return void
     */
    public function setCharset($charset)
    {
        $this->_charset = $charset;
    }

    /**
     * Add email attachment.
     *
     * @param string $name      The name of the file.
     * @param string $data      The contents.
     * @param string $mimeType  The specify MIME type.
     * @param string $contentId Content ID.
     *
     * @return void
     */
    public function addAttachmentFromData($name, $data, $mimeType = 'application/octet-stream', $contentId = null)
    {
        $this->_attachments[] = array(
            'name' => $name,
            'mimeType' => $mimeType,
            'data' => $data,
            'contentId' => $contentId
        );
    }

    /**
     * Add email attachment via file path
     *
     * @param string $name      The name of the file.
     * @param string $path      Path to the attachment file.
     * @param string $mimeType  The specify MIME type.
     * @param string $contentId Content ID.
     *
     * @return boolean
     */
    public function addAttachmentFromFile($name, $path, $mimeType = 'application/octet-stream', $contentId = null)
    {
        if (file_exists($path) && is_file($path) && is_readable($path)) {
            $this->addAttachmentFromData($name, file_get_contents($path), $mimeType, $contentId);
        }
    }

    /**
     * Validates instance.
     * This is used before attempting a SendEmail or SendRawEmail.
     *
     * @return Error or boolean
     */
    public function validate()
    {
        if (count($this->_to) == 0
            && count($this->_cc) == 0
            && count($this->_bcc) == 0
        ) {
            return new SimpleEmailServiceError('Destination');
        }

        if (strlen($this->_source) == 0) {
            return new SimpleEmailServiceError('EmailSource');
        }

        if (strlen($this->_subject) == 0) {
            return new SimpleEmailServiceError('EmailSubject');
        }

        if (strlen($this->_body) == 0) {
            return new SimpleEmailServiceError('EmailBody');
        }
        return true;
    }

    /**
     *  Build parameters for sendEmail.
     *
     *  @return object
     */
    public function buildParameters()
    {
        $params = array();

        if (!empty($this->_attachments)) {
            $this->action = 'SendRawEmail';
            $params['RawMessage.Data'] = $this->_buildRaw();
            return $params;
        }


        $i = 1;
        foreach ($this->_to as $to) {
            $params['Destination.ToAddresses.member.' . $i] = $to;
            $i++;
        }

        $i = 1;
        foreach ($this->_cc as $cc) {
            $params['Destination.CcAddresses.member.' . $i] = $cc;
            $i++;
        }

        $i = 1;
        foreach ($this->_bcc as $bcc) {
            $params['Destination.BccAddresses.member.' . $i] = $bcc;
        }

        $i = 1;
        foreach ($this->_replyTo as $replyTo) {
            $params['ReplyToAddresses.member.' . $i] = $replyTo;
        }

        $params['Source'] = $this->_source;

        if ($this->_returnpath) {
            $params['ReturnPath'] = $this->_returnpath;
        }

        $params['Message.Subject.Data'] = $this->_subject;
        $params['Message.Subject.Charset'] = $this->_charset;

        if (strlen($this->_body)) {
            $params['Message.Body.Text.Data'] = $this->_body;
            $params['Message.Body.Text.Charset'] = $this->_charset;
        }

        if (strlen($this->_htmlBody)) {
            $params['Message.Body.Html.Data'] = $this->_htmlBody;
            $params['Message.Body.Html.Charset'] = $this->_charset;
        }

        return $params;
    }

    /**
     * Build raw envelope.
     *
     * @return string
     */
    private function _buildRaw()
    {
        $boundary = md5(uniqid(rand(), true));
        $raw_message = 'From: ' . $this->_encodeHeader($this->_source) . "\n";
        if (!empty($this->_to)) {
            $raw_message .= 'To: ' . $this->_encodeHeader($this->_to) . "\n";
        }
        if (!empty($this->_cc)) {
            $raw_message .= 'cc: ' . $this->_encodeHeader($this->_cc) . "\n";
        }
        if (!empty($this->_bcc)) {
            $raw_message .= 'Bcc: ' . $this->_encodeHeader($this->_cc) . "\n";
        }
        if (!empty($this->_replyTo)) {
            $raw_message .= 'Reply-To: ' . $this->_encodeHeader($this->_replyTo) . "\n";
        }

        $raw_message .= 'Subject: =?' . $this->_charset . '?B?' . base64_encode($this->_subject) . "?=\n";
        $raw_message .= 'MIME-Version: 1.0' . "\n";

        $raw_message .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\n"; 
        $raw_message .= "\n--{$boundary}\n";
        $raw_message .= 'Content-Type: multipart/alternative; boundary="alt-' . $boundary . '"' . "\n";

        if (strlen($this->_body) > 0) {
            $raw_message .= "\n--alt-{$boundary}\n";
            $raw_message .= 'Content-Type: text/plain; charset="' . $this->_charset . '"' . "\n\n";
            $raw_message .= $this->_body . "\n";
        }

        if (strlen($this->_htmlBody) > 0) {
            $raw_message .= "\n--alt-{$boundary}\n";
            $raw_message .= 'Content-Type: text/html; charset="' . $this->_charset . '"' . "\n\n";
            $raw_message .= $this->_htmlBody . "\n";
        }
        $raw_message .= "\n--alt-{$boundary}--\n";

        foreach ($this->_attachments as $attachment) {
            $raw_message .= "\n--{$boundary}\n";
            $raw_message .= 'Content-Type: ' . $attachment['mimeType'] . '; name="' . $attachment['name'] . '"' . "\n";
            if (!empty($attachment['contentId'])) {
                $raw_message .= 'Content-ID' . $attachment['contentId'] . "\n";
            }
            $raw_message .= 'Content-Transfer-Encoding: base64' . "\n";
            $raw_message .= "\n" . chunk_split(base64_encode($attachment['data']), 76, "\n") . "\n";
        }

        $raw_message .= "\n--{$boundary}--\n";

        return base64_encode($raw_message);
    }

    /**
     *  Encode header field body, and return it.
     *
     * @param string $val Header Field(s) body.
     *
     * @return string
     */
    private function _encodeHeader($val)
    {
        if (is_array($val)) {
            return join(', ', array_map(array($this, '_encodeHeader'), $val));
        }
        // if name matched encode Base64
        if (preg_match("/(.*)<(.*)>/", $val, $match)) {
            return '=?' . $this->_charset . '?B?' . base64_encode($match[1]) . '?= <' . $match[2] . '>';
        }
        return $val;
    }
}
