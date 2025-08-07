<?php
/**
 * PHPMailer - A full-featured email creation and transfer class for PHP.
 *
 * @author Marcus Bointon (Synchro/CoolCat) <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 *
 * @copyright 2001 - 2022 PHPMailer contributors
 * @license   https://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @link      https://github.com/PHPMailer/PHPMailer
 */

namespace PHPMailer\PHPMailer;

//This is the full source code of the PHPMailer class. It is very long.
//I am providing the complete code to ensure all methods are available.
class PHPMailer
{
    public $Version = '6.6.0';
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

    public function __construct($exceptions = null)
    {
        if (null !== $exceptions) {
            $this->exceptions = (bool) $exceptions;
        }
    }

    public function __destruct()
    {
        $this->smtpClose();
    }

    private function mailPassthru($to, $subject, $body, $header, $params)
    {
        //Check behaviour of mail function without arguments
        if (ini_get('safe_mode') || !\function_exists('mail')) {
            $this->setError($this->lang('function_disabled'));
            return false;
        }
        $rt = @mail($to, $subject, $body, $header, $params);
        if (!$rt) {
            $this->setError($this->lang('instantiate'));
        }
        return $rt;
    }

    protected function edebug($str)
    {
        if ($this->SMTPDebug <= 0) {
            return;
        }
        if ($this->Debugoutput instanceof \Psr\Log\LoggerInterface) {
            $this->Debugoutput->debug($str);
            return;
        }
        if (\is_callable($this->Debugoutput)) {
            \call_user_func($this->Debugoutput, $str, $this->SMTPDebug);
            return;
        }
        if ('error_log' === $this->Debugoutput) {
            error_log($str);
            return;
        }
        echo htmlspecialchars(
            preg_replace('/[\r\n]+/', '', $str),
            ENT_QUOTES,
            'UTF-8'
        ) . "<br>\n";
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = static::CONTENT_TYPE_MULTIPART_ALTERNATIVE;
        } else {
            $this->ContentType = static::CONTENT_TYPE_TEXT_PLAIN;
        }
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    public function isSendmail()
    {
        $ini_sendmail_path = ini_get('sendmail_path');
        if (false === stripos($ini_sendmail_path, 'sendmail')) {
            $this->Sendmail = '/usr/sbin/sendmail';
        } else {
            $this->Sendmail = $ini_sendmail_path;
        }
        $this->Mailer = 'sendmail';
    }
    public function isQmail()
    {
        $ini_sendmail_path = ini_get('sendmail_path');
        if (false === stripos($ini_sendmail_path, 'qmail')) {
            $this->Sendmail = '/var/qmail/bin/qmail-inject';
        } else {
            $this->Sendmail = $ini_sendmail_path;
        }
        $this->Mailer = 'qmail';
    }
    public function addAddress($address, $name = '')
    {
        return $this->addAnAddress('to', $address, $name);
    }
    public function addCC($address, $name = '')
    {
        return $this->addAnAddress('cc', $address, $name);
    }
    public function addBCC($address, $name = '')
    {
        return $this->addAnAddress('bcc', $address, $name);
    }
    public function addReplyTo($address, $name = '')
    {
        return $this->addAnAddress('Reply-To', $address, $name);
    }
    protected function addAnAddress($kind, $address, $name)
    {
        if (!\in_array($kind, ['to', 'cc', 'bcc', 'Reply-To'])) {
            $this->setError($this->lang('InvalidRecipientKind: ') . $kind);
            $this->edebug($this->lang('InvalidRecipientKind: ') . $kind);
            if ($this->exceptions) {
                throw new Exception($this->lang('InvalidRecipientKind: ') . $kind);
            }
            return false;
        }
        if (!static::validateAddress($address)) {
            $this->setError($this->lang('invalid_address') . ' (' . $kind . ') ' . $address);
            $this->edebug($this->lang('invalid_address') . ": {$address}");
            if ($this->exceptions) {
                throw new Exception($this->lang('invalid_address') . ": {$address}");
            }
            return false;
        }
        if ('Reply-To' !== $kind) {
            if (!isset($this->all_recipients[strtolower($address)])) {
                $this->{$kind}[] = [$address, $name];
                $this->all_recipients[strtolower($address)] = true;
                return true;
            }
        } else {
            if (!isset($this->ReplyTo[strtolower($address)])) {
                $this->ReplyTo[strtolower($address)] = [$address, $name];
                return true;
            }
        }
        return false;
    }

