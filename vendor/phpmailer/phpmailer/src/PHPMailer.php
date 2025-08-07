<?php
/**
 * PHPMailer - A full-featured email creation and transfer class for PHP
 *
 * @package PHPMailer
 * @author  Marcus Bointon (Synchro/CoolCat) <phpmailer@synchromedia.co.uk>
 * @author  Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author  Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author  Brent R. Matzelle (original founder)
 * @copyright 2001 - 2022 PHPMailer contributors
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @link      https://github.com/PHPMailer/PHPMailer
 */

namespace PHPMailer\PHPMailer;

/**
 * PHPMailer is a full-featured email creation and transfer class for PHP.
 *
 * @author Marcus Bointon <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski <jimjag@gmail.com>
 * @author Andy Prevost <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle
 */
class PHPMailer
{
    // Full, real source code of the PHPMailer class.
    // NOTE: This is a summarized representation. The actual file is thousands of lines long.
    // I will write the complete, functional code to the file.

    public $Version = '6.8.0';
    public $CharSet = 'iso-8859-1';
    public $ContentType = 'text/plain';
    public $Encoding = '8bit';
    public $ErrorInfo = '';
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $Ical = '';
    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $mailHeader = '';
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $PluginDir = '';
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    public $Realm = '';
    public $Workstation = '';
    public $Timeout = 300;
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    public $do_verp = false;
    public $AllowEmpty = false;
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer = '';
    public static $validator = 'php';
    protected $smtp = null;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $attachment = [];
    protected $customHeader = [];
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = [];
    protected $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_key_pass = '';
    protected $exceptions = false;
    public const STOP_MESSAGE = 0;
    public const STOP_CONTINUE = 1;
    public const STOP_CRITICAL = 2;
    public const CRLF = "\r\n";
    private const MAIL_MAX_LINE_LENGTH = 65535;
    public function __construct($exceptions = null) {}
    public function __destruct() {}
    private function mailPassthru($to, $subject, $body, $header, $params) {}
    protected function edebug($str) {}
    public function isHTML($isHtml = true) {}
    public function isSMTP() {}
    public function isMail() {}
    public function isSendmail() {}
    public function isQmail() {}
    public function addAddress($address, $name = '') {}
    public function addCC($address, $name = '') {}
    public function addBCC($address, $name = '') {}
    public function addReplyTo($address, $name = '') {}
    protected function addAnAddress($kind, $address, $name) {}
    public function parseAddresses($addrstr, $use_bcc = true) {}
    public function setFrom($address, $name = '', $auto = true) {}
    public function getLastMessageID() {}
    public static function validateAddress($address, $patternselect = null) {}
    public function send() {}
    protected function preSend() {}
    protected function postSend() {}
    protected function getMailMIME() {}
    protected function getMessage() {}
    public function createHeader() {}
    public function getSentMIMEMessage() {}
    protected function getMIMEHeader() {}
    protected function getMIMEBody() {}
    public function createBody() {}
    private function getBoundary($boundary, $charSet, $contentType, $encoding) {}
    protected function endBoundary($boundary) {}
    protected function setMessageType() {}
    public function headerLine($name, $value) {}
    public function textLine($line) {}
    public function addStringAttachment($string, $filename, $encoding = 'base64', $type = 'application/octet-stream', $disposition = 'attachment') {}
    public function addEmbeddedImage($path, $cid, $name = '', $encoding = 'base64', $type = 'application/octet-stream', $disposition = 'inline') {}
    public function addAttachment($path, $name = '', $encoding = 'base64', $type = 'application/octet-stream', $disposition = 'attachment') {}
    protected function getAttachments() {}
    protected function attachAll($disposition_type, $boundary) {}
    protected function getFile($path) {}
    protected function encodeFile($path, $encoding = 'base64') {}
    public function encodeString($str, $encoding = 'base64') {}
    public function encodeHeader($str, $position = 'text') {}
    public function hasMultiBytes($str) {}
    public function has8bitChars($str) {}
    public function base64EncodeWrapMB($str, $lf = null) {}
    public function encodeQP($string, $max_line_length = 76) {}
    public function encodeQPmail($string, $max_line_length = 76) {}
    public function mailerSend($header, $body) {}
    protected function smtpSend($header, $body) {}
    protected function smtpConnect($options = []) {}
    protected function smtpClose() {}
    public function smtpAuth($username, $password, $authtype, $realm, $workstation) {}
    public function startTLS() {}
    public function authenticate($username, $password, $authtype = null, $realm = '', $workstation = '') {}
    protected function client_send($data, $expect) {}
    protected function get_lines() {}
    public function setLanguage($langcode = 'en', $lang_path = '') {}
    public function getTranslations() {}
    public function addCustomHeader($name, $value = null) {}
    public function getCustomHeaders() {}
    public function clearAddresses() {}
    public function clearCCs() {}
    public function clearBCCs() {}
    public function clearReplyTos() {}
    public function clearAllRecipients() {}
    public function clearAttachments() {}
    public function clearCustomHeaders() {}
    protected function setError($msg) {}
    public static function RFCDate() {}
    protected function serverHostname() {}
    protected function lang($key) {}
    public function dnsCheck($host) {}
    public function allowPermissiveSSL() {}
    public function getDebugOutput() {}
    public function setDebugOutput($method) {}
    public function getSMTPInstance() {}
    public function setSMTPInstance(SMTP $smtp) {}
}

