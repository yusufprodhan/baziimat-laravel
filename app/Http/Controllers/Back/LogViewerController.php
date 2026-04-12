<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('adminlocalize');
    }

    public function index(Request $request)
    {
        $logPath = storage_path('logs');
        $logFiles = [];

        if (File::isDirectory($logPath)) {
            $files = File::glob($logPath . '/*.log');
            foreach ($files as $file) {
                $logFiles[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $this->formatBytes(File::size($file)),
                    'last_modified' => date('Y-m-d H:i:s', File::lastModified($file)),
                ];
            }
        }

        // Sort by last modified descending
        usort($logFiles, function ($a, $b) {
            return strtotime($b['last_modified']) - strtotime($a['last_modified']);
        });

        $selectedFile = $request->get('file', 'laravel.log');
        $logContent = '';
        $logEntries = [];
        $filePath = $logPath . '/' . basename($selectedFile);

        if (File::exists($filePath)) {
            $logContent = File::get($filePath);

            // Parse log entries - read last 500 lines max
            $lines = explode("\n", $logContent);
            $totalLines = count($lines);
            $startLine = max(0, $totalLines - 500);
            $lines = array_slice($lines, $startLine);

            $currentEntry = null;
            foreach ($lines as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[\+\-]?\d*:?\d*)\]\s+(\w+)\.(\w+):\s*(.*)/', $line, $matches)) {
                    if ($currentEntry) {
                        $logEntries[] = $currentEntry;
                    }
                    $currentEntry = [
                        'timestamp' => $matches[1],
                        'environment' => $matches[2],
                        'level' => strtolower($matches[3]),
                        'message' => $matches[4],
                        'stack' => '',
                    ];
                } elseif ($currentEntry && trim($line) !== '') {
                    $currentEntry['stack'] .= $line . "\n";
                }
            }
            if ($currentEntry) {
                $logEntries[] = $currentEntry;
            }

            // Reverse so newest entries appear first
            $logEntries = array_reverse($logEntries);
        }

        return view('back.log-viewer.index', compact('logFiles', 'logEntries', 'selectedFile'));
    }

    public function clear(Request $request)
    {
        $file = $request->get('file', 'laravel.log');
        $filePath = storage_path('logs/' . basename($file));

        if (File::exists($filePath)) {
            File::put($filePath, '');
            return redirect()->route('back.log.index', ['file' => $file])
                ->withSuccess(__('Log file has been cleared successfully.'));
        }

        return redirect()->route('back.log.index')
            ->withError(__('Log file not found.'));
    }

    public function delete(Request $request)
    {
        $file = $request->get('file');
        $filePath = storage_path('logs/' . basename($file));

        if ($file && File::exists($filePath) && basename($file) !== '.gitignore') {
            File::delete($filePath);
            return redirect()->route('back.log.index')
                ->withSuccess(__('Log file has been deleted successfully.'));
        }

        return redirect()->route('back.log.index')
            ->withError(__('Log file not found.'));
    }

    public function download(Request $request)
    {
        $file = $request->get('file', 'laravel.log');
        $filePath = storage_path('logs/' . basename($file));

        if (File::exists($filePath)) {
            return response()->download($filePath);
        }

        return redirect()->route('back.log.index')
            ->withError(__('Log file not found.'));
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
