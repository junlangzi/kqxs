<?php
// Danh sách các loại xổ số và nguồn dữ liệu
$lottery_types = [
    'xsmb' => [
        'name' => 'Xổ Số Miền Bắc',
        'url' => 'https://raw.githubusercontent.com/khiemdoan/vietnam-lottery-xsmb-analysis/refs/heads/main/data/xsmb.json',
        'local' => 'data/xsmb.json'
    ],
    '645' => [
        'name' => 'Vietlott 6/45',
        'url' => 'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/power645.jsonl',
        'local' => 'data/power645.jsonl'
    ],
    '655' => [
        'name' => 'Vietlott 6/55',
        'url' => 'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/power655.jsonl',
        'local' => 'data/power655.jsonl'
    ],
    '3d' => [
        'name' => 'Vietlott 3D',
        'url' => 'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/3d.jsonl',
        'local' => 'data/3d.jsonl'
    ],
    '3dpro' => [
        'name' => 'Vietlott 3D Pro',
        'url' => 'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/3d_pro.jsonl',
        'local' => 'data/3d_pro.jsonl'
    ],
    'keno' => [
        'name' => 'Vietlott Keno',
        'url' => 'https://raw.githubusercontent.com/vietvudanh/vietlott-data/refs/heads/master/data/keno.jsonl',
        'local' => 'data/keno.jsonl'
    ]
];

// Hàm kiểm tra URL với timeout
function checkURL($url) {
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $headers = @get_headers($url, 0, $context);
    return $headers && strpos($headers[0], '200') !== false;
}

// Hàm kiểm tra file cục bộ
function checkLocalFile($file) {
    return file_exists($file);
}

// Hàm định dạng ngày
function formatDate($date) {
    return $date && ($timestamp = strtotime($date)) !== false ? date('d/m/Y', $timestamp) : 'N/A';
}

// Lấy dữ liệu từ nguồn
function getData($type, $url, $local) {
    $source = checkURL($url) ? $url : (checkLocalFile($local) ? $local : null);
    $data = [];
    if ($source) {
        if ($type === 'xsmb') {
            $jsonData = @file_get_contents($source);
            if ($jsonData === false) {
                error_log("Không thể đọc file $source");
                return [];
            }
            $data = json_decode($jsonData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Lỗi giải mã JSON: " . json_last_error_msg());
                return [];
            }
            return $data ?: [];
        } else {
            $data = [];
            $handle = @fopen($source, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line) {
                        $draw = json_decode($line, true);
                        if ($draw) $data[] = $draw;
                    }
                }
                fclose($handle);
            } else {
                error_log("Không thể mở file $source");
            }
            return $data;
        }
    }
    return $data;
}

// Xử lý loại xổ số được chọn
$selected_type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING) ?: 'xsmb';
if (!array_key_exists($selected_type, $lottery_types)) $selected_type = 'xsmb';
$data = getData($selected_type, $lottery_types[$selected_type]['url'], $lottery_types[$selected_type]['local']);

// Lấy danh sách ngày và kỳ quay có kết quả
$available_dates = [];
$available_draws = [];
if (!empty($data)) {
    if ($selected_type === 'xsmb' || $selected_type === 'keno') {
        $available_dates = array_unique(array_filter(array_map(function($r) use ($selected_type) {
            return isset($r['date']) && is_string($r['date']) && ($selected_type !== 'xsmb' || strlen($r['date']) >= 10)
                ? ($selected_type === 'xsmb' ? substr($r['date'], 0, 10) : $r['date'])
                : '';
        }, $data)));
        usort($available_dates, fn($a, $b) => strcmp($b, $a));
    }
    if ($selected_type !== 'xsmb') {
        usort($data, fn($a, $b) => strtotime($b['date'] ?? '1970-01-01') - strtotime($a['date'] ?? '1970-01-01'));
        $available_draws = array_map(function($r) {
            return [
                'id' => $r['id'] ?? '',
                'date' => $r['date'] ?? ''
            ];
        }, $data);
    }
}

// Lấy ngày và kỳ quay được chọn
$selected_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?: '';
if (!in_array($selected_date, $available_dates)) $selected_date = $available_dates[0] ?? '';
$selected_draw = filter_input(INPUT_GET, 'draw', FILTER_SANITIZE_STRING) ?: '';
$selected_result = null;

