<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Report
 */
class ReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $file = $this->getFirstMedia('file');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'year' => $this->year,
            'title' => $this->t('title'),
            'summary' => $this->t('summary'),
            'cover' => MediaResource::make($this->mediaFor('cover')),
            // Relative, for the same reason as MediaResource: a download link
            // must not carry a hostname that may not be the one serving it.
            'fileUrl' => $file?->getUrl(),
            'fileSize' => $file?->human_readable_size,
            /*
             * "PDF", not "application/pdf". The card tells a visitor what they
             * are about to receive; a MIME type tells them what a server
             * thinks. The extension is the honest, readable form of the same
             * fact, and it needs no map to maintain.
             */
            'fileLabel' => $file === null
                ? null
                : mb_strtoupper(pathinfo((string) $file->file_name, PATHINFO_EXTENSION)),
        ];
    }
}
