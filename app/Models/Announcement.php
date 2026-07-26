<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'content',
        'author',
        'pinned',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'published' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author');
    }

    /** 已发布，置顶在前、新的在前 */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('published', true)
            ->orderByDesc('pinned')
            ->orderByDesc('created_at');
    }

    /** Markdown → 安全 HTML（转义原始 HTML，链接加 nofollow） */
    public function renderedContent(): string
    {
        return static::markdown()->convert($this->content)->getContent();
    }

    /** 纯文本摘要（列表页用） */
    public function excerpt(int $limit = 150): string
    {
        $text = trim(strip_tags($this->renderedContent()));

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit).'…' : $text;
    }

    protected static ?MarkdownConverter $converter = null;

    protected static function markdown(): MarkdownConverter
    {
        if (static::$converter === null) {
            $environment = new Environment([
                'html_input' => 'escape',          // 原始 HTML 全部转义，防 XSS
                'allow_unsafe_links' => false,     // 禁 javascript: 等危险链接
                'max_nesting_level' => 20,
                'renderer' => [
                    'soft_break' => "<br>\n",      // 单个换行也断行，贴近用户直觉
                ],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new TableExtension());
            $environment->addExtension(new StrikethroughExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new DisallowedRawHtmlExtension());

            static::$converter = new MarkdownConverter($environment);
        }

        return static::$converter;
    }
}
