<?php

namespace RahatulRabbi\TalkBridge\Support;

use Illuminate\Support\Facades\File;

/**
 * UserModelModifier
 *
 * Safely:
 *  1. Injects HasTalkBridgeFeatures trait (marker-based, surgical)
 *  2. Removes trait on uninstall
 *  3. Detects $fillable vs $guarded
 *  4. Adds required TalkBridge columns to $fillable based on config
 *  5. Removes those columns from $fillable on uninstall
 *
 * Never modifies $guarded — if the model uses $guarded = [] everything
 * is already mass-assignable, no changes needed.
 */
class UserModelModifier
{
    protected string $markerStart = '// @talkbridge:start';
    protected string $markerEnd   = '// @talkbridge:end';
    protected string $traitFqn    = '\\RahatulRabbi\\TalkBridge\\Traits\\HasTalkBridgeFeatures';

    public function __construct(protected string $userModelPath) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Inject trait + patch fillable.
     */
    public function inject(): void
    {
        $content = $this->read();

        if (! $this->isAlreadyInjected($content)) {
            $content = $this->addTrait($content);
        }

        $content = $this->patchFillable($content, 'add');

        $this->write($content);
    }

    /**
     * Remove trait + revert fillable changes.
     */
    public function remove(): void
    {
        $content = $this->read();
        $content = $this->removeTrait($content);
        $content = $this->patchFillable($content, 'remove');
        $this->write($content);
    }

    public function isAlreadyInjected(?string $content = null): bool
    {
        return str_contains($content ?? $this->read(), $this->markerStart);
    }

    // =========================================================================
    // Trait injection
    // =========================================================================

    protected function addTrait(string $content): string
    {
        $block = implode("\n", [
            '    ' . $this->markerStart,
            '    use ' . $this->traitFqn . ';',
            '    ' . $this->markerEnd,
        ]);

        // Insert right after the first class opening brace
        if (preg_match('/\{/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            return substr($content, 0, $pos + 1)
                . "\n" . $block . "\n"
                . substr($content, $pos + 1);
        }

        // Fallback: before last closing brace
        $last = strrpos($content, '}');
        return substr($content, 0, $last) . "\n    " . $block . "\n" . substr($content, $last);
    }

    protected function removeTrait(string $content): string
    {
        $pattern = '/\n?\s*' . preg_quote($this->markerStart, '/') . '.*?' . preg_quote($this->markerEnd, '/') . '\s*\n?/s';
        return preg_replace($pattern, "\n", $content);
    }

    // =========================================================================
    // Fillable / Guarded patching
    // =========================================================================

    protected function patchFillable(string $content, string $mode): string
    {
        // If model uses $guarded — nothing to do, everything is mass-assignable
        if ($this->modelUsesGuarded($content)) {
            return $content;
        }

        if (! $this->modelUsesFillable($content)) {
            return $content;
        }

        $fields = $this->resolveFieldsToAdd();

        if (empty($fields)) {
            return $content;
        }

        if ($mode === 'add') {
            foreach ($fields as $field) {
                $content = $this->addFieldToFillable($content, $field);
            }
        } elseif ($mode === 'remove') {
            foreach ($fields as $field) {
                $content = $this->removeFieldFromFillable($content, $field);
            }
        }

        return $content;
    }

    /**
     * Collect all column names TalkBridge may need in $fillable,
     * based on what is configured in config/talkbridge.php.
     */
    protected function resolveFieldsToAdd(): array
    {
        $fields = [];

        // last_seen column
        $lastSeen = config('talkbridge.user_fields.last_seen', 'last_seen_at');
        if ($lastSeen) {
            $fields[] = $lastSeen;
        }

        // avatar column
        $avatar = config('talkbridge.user_fields.avatar');
        if ($avatar) {
            $fields[] = $avatar;
        }

        // is_active column
        $isActive = config('talkbridge.user_fields.is_active');
        if ($isActive) {
            $fields[] = $isActive;
        }

        // name column(s)
        $nameConfig = config('talkbridge.user_fields.name', 'name');
        if (is_array($nameConfig)) {
            foreach ($nameConfig as $col) {
                if ($col && $col !== 'name') {
                    $fields[] = $col;
                }
            }
        } elseif ($nameConfig && $nameConfig !== 'name') {
            $fields[] = $nameConfig;
        }

        return array_unique(array_filter($fields));
    }

    protected function modelUsesGuarded(string $content): bool
    {
        return (bool) preg_match('/\$guarded\s*=\s*\[/', $content);
    }

    protected function modelUsesFillable(string $content): bool
    {
        return str_contains($content, '$fillable');
    }

    protected function addFieldToFillable(string $content, string $field): string
    {
        // Skip if already present
        if (str_contains($content, "'{$field}'") || str_contains($content, "\"{$field}\"")) {
            return $content;
        }

        // Append inside the fillable array before its closing bracket
        return preg_replace_callback(
            '/(\$fillable\s*=\s*\[)(.*?)(\s*\];)/s',
            function ($matches) use ($field) {
                $body     = rtrim($matches[2]);
                $trailing = str_ends_with($body, ',') ? '' : ',';
                return $matches[1] . $body . $trailing . "\n        '{$field}'," . "\n    " . $matches[3];
            },
            $content
        );
    }

    protected function removeFieldFromFillable(string $content, string $field): string
    {
        // Remove lines that contain the field — handles both single and double quotes
        $content = preg_replace("/\s*['\"]" . preg_quote($field, '/') . "['\"],?\n/", "\n", $content);
        return $content;
    }

    // =========================================================================
    // File I/O
    // =========================================================================

    protected function read(): string
    {
        if (! File::exists($this->userModelPath)) {
            throw new \RuntimeException("User model not found: {$this->userModelPath}");
        }
        return File::get($this->userModelPath);
    }

    protected function write(string $content): void
    {
        File::put($this->userModelPath, $content);
    }
}