if (!empty($data)) {
    if ($selected_type === 'xsmb') {
        foreach ($data as $result) {
            if (isset($result['date']) && is_string($result['date']) && substr($result['date'], 0, 10) === $selected_date) {
                $selected_result = $result;
                break;
            }
        }
    } else {
        foreach ($data as $result) {
            if ($selected_draw && isset($result['id']) && $result['id'] === $selected_draw) {
                $selected_result = $result;
                $selected_date = $result['date'] ?? '';
                break;
            } elseif (($selected_type === 'keno' && isset($result['date']) && $result['date'] === $selected_date && !$selected_draw) || 
                     ($selected_type !== 'keno' && !$selected_draw && isset($result['id']))) {
                $selected_result = $result;
                $selected_draw = $result['id'] ?? '';
                break;
            }
        }
        if (!$selected_result && !$selected_draw && !empty($data[0])) {
            $selected_result = $data[0];
            $selected_draw = $selected_result['id'] ?? '';
            $selected_date = $selected_result['date'] ?? '';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm tra Kết Quả Xổ Số</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
        }
        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        .button {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .button:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        .button.active {
            background: #2c3e50;
        }
        .status {
            text-align: center;
            margin-bottom: 20px;
        }
        .status p {
            margin: 5px 0;
        }
        .status .online { color: green; }
        .status .offline { color: red; }
        .selector {
            text-align: center;
            margin-bottom: 20px;
        }
        .selector form {
            display: inline-flex;
            gap: 10px;
        }
        .selector select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
            min-width: 200px;
            max-height: 200px;
            overflow-y: auto;
        }
        .result {
            text-align: center;
        }
        .result h2 {
            font-size: 18px;
            color: #2c3e50;
            margin: 10px 0;
        }
        .circles {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .red-circle {
            background: #f44336;
        }
        .table-result {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table-result th, .table-result td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .table-result th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .number {
            display: inline-block;
            padding: 5px 10px;
            background: #e8f4fd;
            border-radius: 4px;
            margin: 2px;
            font-weight: bold;
            color: #2c3e50;
        }
        .keno-table {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            justify-items: center;
            margin-top: 20px;
        }
        @media (max-width: 600px) {
            .circle {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            .buttons {
                flex-direction: column;
                align-items: center;
            }
            .selector form {
                flex-direction: column;
                align-items: center;
            }
            .keno-table {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Kiểm tra Kết Quả Xổ Số</h1>
        <div class="buttons">
            <?php foreach ($lottery_types as $key => $type): ?>
                <a href="?type=<?php echo htmlspecialchars($key); ?>" class="button <?php echo $selected_type === $key ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($type['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Trạng thái nguồn dữ liệu -->
        <div class="status">
            <p>Link trực tuyến: <span class="<?php echo checkURL($lottery_types[$selected_type]['url']) ? 'online' : 'offline'; ?>">
                <?php echo checkURL($lottery_types[$selected_type]['url']) ? 'Hoạt động' : 'Không hoạt động'; ?></span></p>
            <p>File cục bộ: <span class="<?php echo checkLocalFile($lottery_types[$selected_type]['local']) ? 'online' : 'offline'; ?>">
                <?php echo checkLocalFile($lottery_types[$selected_type]['local']) ? 'Hoạt động' : 'Không hoạt động'; ?></span></p>
        </div>

        <!-- Chọn ngày và kỳ quay -->
        <div class="selector">
            <form method="get" action="">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($selected_type); ?>">
                <?php if ($selected_type === 'xsmb' || $selected_type === 'keno'): ?>
                    <select name="date" onchange="this.form.submit()">
                        <option value="">Chọn ngày</option>
                        <?php foreach ($available_dates as $date): ?>
                            <option value="<?php echo htmlspecialchars($date); ?>" <?php echo $date === $selected_date ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(formatDate($date)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?php if ($selected_type !== 'xsmb'): ?>
                    <select name="draw" onchange="this.form.submit()">
                        <option value="">Chọn kỳ quay</option>
                        <?php foreach ($available_draws as $draw): ?>
                            <?php if ($selected_type === 'keno' && $draw['date'] !== $selected_date) continue; ?>
                            <option value="<?php echo htmlspecialchars($draw['id']); ?>" <?php echo $draw['id'] === $selected_draw ? 'selected' : ''; ?>>
                                Kỳ <?php echo htmlspecialchars($draw['id']); ?> (<?php echo htmlspecialchars(formatDate($draw['date'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </form>
        </div>

        <!-- Hiển thị kết quả -->
        <div class="result">
            <?php if ($selected_result): ?>
                <h2><?php echo $selected_type === 'xsmb' ? '' : 'Kỳ ' . htmlspecialchars($selected_result['id'] ?? '') . ' - '; ?>Ngày <?php echo htmlspecialchars(formatDate($selected_date)); ?></h2>
                <?php
                switch ($selected_type) {
                    case 'xsmb':
                        echo "<table class='table-result'>";
                        echo "<tr><th>Giải đặc biệt</th><td class='number'>" . htmlspecialchars($selected_result['special'] ?? 'N/A') . "</td></tr>";
                        echo "<tr><th>Giải nhất</th><td class='number'>" . htmlspecialchars($selected_result['prize1'] ?? 'N/A') . "</td></tr>";
                        echo "<tr><th>Giải nhì</th><td>";
                        for ($i = 1; $i <= 2; $i++) {
                            echo "<span class='number'>" . htmlspecialchars($selected_result["prize2_$i"] ?? 'N/A') . "</span> ";
                        }
                        echo "</td></tr>";
                        echo "<tr><th>Giải ba</th><td>";
                        for ($i = 1; $i <= 6; $i++) {
                            echo "<span class='number'>" . htmlspecialchars($selected_result["prize3_$i"] ?? 'N/A') . "</span> ";
                        }
                        echo "</td></tr>";
                        echo "<tr><th>Giải tư</th><td>";
                        for ($i = 1; $i <= 4; $i++) {
                            echo "<span class='number'>" . htmlspecialchars($selected_result["prize4_$i"] ?? 'N/A') . "</span> ";
                        }
                        echo "</td></tr>";
                        echo "<tr><th>Giải năm</th><td>";
                        for ($i = 1; $i <= 6; $i++) {
                            echo "<span class='number'>" . htmlspecialchars($selected_result["prize5_$i"] ?? 'N/A') . "</span> ";
                        }
                        echo "</td></tr>";
                        echo "<tr><th>Giải sáu</th><td>";
                        for ($i = 1; $i <= 3; $i++) {
                            echo "<span class='number'>" . htmlspecialchars($selected_result["prize6_$i"] ?? 'N/A') . "</span> ";
                        }
                        echo "</td></tr>";
                        echo "<tr><th>Giải bảy</th><td>";
                        for ($i = 1; $i <= 4; $i++) {
                            echo "<span class='number'>" . htmlspecialchars($selected_result["prize7_$i"] ?? 'N/A') . "</span> ";
                        }
                        echo "</td></tr>";
                        echo "</table>";
                        break;
                    case '645':
                        $numbers = array_slice($selected_result['result'] ?? [], 0, 6);
                        echo '<div class="circles">';
                        foreach ($numbers as $num) {
                            echo "<div class='circle'>" . htmlspecialchars(str_pad($num, 2, '0', STR_PAD_LEFT)) . "</div>";
                        }
                        echo '</div>';
                        break;
                    case '655':
                        $numbers = array_slice($selected_result['result'] ?? [], 0, 6);
                        $bonus = $selected_result['result'][6] ?? 'N/A';
                        echo '<div class="circles">';
                        foreach ($numbers as $num) {
                            echo "<div class='circle'>" . htmlspecialchars(str_pad($num, 2, '0', STR_PAD_LEFT)) . "</div>";
                        }
                        echo "<div class='circle red-circle'>" . htmlspecialchars(str_pad($bonus, 2, '0', STR_PAD_LEFT)) . "</div>";
                        echo '</div>';
                        break;
                    case '3d':
                    case '3dpro':
                        echo '<table class="table-result">';
                        echo '<tr><th>Giải Đặc biệt</th><td>' . implode('', array_map(fn($num) => "<span class='number'>" . htmlspecialchars($num) . "</span>", $selected_result['result']['Giải Đặc biệt'] ?? [])) . '</td></tr>';
                        echo '<tr><th>Giải Nhất</th><td>' . implode('', array_map(fn($num) => "<span class='number'>" . htmlspecialchars($num) . "</span>", $selected_result['result']['Giải Nhất'] ?? [])) . '</td></tr>';
                        echo '<tr><th>Giải Nhì</th><td>' . implode('', array_map(fn($num) => "<span class='number'>" . htmlspecialchars($num) . "</span>", $selected_result['result']['Giải Nhì'] ?? [])) . '</td></tr>';
                        echo '<tr><th>Giải Ba</th><td>' . implode('', array_map(fn($num) => "<span class='number'>" . htmlspecialchars($num) . "</span>", $selected_result['result']['Giải ba'] ?? [])) . '</td></tr>';
                        echo '</table>';
                        break;
                    case 'keno':
                        echo '<div class="keno-table">';
                        foreach ($selected_result['result'] ?? [] as $num) {
                            echo "<div class='circle'>" . htmlspecialchars(str_pad($num, 2, '0', STR_PAD_LEFT)) . "</div>";
                        }
                        echo '</div>';
                        echo "<p>Chẵn/Lẻ: " . htmlspecialchars($selected_result['odd_even'] ?? 'N/A') . " - Lớn/Nhỏ: " . htmlspecialchars($selected_result['big_small'] ?? 'N/A') . "</p>";
                        break;
                }
                ?>
            <?php else: ?>
                <p>Không tìm thấy dữ liệu cho <?php echo htmlspecialchars($lottery_types[$selected_type]['name']); ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
