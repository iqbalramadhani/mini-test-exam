<?php

class AdminLogsController
{
    public function index(): bool
    {
        Security::requireRole('admin');
        
        $logFile = ROOT . 'tmp/logs/app.log';
        if (!file_exists($logFile)) {
            apiRespond(['logs' => []]);
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            apiJsonError('Gagal membaca file log', 500);
        }
        
        $lastLines = array_slice($lines, -500);
        
        // Reverse so the newest log is at the top
        $lastLines = array_reverse($lastLines);
        
        apiRespond(['logs' => $lastLines]);
        return true;
    }
}
