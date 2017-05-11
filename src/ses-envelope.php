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
     * Validates instance.
     * This is used before attempting a SendEmail or SendRawEmail.
     *
     * @return boolean
     */
    public function validate()
    {
        if (count($this->_to) == 0
            && count($this->_cc) == 0
            && count($this->_bcc) == 0
        ) {
            return false;
        }

        if (strlen($this->_source) == 0) {
            return false;
        }

        if (strlen($this->_subject) == 0) {
            return false;
        }

        if (strlen($this->_body) == 0) {
            return false;
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
}
