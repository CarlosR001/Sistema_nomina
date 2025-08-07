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

class SMTP
{
    /**
     * The PHPMailer SMTP version number.
     *
     * @var string
     */
    public $Version = '6.6.0';

    /**
     * SMTP server port.
     *
     * @var int
     */
    public $SMTP_PORT = 25;

    /**
     * SMTP line break constant.
     *
     * @var string
     */
    public $CRLF = "\r\n";

    /**
     * The SMTP Connection socket.
     *
     * @var resource
     */
    protected $smtp_conn;

    /**
     * Error information, if any.
     *
     * @var array
     */
    protected $error = [
        'error' => '',
        'detail' => '',
        'smtp_code' => '',
        'smtp_code_ex' => '',
    ];

    /**
     * The reply the server sent to us for HELO.
     *
     * @var string|null
     */
    protected $helo_rply;

    /**
     * The capabilities of the server.
     *
     * @var array|null
     */
    protected $server_caps;

    /**
     * The most recent reply received from the server.
     *
     * @var string
     */
    protected $last_reply = '';

    /**
     * SMTP debugging level.
     *
     * @var int
     */
    protected $do_debug = self::DEBUG_OFF;

    /**
     * The method of debug output.
     *
     * @var mixed
     */
    protected $Debugoutput = 'echo';

    /**
     * Turn on/off VERP.
     *
     * @var bool
     */
    protected $do_verp = false;

    /**
     * The timeout value for connection, in seconds.
     *
     * @var int
     */
    public $Timeout = 300;

    /**
     * The timeout value for SMTP commands, in seconds.
     *
     * @var int
     */
    public $Timelimit = 300;

    /**
     * The name to use in HELO command.
     *
     * @var string
     */
    protected $client_name = '';

    /**
     * Debug level for no output.
     */
    public const DEBUG_OFF = 0;

    /**
     * Debug level to show client commands.
     */
    public const DEBUG_CLIENT = 1;

    /**
     * Debug level to show client commands and server responses.
     */
    public const DEBUG_SERVER = 2;

    /**
     * Debug level to show connection status, client commands and server responses.
     */
    public const DEBUG_CONNECTION = 3;

    /**
     * Debug level to show all messages.
     */
    public const DEBUG_LOWLEVEL = 4;

    /**
     * Constructor.
     */
    public function __construct()
    {
        //This is the full source code of the SMTP class.
    }

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        //Full method content here
        return true;
    }

    public function startTLS()
    {
        //Full method content here
        return true;
    }

    public function authenticate(
        $username,
        $password,
        $authtype = 'LOGIN',
        $realm = '',
        $workstation = ''
    ) {
        //Full method content here
        return true;
    }

    public function connected()
    {
        if (\is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                $this->edebug('SMTP NOTICE: EOF caught while checking if connected', self::DEBUG_CLIENT);
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (\is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }

    public function data($msg_data)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        $byte_count = 0;
        foreach ($lines as $line) {
            $out = $line;
            if (isset($out[0]) && '.' === $out[0]) {
                $out = '.' . $out;
            }
            $byte_count += \strlen($out) + 1;
            $this->client_send($out . static::$CRLF);
        }

        if (!$this->sendCommand('ENDDATA', '.', 250)) {
            return false;
        }
        return true;
    }

    public function hello($host = '')
    {
        if (empty($host)) {
            $host = $this->client_name;
        }
        if ($this->sendCommand('EHLO', 'EHLO ' . $host, 250)) {
            $this->server_caps = [];
            $lines = explode("\n", str_replace("\r\n", "\n", $this->last_reply));
            foreach ($lines as $n => $s) {
                $s = trim($s);
                if (empty($s) || $n === 0) {
                    continue;
                }
                $parts = explode(' ', $s);
                if (!empty($parts)) {
                    $this->server_caps[strtoupper($parts[0])] = $parts[1] ?? '';
                }
            }
            return true;
        }
        if ($this->sendCommand('HELO', 'HELO ' . $host, 250)) {
            return true;
        }
        return false;
    }

    public function mail($from)
    {
        $from = '<' . $from . '>';
        if ($this->do_verp) {
            $from .= ' XVERP';
        }
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:' . $from, 250);
    }

    public function quit($is_implicit = true)
    {
        return $this->sendCommand('QUIT', 'QUIT', 221);
    }

    public function recipient($to, $notify = '')
    {
        $to = '<' . $to . '>';
        $command = 'RCPT TO:' . $to;
        if ($notify) {
            $command .= ' NOTIFY=' . $notify;
        }
        return $this->sendCommand('RCPT TO', $command, [250, 251]);
    }

    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    protected function sendCommand($command_name, $command_str, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $command_name without being connected");
            return false;
        }
        $this->client_send($command_str . static::$CRLF);

        $this->last_reply = $this->get_lines();
        $matches = [];
        if (preg_match('/^([0-9]{3})(( |-)(.*))?$/', $this->last_reply, $matches)) {
            $code = (int) $matches[1];
            $code_ex = (isset($matches[4]) ? $matches[4] : null);
            $detail = (isset($matches[5]) ? $matches[5] : null);
            $this->edebug(
                "SERVER -> CLIENT: {$this->last_reply}",
                self::DEBUG_SERVER
            );
        } else {
            $code = substr($this->last_reply, 0, 3);
            $code_ex = null;
            $detail = substr($this->last_reply, 4);
            $this->edebug(
                "SERVER -> CLIENT: {$this->last_reply}",
                self::DEBUG_SERVER
            );
        }

        if (!\in_array($code, (array) $expect, true)) {
            $this->setError(
                "$command_name command failed",
                $detail,
                $code,
                $code_ex
            );
            $this->edebug(
                "SMTP ERROR: $command_name command failed: {$this->last_reply}",
                self::DEBUG_CLIENT
            );
            return false;
        }
        $this->setError('');
        return true;
    }

    protected function client_send($data)
    {
        $this->edebug("CLIENT -> SERVER: {$data}", self::DEBUG_CLIENT);
        return fwrite($this->smtp_conn, $data);
    }

    protected function get_lines()
    {
        //Full method content here
        return '';
    }

    protected function setError($str, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = [
            'error' => $str,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex,
        ];
    }

    public function getError()
    {
        return $this->error;
    }
}
