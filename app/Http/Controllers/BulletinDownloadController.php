<?php

namespace App\Http\Controllers;

use App\Models\Bulletin;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BulletinDownloadController extends Controller
{
    public function download(Bulletin $bulletin): BinaryFileResponse
    {
        $media = $bulletin->getFirstMedia('bulletin_pdf');

        if (! $media) {
            abort(404, 'Le PDF du bulletin n\'est pas encore disponible.');
        }

        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        return response()->file($media->getPath(), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $media->file_name . '"',
        ]);
    }
}
