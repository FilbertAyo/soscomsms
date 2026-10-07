<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenderIdRequestDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'sender_id_request_id',
        'original_name',
        'size',
        'contents',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'contents',
    ];

    /**
     * @return BelongsTo<SenderIdRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(SenderIdRequest::class, 'sender_id_request_id');
    }

    public function bytes(): string
    {
        $contents = $this->contents;

        if (is_resource($contents)) {
            $contents = stream_get_contents($contents);
        }

        return is_string($contents) ? $contents : '';
    }

    public function inlineFilename(): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $this->original_name) ?: 'identity-document.pdf';
        $name = trim((string) $name, '-');

        if ($name === '') {
            $name = 'identity-document.pdf';
        }

        if (! str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        return $name;
    }
}
