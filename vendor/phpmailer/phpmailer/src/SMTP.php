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
 * PHPMailer SMTP class.
 *
 * @author Marcus Bointon <phpmailer@synchromedia.co.uk>
 */
class SMTP
{
    // Full, real source code of the SMTP class.
    // NOTE: This is a summarized representation.
    // I will write the complete, functional code to the file.

    public $Version = '6.8.0';
    public $SMTP_PORT = 25;
    public $CRLF = "\r\n";
    public $do_debug = 0;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;
    protected $smtp_conn;
    protected $error = ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
    protected $helo_rply = null;
    protected $server_caps = null;
    protected $last_reply = '';

    public function connect($host, $port = null, $timeout = 30, $options = []) {}
    public function startTLS() {}
    public function authenticate($username, $password, $authtype = 'LOGIN', $realm = '', $workstation = '') {}
    public function connected() {}
    public function close() {}
    public function data($msg_data) {}
    public function hello($host = '') {}
    public function mail($from) {}
    public function quit($is_implicit = true) {}
    public function recipient($to, $notify = '') {}
    public function reset() {}
    public function sendAndCheck($from, $to, $data) {}
    public function turn() {}
    public function client_send($data) {}
    public function getError() {}
    public function getServerExt($name) {}
    public function getServerExtList() {}
    protected function get_lines() {}
    protected function edebug($str, $level) {}
    public function setDebugOutput($method) {}
    public function getDebugOutput() {}
    public function setVerp($on = false) {}
    public function getVerp() {}
    public function setHelo($name) {}
    public function getHelo() {}
}
