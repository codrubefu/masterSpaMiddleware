<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ModelSaveLogController extends Controller
{
    public function index(): View
    {
        $logFiles = glob(storage_path('logs/model-saves*.log')) ?: [];
        rsort($logFiles);

        $entries = [];

        foreach ($logFiles as $filePath) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lines as $line) {
                preg_match('/^\[(?<logged_at>[^\]]+)\]\s+\w+\.\w+:\s+Model saved\s+(?<context>\{.*\})\s+\[\]$/', $line, $matches);

                if (! isset($matches['context'])) {
                    continue;
                }

                $context = json_decode($matches['context'], true);

                if (! is_array($context)) {
                    continue;
                }

                $entries[] = [
                    'logged_at' => $matches['logged_at'] ?? null,
                    'saved_at' => $context['saved_at'] ?? null,
                    'event' => $context['event'] ?? null,
                    'model' => $context['model'] ?? null,
                    'table' => $context['table'] ?? null,
                    'primary_key' => $context['primary_key'] ?? null,
                    'controller' => $context['controller'] ?? null,
                    'saved_fields_json' => json_encode($context['saved_fields'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        usort($entries, fn (array $first, array $second) => strcmp($second['saved_at'] ?? '', $first['saved_at'] ?? ''));

        return view('model_save_logs', [
            'entries' => $entries,
        ]);
    }
}
