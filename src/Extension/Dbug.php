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

namespace Joomla\Plugin\System\Dbug\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

final class Dbug extends CMSPlugin
{
    protected $autoloadLanguage = true;

    public function onAfterInitialise(): void
    {
        // Always load legacy shim so dbug() exists.
        // Assets + dock injection are handled by this plugin class.
        $shim = JPATH_PLUGINS . '/system/dbug/dbug/debug.php';

        if (is_file($shim)) {
            require_once $shim;
        }
    }

    public function onAfterDispatch(): void
    {
        if (!$this->isAllowedByBaseRules()) {
            return;
        }

        $app = Factory::getApplication();
        $doc = $app->getDocument();

        if ($doc->getType() !== 'html' || !method_exists($doc, 'getWebAssetManager')) {
            return;
        }

        // Enqueue assets only if at least one dump happened.
        if (
            !class_exists(\Joomla\Plugin\System\Dbug\Dbug\Dbug::class)
            || !\Joomla\Plugin\System\Dbug\Dbug\Dbug::wasUsed()
        ) {
            return;
        }

        $wa = $doc->getWebAssetManager();

        // Load plugin asset registry (joomla.asset.json)
        $wa->getRegistry()->addExtensionRegistryFile('plg_system_dbug');

        // Enqueue
        $wa->useStyle('plg_system_dbug.dbug');
        $wa->useScript('plg_system_dbug.dbug');
    }

    public function onAfterRender(): void
    {
        if (!$this->isAllowedByBaseRules()) {
            return;
        }

        $app = Factory::getApplication();
        $doc = $app->getDocument();

        if ($doc->getType() !== 'html') {
            return;
        }

        if (
            !class_exists(\Joomla\Plugin\System\Dbug\Dbug\Dbug::class)
            || !\Joomla\Plugin\System\Dbug\Dbug\Dbug::wasUsed()
        ) {
            return;
        }

        $dumpHtml = \Joomla\Plugin\System\Dbug\Dbug\Dbug::flush();

        if ($dumpHtml === '') {
            return;
        }

        $dock = <<<HTML
<div class="dbug-dock" data-dbug>
  <div class="dbug-drawer" id="dbug-drawer" aria-hidden="true">
    <div class="dbug-resize" aria-hidden="true"></div>

    <div class="dbug-topbar">
      <div class="dbug-tabs" role="tablist" aria-label="dBug panels">
        <button class="dbug-tab is-active" role="tab" aria-selected="true" data-dbug-tab="dump">
          dBug <span class="dbug-count" data-dbug-count="dump">0</span>
        </button>

        <button class="dbug-tab" role="tab" aria-selected="false" data-dbug-tab="info">Info</button>
        <button class="dbug-tab" role="tab" aria-selected="false" data-dbug-tab="request">Request</button>
      </div>

      <div class="dbug-actions">
        <button type="button" class="dbug-iconbtn" data-dbug-action="collapseAll" title="Collapse all">–</button>
        <button type="button" class="dbug-iconbtn" data-dbug-action="expandAll" title="Expand all">+</button>
        <button type="button" class="dbug-iconbtn" data-dbug-action="close" title="Close">×</button>
      </div>
    </div>

    <div class="dbug-panels">
      <section class="dbug-panel is-active" role="tabpanel" data-dbug-panel="dump">
        <div class="dbug-panel-body" data-dbug-target="dump">
          {$dumpHtml}
        </div>
      </section>

      <section class="dbug-panel" role="tabpanel" data-dbug-panel="info">
        <div class="dbug-panel-body" data-dbug-target="info">
          <div class="dbug-note">Info panel.</div>
        </div>
      </section>

      <section class="dbug-panel" role="tabpanel" data-dbug-panel="request">
        <div class="dbug-panel-body" data-dbug-target="request">
          <div class="dbug-note">Request panel.</div>
        </div>
      </section>
    </div>
  </div>

  <button type="button" class="dbug-toggle" aria-expanded="false" aria-controls="dbug-drawer">
    <span class="dbug-toggle-icon" aria-hidden="true"></span>
    <span class="dbug-toggle-text">dBug</span>
  </button>
</div>
HTML;

        $body = $app->getBody();

        if (stripos($body, '</body>') !== false) {
            $body = str_ireplace('</body>', $dock . "\n</body>", $body);
        } else {
            $body .= $dock;
        }

        $app->setBody($body);
    }

    private function isAllowedByBaseRules(): bool
    {
        // Optional: only run in debug mode
        if ((int) $this->params->get('only_debug', 1) === 1) {
            if (!defined('JDEBUG') || !JDEBUG) {
                return false;
            }
        }

        // Keep your existing restriction logic (type: ip/userid/access/usergroup)
        // If you haven't wired those params yet, allow all:
        $type = strtolower((string) $this->params->get('type', 'all'));

        return match ($type) {
            default => true,
        };
    }
}