<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class LogsController extends Controller
{
    /**
     * Display logs management page
     * Mostra la pagina di gestione dei logs
     *
     * @param Request $request HTTP request with optional path parameter
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Logs view or error redirect
     */
    public function index(Request $request)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        $currentPath = $request->get('path', '');
        $logsBasePath = storage_path('logs');
        
        // Sanitize and validate path
        $fullPath = $this->sanitizePath($logsBasePath, $currentPath);
        
        if (!$fullPath || !File::exists($fullPath)) {
            return redirect()->route('admin.logs')->with('error', 'Percorso non valido o inesistente');
        }

        $breadcrumbs = $this->generateBreadcrumbs($currentPath);
        $items = $this->getDirectoryContents($fullPath, $currentPath);
        $fileContent = null;
        $isFile = File::isFile($fullPath);

        if ($isFile) {
            $fileContent = $this->getFileContent($fullPath);
        }

        return view('admin.logs', compact('items', 'breadcrumbs', 'currentPath', 'fileContent', 'isFile'));
    }

    /**
     * Sanitize and validate path to prevent directory traversal
     * Sanifica e valida il percorso per prevenire directory traversal
     *
     * @param string $basePath Base logs directory path
     * @param string $requestedPath Requested relative path
     * @return string|null Sanitized full path or null if invalid
     */
    private function sanitizePath($basePath, $requestedPath)
    {
        if (empty($requestedPath)) {
            return $basePath;
        }

        // Remove any potential directory traversal attempts
        $requestedPath = str_replace(['../', '..\\', '../', '..\\'], '', $requestedPath);
        $requestedPath = ltrim($requestedPath, '/\\');
        
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $requestedPath;
        $realPath = realpath($fullPath);
        
        // Ensure the path is within the logs directory
        if ($realPath && strpos($realPath, realpath($basePath)) === 0) {
            return $realPath;
        }
        
        return null;
    }

    /**
     * Generate breadcrumb navigation
     * Genera la navigazione breadcrumb
     *
     * @param string $currentPath Current relative path
     * @return array Array of breadcrumb items
     */
    private function generateBreadcrumbs($currentPath)
    {
        $breadcrumbs = [
            ['name' => 'logs', 'path' => '']
        ];

        if (!empty($currentPath)) {
            $pathParts = explode(DIRECTORY_SEPARATOR, trim($currentPath, DIRECTORY_SEPARATOR));
            $buildPath = '';
            
            foreach ($pathParts as $part) {
                $buildPath .= ($buildPath ? DIRECTORY_SEPARATOR : '') . $part;
                $breadcrumbs[] = [
                    'name' => $part,
                    'path' => $buildPath
                ];
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get directory contents with file/folder information
     * Ottiene il contenuto della directory con informazioni su file/cartelle
     *
     * @param string $fullPath Full path to directory
     * @param string $currentPath Current relative path
     * @return array Array of directory items
     */
    private function getDirectoryContents($fullPath, $currentPath)
    {
        if (!File::isDirectory($fullPath)) {
            return [];
        }

        $items = [];
        $files = File::files($fullPath);
        $directories = File::directories($fullPath);

        // Add directories first
        foreach ($directories as $directory) {
            $dirName = basename($directory);
            $relativePath = $currentPath ? $currentPath . DIRECTORY_SEPARATOR . $dirName : $dirName;
            
            $items[] = [
                'name' => $dirName,
                'type' => 'directory',
                'path' => $relativePath,
                'size' => null,
                'modified' => File::lastModified($directory)
            ];
        }

        // Add files
        foreach ($files as $file) {
            $fileName = basename($file);
            $relativePath = $currentPath ? $currentPath . DIRECTORY_SEPARATOR . $fileName : $fileName;
            
            $items[] = [
                'name' => $fileName,
                'type' => 'file',
                'path' => $relativePath,
                'size' => File::size($file),
                'modified' => File::lastModified($file)
            ];
        }

        // Sort: directories first, then files, both alphabetically
        usort($items, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    /**
     * Get file content without size limit
     * Ottiene il contenuto del file senza limite di dimensione
     *
     * @param string $filePath Full path to file
     * @return array File content information
     */
    private function getFileContent($filePath)
    {
        $fileSize = File::size($filePath);

        try {
            $content = File::get($filePath);
            return [
                'content' => $content,
                'error' => null,
                'size' => $fileSize,
                'lines' => substr_count($content, "\n") + 1
            ];
        } catch (\Exception $e) {
            return [
                'content' => null,
                'error' => 'Errore nella lettura del file: ' . $e->getMessage(),
                'size' => $fileSize
            ];
        }
    }

    /**
     * Format file size for display
     * Formatta la dimensione del file per la visualizzazione
     *
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    public static function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
