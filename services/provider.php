<?php
/**
 * @package     plg_system_dbug
 * @subpackage  System.dbug
 *
 * @author      Gerald R. Zalsos
 * @link        https://www.stacklio.app
 * @copyright   Copyright (C) 2026 Stacklio.app
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                // Joomla registers the event dispatcher under Joomla\Event\DispatcherInterface
                $dispatcher = $container->get(DispatcherInterface::class);

                return new \Joomla\Plugin\System\Dbug\Extension\Dbug(
                    $dispatcher,
                    (array) PluginHelper::getPlugin('system', 'dbug')
                );
            }
        );
    }
};