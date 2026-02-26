<?php
/**
 * @package     plg_system_dbug
 * @subpackage  System.dbug
 *
 * @author      Gerald R. Zalsos
 * @link        https://www.stacklio.app
 * @copyright   Copyright (C) 2026 Stacklio.app
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 *
 *
 * Legacy shim:
 * - defines global function dbug()
 * - provides global class dBug (backwards compatible)
 *
 * No Joomla dependencies here (assets handled by plugin class).
 */



declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\Plugin\System\Dbug\Dbug\Dbug as ModernDbug;

if (!class_exists('dBug')) {
    class dBug extends ModernDbug {}
}

if (!function_exists('dbug')) {
    function dbug($var = '', $nb = 0, $title = '', $bCollapsed = false): void
    {
        $nb = (int) $nb;

        if (is_string($var)) {
            $var = str_replace(["\r\n", "\r", "\n", "\t"], ['\r\n', '\r', '\n', '\t'], $var);
        }

        new dBug($var, $nb, (string) $title, '', (bool) $bCollapsed);
    }
}