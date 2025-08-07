<?php
/**
 * PHPMailer - A full-featured email creation and transfer class for PHP
 *
 * @package PHPMailer
 * @author  Marcus Bointon (Synchro/CoolCat) <phpmailer@synchromedia.co.uk>
 * @author  Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author  Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author  Brent R. Matzelle (original founder)
 * @copyright 2001 - 2020 Marcus Bointon and PHPMailer contributors
 * @copyright 2001 - 2010 Brent R. Matzelle
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @link    https://github.com/PHPMailer/PHPMailer
 */

namespace PHPMailer\PHPMailer;

/**
 * PHPMailer exception handler.
 *
 * @author Marcus Bointon <phpmailer@synchromedia.co.uk>
 */
class Exception extends \Exception
{
    /**
     * Prettify error message output.
     *
     * @return string
     */
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage(), ENT_COMPAT | ENT_HTML401) . "</strong><br />\n";
    }
}
