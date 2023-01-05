<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConvertDocumentTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:convert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command converts all word documents in templates folder to html';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $pandoc = shell_exec('pandoc -v');
        if (strpos($pandoc, 'John MacFarlane') === false) {
            $this->warn('Pandoc not found!');
            exit;
        }

        $path = base_path('database' . DIRECTORY_SEPARATOR . 'seeders' . DIRECTORY_SEPARATOR . 'DocumentAutomation' . DIRECTORY_SEPARATOR . 'templates');
        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'docx') {
                $content = $this->convertDocxToHTML($file->getRealPath());
                $filename = $file->getFilenameWithoutExtension() . '.html';
                if (File::put($path . DIRECTORY_SEPARATOR . $filename, $content)) {
                    $this->info('Converted => ' . $file->getFilename());
                    File::delete($file->getRealPath());
                }
            }
        }

        return 0;
    }

    private function convertDocxToHTML(string $path): string
    {
        $pattern = "/\[[^]]*]/";
        $html = shell_exec("pandoc \"$path\" --from docx --to html");
        $html = preg_replace($pattern, '<span style="background-color: rgb(255, 255, 0);">$0</span>', $html);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        //remove weird TOC numbers
        //matches <a [whitespace or line break] href="#[alphanumberic with dashes]">[number]</a>
        $html = preg_replace('/<a\s?[\n\r]?href="#[a-zA-Z0-9-_]+">\d+<\/a>/', '', $html);
        return str_replace('<td></td>', '<td style="background-color: yellow"></td>', $html);
    }
}
