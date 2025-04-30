<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Asia/Bangkok (GMT+7)
date_default_timezone_set('Asia/Bangkok');

// Define the URLs to check
$urls = [
    'https://raw.githubusercontent.com/khiemdoan/vietnam-lottery-xsmb-analysis/refs/heads/main/data/xsmb.json',
    'https://raw.githubusercontent.com/khiemdoan/vietnam-lottery-xsmb-analysis/refs/heads/main/data/xsmb-2-digits.json',
    'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/power655.jsonl',
    'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/power645.jsonl',
    'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/keno.jsonl',
    'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/3d.jsonl',
    'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/3d_pro.jsonl'
];

// Current UTC time and user info
$currentDateTime = gmdate('Y-m-d H:i:s'); // Lấy thời gian UTC hiện tại
$currentUser = 'SYS admin';

// Convert UTC to GMT+7
$dateTimeUTC = new DateTime($currentDateTime, new DateTimeZone('UTC'));
$dateTimeUTC->setTimezone(new DateTimeZone('Asia/Bangkok'));
$currentDateTime = $dateTimeUTC->format('Y-m-d H:i:s');

// Function to format date from yyyy-mm-dd to dd/mm/yyyy
function formatDate($date) {
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}

// Function to write to log file
function writeLog($message) {
    global $currentDateTime, $currentUser;
    $formattedDateTime = date('d/m/Y H:i:s', strtotime($currentDateTime));
    $logMessage = "[{$formattedDateTime}] [{$currentUser}] {$message}\n";
    file_put_contents(__DIR__ . '/log.txt', $logMessage, FILE_APPEND);
}