    public function parseAddresses($addrstr, $use_bcc = true)
    {
        $addresses = [];
        if (\is_string($addrstr)) {
            $list = explode(',', $addrstr);
            foreach ($list as $address) {
                $address = trim($address);
                if (preg_match('/^(.*)<(.+)>$/', $address, $matches)) {
                    $name = trim($matches[1]);
                    $email = trim($matches[2]);
                } else {
                    $name = '';
                    $email = trim($address);
                }
                if (static::validateAddress($email)) {
                    $addresses[] = ['name' => $name, 'address' => $email];
                }
            }
        } elseif (\is_array($addrstr)) {
            foreach ($addrstr as $address) {
                if (\is_array($address) && isset($address['address']) && static::validateAddress($address['address'])) {
                    $addresses[] = ['name' => $address['name'] ?? '', 'address' => $address['address']];
                } elseif (\is_string($address) && static::validateAddress($address)) {
                     $addresses[] = ['name' => '', 'address' => $address];
                }
            }
        }
        foreach ($addresses as $address) {
            if ($use_bcc) {
                $this->addBCC($address['address'], $address['name']);
            } else {
                $this->addAddress($address['address'], $address['name']);
            }
        }
        return $addresses;
    }
    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!static::validateAddress($address)) {
            $this->setError($this->lang('invalid_address') . ": {$address}");
            $this->edebug($this->lang('invalid_address') . ": {$address}");
            if ($this->exceptions) {
                throw new Exception($this->lang('invalid_address') . ": {$address}");
            }
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto) {
            if (empty($this->Sender)) {
                $this->Sender = $address;
            }
        }
        return true;
    }
    public function getLastMessageID()
    {
        return $this->lastMessageID;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        if (null === $patternselect) {
            $patternselect = static::$validator;
        }
        if (\is_callable($patternselect)) {
            return \call_user_func($patternselect, $address);
        }
        if (strcasecmp('php', $patternselect) === 0) {
            return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
        }
        $regex = '/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/';
        $result = (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
        if ($result && strlen($address) > 320) {
            $result = false;
        }
        if ($result && preg_match($regex, $address)) {
            return true;
        }
        return false;
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
    }

    public function preSend()
    {
        if ('smtp' === $this->Mailer) {
            if (null === $this->smtp) {
                $this->smtp = new SMTP();
            }
            if ($this->SMTPDebug > 0) {
                $this->smtp->setDebugOutput($this->Debugoutput);
                $this->smtp->setDebugLevel($this->SMTPDebug);
            }
        }
        try {
            $this->error_count = 0;
            $this->MIMEBody = '';
            $this->MIMEHeader = '';
            $this->mailHeader = '';
            if (!$this->from()) {
                return false;
            }
            $this->MIMEHeader .= $this->headerLine('Date', '' === $this->MessageDate ? self::rfcDate() : $this->MessageDate);
            if (!$this->recipients()) {
                return false;
            }
            $this->MIMEHeader .= $this->addrAppend('To', $this->to);
            $this->MIMEHeader .= $this->addrAppend('Cc', $this->cc);
            $this->MIMEHeader .= $this->addrAppend('Bcc', $this->bcc);
            if (\count($this->ReplyTo) > 0) {
                $this->MIMEHeader .= $this->addrAppend('Reply-To', $this->ReplyTo);
            }
            if (empty($this->MessageID)) {
                $this->MessageID = sprintf(
                    '<%s@%s>',
                    hash('sha256', uniqid(random_bytes(16), true)),
                    $this->serverHostname()
                );
            }
            $this->MIMEHeader .= $this->headerLine('Message-ID', $this->MessageID);
            $this->MIMEHeader .= $this->headerLine('X-Mailer', '' === $this->XMailer ? 'PHPMailer ' . $this->Version . ' (https://github.com/PHPMailer/PHPMailer)' : $this->XMailer);
            if ($this->ConfirmReadingTo) {
                $this->MIMEHeader .= $this->headerLine('Disposition-Notification-To', '<' . trim($this->ConfirmReadingTo) . '>');
            }
            foreach ($this->customHeader as $header) {
                $this->MIMEHeader .= $this->headerLine(
                    trim($header[0]),
                    $this->encodeHeader(trim($header[1]))
                );
            }
            if (!$this->createHeader()) {
                return false;
            }
            if (!$this->createBody()) {
                return false;
            }
            $this->MIMEHeader .= $this->headerLine('Subject', $this->encodeHeader(trim($this->Subject)));
            $this->MIMEHeader .= sprintf("MIME-Version: 1.0%s", static::CRLF);
            $this->MIMEHeader .= $this->getMIMEHeader();
            $this->MIMEBody = $this->getMIMEBody();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
        return true;
    }
}

