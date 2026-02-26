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

namespace Joomla\Plugin\System\Dbug\Dbug;

defined('_JEXEC') or die;

class Dbug
{
    private int $nb;
    private string $title;
    private bool $collapsed;
    private int $maxDepth;

    /** @var array<string, true> */
    private array $seenArrays = [];

    /** @var array<int, true> */
    private array $seenObjects = [];

    /** True once any dump is created (used by plugin to decide whether to inject/enqueue). */
    private static bool $used = false;

    /** Buffered HTML dump blocks, injected at end of response by the plugin (prevents DOM corruption). */
    private static array $buffer = [];

    public function __construct(
        mixed $var,
        int $nb = 9,
        string $title = '',
        string $forceType = '',
        bool $collapsed = false,
        int $maxDepth = 12,
        bool $echo = true
    ) {
        $this->nb        = $nb;
        $this->title     = $title;
        $this->collapsed = $collapsed;
        $this->maxDepth  = max(1, $maxDepth);

        self::$used = true;

        $html = $this->render($var, $forceType);

        if ($echo) {
            self::push($html);
        }
    }

    /** Plugin uses this to decide whether to enqueue assets and inject dock. */
    public static function wasUsed(): bool
    {
        return self::$used;
    }

    /** Append a rendered dump block into the buffer. */
    public static function push(string $html): void
    {
        self::$buffer[] = $html;
        self::$used = true;
    }

    /**
     * Return buffered dumps (as a single HTML string) and clear the buffer.
     * Called by plugin in onAfterRender().
     */
    public static function flush(): string
    {
        if (!self::$buffer) {
            return '';
        }

        $out = implode("\n", self::$buffer);
        self::$buffer = [];

        return $out;
    }

    public function render(mixed $var, string $forceType = ''): string
    {
        $forceType = strtolower(trim($forceType));

        if ($forceType === 'xml') {
            return $this->renderRoot('xml', $this->rootTitle('xml'), $this->renderXml($var));
        }

        if ($forceType === 'array') {
            return $this->renderRoot('array', $this->rootTitle('array'), $this->renderArray($this->forceArray($var), 0));
        }

        if ($forceType === 'object') {
            return $this->renderRoot('object', $this->rootTitle('object'), $this->renderObject($this->forceObject($var), 0));
        }

        return $this->renderRoot('mixed', $this->rootTitle($this->typeLabel($var)), $this->renderValue($var, 0));
    }

    private function rootTitle(string $type): string
    {
        $base = $type . ': ' . $this->nb;

        if ($this->title !== '') {
            $base .= ' — ' . $this->title;
        }

        return $base;
    }

    /**
     * IMPORTANT:
     * Returns ONLY the dump block (<details class="dbug-block">...</details>).
     * No global wrapper/dock is emitted here (prevents DOM corruption).
     */
    private function renderRoot(string $kind, string $title, string $contentHtml): string
    {
        $openAttr = $this->collapsed ? '' : ' open';

        return <<<HTML
<details class="dbug-block"{$openAttr} data-kind="{$this->esc($kind)}">
  <summary class="dbug-summary">
    <span class="dbug-badge">dBug</span>
    <span class="dbug-title">{$this->esc($title)}</span>
  </summary>
  <div class="dbug-body-inner">
    {$contentHtml}
  </div>
</details>
HTML;
    }

    private function renderValue(mixed $var, int $depth): string
    {
        if ($depth >= $this->maxDepth) {
            return $this->renderNotice('Max depth reached');
        }

        return match (true) {
            is_array($var)     => $this->renderArray($var, $depth),
            is_object($var)    => $this->renderObject($var, $depth),
            is_resource($var)  => $this->renderScalar('[resource: ' . get_resource_type($var) . ']'),
            $var === null      => $this->renderScalar('NULL'),
            is_bool($var)      => $this->renderScalar($var ? 'TRUE' : 'FALSE'),
            is_string($var)    => $this->renderScalar($var === '' ? '[empty string]' : $var),
            default            => $this->renderScalar((string) $var),
        };
    }

    private function renderScalar(string $text): string
    {
        return '<pre class="dbug-pre">' . $this->esc($text) . '</pre>';
    }

    private function renderNotice(string $text): string
    {
        return '<div class="dbug-note">' . $this->esc($text) . '</div>';
    }