// Function to get the latest date from JSON/JSONL file
function getLatestDate($content, $isJsonl = false, $fileName = '') {
    // Xử lý đặc biệt cho xsmb.json và xsmb-2-digits.json
    if (strpos($fileName, 'xsmb') !== false) {
        $data = json_decode($content, true);
        if (is_array($data) && !empty($data)) {
            $lastItem = end($data);
            return isset($lastItem['date']) ? substr($lastItem['date'], 0, 10) : null;
        }
    }
    
    // Xử lý cho các file JSONL
    if ($isJsonl) {
        $lines = explode("\n", trim($content));
        foreach (array_reverse($lines) as $line) {
            if (empty(trim($line))) continue;
            $data = json_decode(trim($line), true);
            if ($data && isset($data['date'])) {
                return substr($data['date'], 0, 10);
            }
        }
    } else {
        // Xử lý cho các file JSON khác
        $data = json_decode($content, true);
        if (is_array($data) && !empty($data)) {
            foreach (array_reverse($data) as $item) {
                if (isset($item['date'])) {
                    return substr($item['date'], 0, 10);
                }
            }
        }
    }
    return null;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đồng bộ dữ liệu xổ số</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 80px auto 20px;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2980b9;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            font-size: 1.8em;
            font-weight: 600;
        }
        
        .sync-info {
            text-align: center;
            margin-bottom: 30px;
            color: #666;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .sync-info p {
            margin: 8px 0;
            font-size: 1.1em;
        }

        .user-name {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .sync-item {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        h3 {
            margin: 0 0 15px 0;
            color: #2980b9;
            font-size: 1.2em;
            font-weight: 600;
        }
        
        .progress-container {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .progress-bar {
            width: 0;
            height: 100%;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            transition: width 0.5s ease-in-out;
        }
        
        .progress-bar.error {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .status {
            font-size: 0.95em;
            padding: 10px 15px;
            border-radius: 8px;
            background: #f8f9fa;
            font-weight: 500;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status.info {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
                padding: 15px;
            }
            
            .sync-item {
                padding: 15px;
            }
            
            h1 {
                font-size: 1.5em;
            }
            
            .sync-info p {
                font-size: 1em;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 10px;
            }
            
            .sync-item {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Đồng bộ dữ liệu xổ số</h1>
        <div class="sync-info">
            <p>Thời gian đồng bộ: <span class="time"><?php echo date('d/m/Y H:i:s', strtotime($currentDateTime)); ?></span></p>
            <p>Người thực hiện: <span class="user-name"><?php echo htmlspecialchars($currentUser); ?></span></p>
        </div>

        <?php
        foreach ($urls as $index => $url) {
            $fileName = basename($url);
            $localPath = __DIR__ . '/' . $fileName;
            $isJsonl = pathinfo($fileName, PATHINFO_EXTENSION) === 'jsonl';
            
            echo "<div class='sync-item' id='sync-{$index}'>";
            echo "<h3>Đang xử lý: {$fileName}</h3>";
            echo "<div class='progress-container'>";
            echo "<div class='progress-bar' id='progress-{$index}'></div>";
            echo "</div>";
            echo "<div class='status' id='status-{$index}'>Đang kiểm tra...</div>";
            
            flush();
            ob_flush();
            
            try {
                // Download new content
                $newContent = @file_get_contents($url);
                if ($newContent === false) {
                    throw new Exception("Không thể tải file từ {$url}");
                }
                
                if (!file_exists($localPath)) {
                    // File doesn't exist locally, save it
                    file_put_contents($localPath, $newContent);
                    $remoteDate = getLatestDate($newContent, $isJsonl, $fileName);
                    $formattedDate = formatDate($remoteDate);
                    $message = "Đã tải file mới: {$fileName} (Ngày dữ liệu: {$formattedDate})";
                    writeLog($message);
                    echo "<script>
                        document.getElementById('progress-{$index}').style.width = '100%';
                        document.getElementById('status-{$index}').innerHTML = 'Đã tải file thành công! (Ngày dữ liệu: {$formattedDate})';
                        document.getElementById('status-{$index}').className = 'status success';
                    </script>";
                } else {
                    // File exists, compare content
                    $localContent = file_get_contents($localPath);
                    $localDate = getLatestDate($localContent, $isJsonl, $fileName);
                    $remoteDate = getLatestDate($newContent, $isJsonl, $fileName);
                    
                    if ($localDate !== $remoteDate) {
                        // Update local file
                        file_put_contents($localPath, $newContent);
                        $formattedLocalDate = formatDate($localDate);
                        $formattedRemoteDate = formatDate($remoteDate);
                        $message = "Đã cập nhật file {$fileName}: từ ngày {$formattedLocalDate} lên {$formattedRemoteDate}";
                        writeLog($message);
                        echo "<script>
                            document.getElementById('progress-{$index}').style.width = '100%';
                            document.getElementById('status-{$index}').innerHTML = 'Đã cập nhật! (Từ ngày {$formattedLocalDate} lên {$formattedRemoteDate})';
                            document.getElementById('status-{$index}').className = 'status success';
                        </script>";
                    } else {
                        // No update needed
                        $formattedDate = formatDate($localDate);
                        $message = "File {$fileName} đã được cập nhật (Ngày dữ liệu: {$formattedDate})";
                        writeLog($message);
                        echo "<script>
                            document.getElementById('progress-{$index}').style.width = '100%';
                            document.getElementById('status-{$index}').innerHTML = 'Dữ liệu đã cập nhật (Ngày dữ liệu: {$formattedDate})';
                            document.getElementById('status-{$index}').className = 'status info';
                        </script>";
                    }
                }
            } catch (Exception $e) {
                $message = "Lỗi xử lý file {$fileName}: " . $e->getMessage();
                writeLog($message);
                echo "<script>
                    document.getElementById('progress-{$index}').style.width = '100%';
                    document.getElementById('progress-{$index}').className = 'progress-bar error';
                    document.getElementById('status-{$index}').innerHTML = 'Lỗi: {$e->getMessage()}';
                    document.getElementById('status-{$index}').className = 'status error';
                </script>";
            }
            
            echo "</div>";
            
            flush();
            ob_flush();
        }
        ?>
    </div>
</body>
</html>