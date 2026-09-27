<?php
/**
 * 5人輪值排班程式 (PHP 版本)
 * 支援手動選擇月份，自動排出班表。
 */

// 設定語系與時區
date_default_timezone_set('Asia/Taipei');

// 1. 取得選擇的年份與月份
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// 2. 基本資料設定
$people = [
    ["name" => "AAA", "id" => "001"],
    ["name" => "BBB", "id" => "002"],
    ["name" => "CCC", "id" => "003"],
    ["name" => "DDD", "id" => "004"],
    ["name" => "EEE", "id" => "005"]
];
$weekdayShifts = ["1", "2", "3", "4", "5"]; 
$numDays = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$weekdaysMap = ['日', '一', '二', '三', '四', '五', '六'];

// 3. 定義假日邏輯
function isHoliday($y, $m, $d) {
    $timestamp = strtotime("$y-$m-$d");
    $w = (int)date('w', $timestamp);
    if ($w == 0 || $w == 6) return true; // 週六、日
    // 可在此處加入特定國定假日
    if ($m == 4 && ($d == 3 || $d == 6)) return true; 
    return false;
}

// 4. 排班核心邏輯
$schedule = [];
foreach ($people as $p) {
    $schedule[$p['name']] = array_fill(1, $numDays, "");
}

$holidayPtr = 0;
$holidayAssignments = []; 
$holidayGroups = [];
$currentGroup = 0;
$inHoliday = false;

// 假日分組
for ($d = 1; $d <= $numDays; $d++) {
    if (isHoliday($selectedYear, $selectedMonth, $d)) {
        if (!$inHoliday) { $currentGroup++; $inHoliday = true; }
        $holidayGroups[$d] = $currentGroup;
    } else { $inHoliday = false; }
}

// 分配班別
for ($d = 1; $d <= $numDays; $d++) {
    if (isHoliday($selectedYear, $selectedMonth, $d)) {
        $groupId = $holidayGroups[$d];
        if (!isset($holidayAssignments[$groupId])) {
            $holidayAssignments[$groupId] = [$holidayPtr % 5, ($holidayPtr + 1) % 5];
            $holidayPtr += 2;
        }
        foreach ($holidayAssignments[$groupId] as $pIdx) {
            $schedule[$people[$pIdx]['name']][$d] = "H";
        }
    } else {
        foreach ($people as $idx => $p) {
            $schedule[$p['name']][$d] = $weekdayShifts[($idx + $d) % 5];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title><?php echo $selectedYear - 1911; ?>年<?php echo $selectedMonth; ?>月 排班表</title>
    <style>
        body { font-family: "Microsoft JhengHei", sans-serif; padding: 20px; background-color: #f4f7f6; }
        .controls { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; text-align: center; }
        .table-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow-x: auto; }
        .title { text-align: center; font-size: 1.6em; font-weight: bold; margin-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; border: 2px solid #333; }
        th, td { border: 1px solid #333; height: 35px; text-align: center; font-size: 0.9em; }
        .header-cell { background-color: #e9ecef; font-weight: bold; }
        .weekend { background-color: #fff0f0; }
        .h-duty { font-weight: bold; color: #d00; background-color: #ffe3e3; }
        @media print { .controls { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="controls">
        <form method="GET">
            年份：<select name="year"><?php for($y=2025; $y<=2030; $y++) echo "<option value='$y' ".($y==$selectedYear?'selected':'').">".($y-1911)."年 ($y)</option>"; ?></select>
            月份：<select name="month"><?php for($m=1; $m<=12; $m++) echo "<option value='$m' ".($m==$selectedMonth?'selected':'').">{$m}月</option>"; ?></select>
            <button type="submit">排出班表</button>
        </form>
    </div>
<div class="table-container">
        <div class="title">中華民國<?php echo $selectedYear - 1911; ?>年<?php echo $selectedMonth; ?>月份日班業務處理科人員輪值表</div>
        <div style="text-align:right; margin-bottom:10px;">日 班 (上班時間: 07:30 ~ 15:40)</div>
        <table>
            <thead>
                <tr>
                    <th class="header-cell" style="width:40px;">班</th>
                    <th class="header-cell" colspan="2">員 工</th>
                    <?php for($d=1; $d<=$numDays; $d++) echo "<th class='header-cell'>$d</th>"; ?>
                </tr>
                <tr>
                    <th class="header-cell">別</th><th class="header-cell" style="width:80px;">姓名</th><th class="header-cell" style="width:60px;">編號</th>
                    <?php for($d=1; $d<=$numDays; $d++) echo "<th class='header-cell'>".$weekdaysMap[date('w', strtotime("$selectedYear-$selectedMonth-$d"))]."</th>"; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($people as $p): ?>
                <tr>
                    <td class="header-cell">日</td><td style="font-weight:bold;"><?php echo $p['name']; ?></td><td><?php echo $p['id']; ?></td>
                    <?php for($d=1; $d<=$numDays; $d++): 
                        $val = $schedule[$p['name']][$d];
                        $class = isHoliday($selectedYear, $selectedMonth, $d) ? 'weekend' : '';
                        if ($val == "H") $class .= ' h-duty';
                        echo "<td class='$class'>$val</td>";
                    endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:15px; font-weight:bold;">備註：H 為假日值班；平日班別 1-5 輪替。</div>
    </div>
</body>
</html>