    private function renderArray(array $arr, int $depth): string
    {
        $key = $this->arrayKey($arr);

        if (isset($this->seenArrays[$key])) {
            return $this->renderNotice('*RECURSION* (array)');
        }

        $this->seenArrays[$key] = true;

        $count = count($arr);
        $openAttr = ($this->collapsed || $depth > 0) ? '' : ' open';

        $rows = '';
        foreach ($arr as $k => $v) {
            $rows .= $this->renderRow((string) $k, $this->renderValue($v, $depth + 1));
        }

        unset($this->seenArrays[$key]);

        return <<<HTML
<details class="dbug-item"{$openAttr}>
  <summary class="dbug-item-summary">
    <span class="dbug-pill">array</span>
    <span class="dbug-muted">{$count} item(s)</span>
  </summary>
  <div class="dbug-item-body">
    <div class="dbug-grid">
      {$rows}
    </div>
  </div>
</details>
HTML;
    }

    private function renderObject(object $obj, int $depth): string
    {
        $id = spl_object_id($obj);

        if (isset($this->seenObjects[$id])) {
            return $this->renderNotice('*RECURSION* (object: ' . get_class($obj) . ')');
        }

        $this->seenObjects[$id] = true;

        $class = get_class($obj);
        $openAttr = ($this->collapsed || $depth > 0) ? '' : ' open';

        $propsHtml = '';
        foreach (get_object_vars($obj) as $k => $v) {
            $propsHtml .= $this->renderRow((string) $k, $this->renderValue($v, $depth + 1));
        }

        $methodsHtml = '';
        $methods = get_class_methods($obj) ?: [];
        sort($methods);

        foreach ($methods as $m) {
            $methodsHtml .= $this->renderRow((string) $m, $this->renderNotice('[function]'));
        }

        unset($this->seenObjects[$id]);

        return <<<HTML
<details class="dbug-item"{$openAttr}>
  <summary class="dbug-item-summary">
    <span class="dbug-pill">object</span>
    <span class="dbug-muted">{$this->esc($class)}</span>
  </summary>
  <div class="dbug-item-body">
    <div class="dbug-subtitle">Properties</div>
    <div class="dbug-grid">
      {$propsHtml}
    </div>

    <div class="dbug-subtitle" style="margin-top:12px;">Methods</div>
    <div class="dbug-grid">
      {$methodsHtml}
    </div>
  </div>
</details>
HTML;
    }

    private function renderRow(string $key, string $valueHtml): string
    {
        return <<<HTML
<div class="dbug-row">
  <div class="dbug-key">{$this->esc($key)}</div>
  <div class="dbug-val">{$valueHtml}</div>
</div>
HTML;
    }

    private function renderXml(mixed $input): string
    {
        $xml = null;

        if (is_string($input)) {
            $xml = (is_file($input) && is_readable($input)) ? file_get_contents($input) : $input;
        }

        if (!is_string($xml) || trim($xml) === '') {
            return $this->renderNotice($this->error('xml'));
        }

        $xml = trim($xml);

        if (!class_exists(\DOMDocument::class)) {
            return $this->renderScalar($xml);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$ok) {
            $msg = $errors ? trim($errors[0]->message) : 'Invalid XML';
            return $this->renderNotice('XML error: ' . $msg);
        }

        return $this->renderScalar($dom->saveXML() ?: $xml);
    }

    private function error(string $type): string
    {
        $type = strtolower($type);
        $article = in_array(substr($type, 0, 1), ['a', 'e', 'i', 'o', 'u', 'x'], true) ? 'an' : 'a';
        return "Error: Variable cannot be {$article} {$type} type";
    }

    private function typeLabel(mixed $var): string
    {
        return match (true) {
            is_array($var)    => 'array',
            is_object($var)   => 'object',
            is_resource($var) => 'resource',
            is_bool($var)     => 'boolean',
            $var === null     => 'NULL',
            is_string($var)   => 'string',
            is_int($var)      => 'int',
            is_float($var)    => 'float',
            default           => 'mixed',
        };
    }

    private function forceArray(mixed $var): array
    {
        if (is_array($var)) {
            return $var;
        }

        if (is_object($var)) {
            return get_object_vars($var);
        }

        return [$var];
    }

    private function forceObject(mixed $var): object
    {
        return is_object($var) ? $var : (object) ['value' => $var];
    }

    private function arrayKey(array $arr): string
    {
        try {
            return hash('sha256', serialize($arr));
        } catch (\Throwable) {
            return (string) spl_object_id((object) $arr);
        }
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}