<?php

$confDir = '/home/ga/.aqi-bat';
$stateFile = $confDir . '/state.json';
$prefsFile = $confDir . '/prefs.json';
$state = json_decode(file_get_contents($stateFile), true);
$prefs = json_decode(file_get_contents($prefsFile), true);

$aqi = $state['aqi'];
$timestamp = date('Y-m-d h:i A', $state['sensor-read-timestamp']);
$sensor = $state['sensor-label'];

print "<p>AQI at sensor '$sensor' as of $timestamp: $aqi</p>";

print "<form method='POST' action='threshold.php'>";

print "<p>Set high and low thresholds:</p>";

print "<table>";
print "<tr>";
for ($i=5; $i<=25; ++$i) {
  tableCell('hi', $i * 10, $prefs['thresholds']['hi'], $i * 10 > $prefs['thresholds']['low']);
}
print "</tr>";
print "<tr>";
for ($i=5; $i<=25; ++$i) {
  tableCell('low', $i * 10, $prefs['thresholds']['low'], $i * 10 < $prefs['thresholds']['hi']);
}
print "</tr>";
print "</table>";
print "</form>";

function tableCell($label, $aqi, $current, $ok)
{
  if (!$ok) {
    print "<td></td>";
    return;
  }
  if ($aqi == $current) {
    print "<td style='border: 3px solid black;'><b>$aqi</b></td>";
    return;
  }
  print "<td>";
    print "<input type='submit' value='$aqi' name='$label-$aqi'/>";
  print "</td>";
}
