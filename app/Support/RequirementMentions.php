<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Gordon reads rules, not numbers: every "#N" in text shown to him becomes that rule's title (cut to 20 characters,
 * in quotes; the HTML form shows the full title on hover).
 */
final readonly class RequirementMentions
{
    private const PATTERN = '/#(\d+)/u';

    /**
     * @param  array<int, string>  $titles  requirement number => title
     */
    public function __construct(private array $titles) {}

    public function plain(?string $text): string
    {
        return (string) preg_replace_callback(self::PATTERN, fn (array $match): string => $this->quoted((int) $match[1]), (string) $text);
    }

    public function html(?string $text): HtmlString
    {
        $parts = preg_split(self::PATTERN, (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $html = '';

        foreach ($parts as $index => $part) {
            $html .= $index % 2 === 0
                ? e($part)
                : '<span class="underline decoration-dotted" title="'.e($this->titles[(int) $part] ?? '').'">'.e($this->quoted((int) $part)).'</span>';
        }

        return new HtmlString($html);
    }

    private function quoted(int $number): string
    {
        return isset($this->titles[$number]) ? '「'.Str::limit($this->titles[$number], 20, '…').'」' : '另一条规则';
    }
}
