<?php

$confDir = '/home/ga/.aqi-bat';
$stateFile = $confDir . '/state.json';
$prefsFile = $confDir . '/prefs.json';
$state = json_decode(file_get_contents($stateFile), true);
$prefs = json_decode(file_get_contents($prefsFile), true);

foreach (['hi', 'low'] as $pref) {
  foreach ($_POST as $key => $value) {
    if ($key == "$pref-$value") {
      $prefs['thresholds'][$pref] = $value;
      write($prefsFile, $prefs);
      if (shouldResetNotification($pref, $value, $state)) {
        unset($state['last-notification']);
        write($stateFile, $state);
      }
    }
  }
}

header("Location: /");

function shouldResetNotification($pref, $value, $state)
{
  if ($state['last-notification'] != $pref) {
    return false;
  }
  if ($pref == 'hi') {
    return $value > $state['aqi'];
  }
  return $value < $state['aqi'];
}

function write($file, $data)
{
  file_put_contents($file, json_encode($data));
}
