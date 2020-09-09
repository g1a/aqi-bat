<?php

$useLiveData = true;

$configDir = getenv('HOME') . '/.aqi-bat';
@mkdir($configDir);

$prefsFilename = $configDir . '/prefs.json';
$stateFilename = $configDir . '/state.json';

$defaultPrefs = [
  // Sierra Point Road
  // 'https://www.purpleair.com/json?key=XDLPNJRGL7S1G4JA&show=53461'
  // San Benito Road
  'sensor-url' => 'https://www.purpleair.com/json?key=VG6SPX3AMNOR6MKW&show=55195',
  'thresholds' => [
    'hi' => 100,
    'low' => 90,
  ],
];

$defaultState = [
  'aqi' => 90,
];

if (!file_exists($prefsFilename)) {
  file_put_contents($prefsFilename, json_encode($defaultPrefs));
}

if (!file_exists($stateFilename)) {
  file_put_contents($stateFilename, json_encode($defaultState));
}

$config = json_decode(file_get_contents($prefsFilename), true);
$state = json_decode(file_get_contents($stateFilename), true);


var_export($config);
var_export($state);

$contents = '{"mapVersion":"0.18","baseVersion":"7","mapVersionString":"","results":[{"ID":53461,"Label":"Brisbane 110SPR","DEVICE_LOCATIONTYPE":"outside","THINGSPEAK_PRIMARY_ID":"1045001","THINGSPEAK_PRIMARY_ID_READ_KEY":"XDLPNJRGL7S1G4JA","THINGSPEAK_SECONDARY_ID":"1045002","THINGSPEAK_SECONDARY_ID_READ_KEY":"1RMKWP9MK34BJ554","Lat":37.6834,"Lon":-122.406137,"PM2_5Value":"23.46","LastSeen":1599234792,"Type":"PMS5003+PMS5003+BME280","Hidden":"false","DEVICE_BRIGHTNESS":"15","DEVICE_HARDWAREDISCOVERED":"2.0+BME280+PMSX003-B+PMSX003-A","DEVICE_FIRMWAREVERSION":"6.01","Version":"6.01","LastUpdateCheck":1599233950,"Created":1587679197,"Uptime":"306961","RSSI":"-59","Adc":"0.01","p_0_3_um":"1987.0","p_0_5_um":"622.65","p_1_0_um":"202.23","p_2_5_um":"17.58","p_5_0_um":"4.53","p_10_0_um":"0.63","pm1_0_cf_1":"10.04","pm2_5_cf_1":"23.46","pm10_0_cf_1":"26.14","pm1_0_atm":"10.04","pm2_5_atm":"23.46","pm10_0_atm":"26.14","isOwner":0,"humidity":"60","temp_f":"66","pressure":"1011.85","AGE":0,"Stats":"{\"v\":23.46,\"v1\":20.88,\"v2\":19.42,\"v3\":18.37,\"v4\":17.91,\"v5\":17.3,\"v6\":5.56,\"pm\":23.46,\"lastModified\":1599234792374,\"timeSinceModified\":120041}"},{"ID":53462,"ParentID":53461,"Label":"Brisbane 110SPR B","THINGSPEAK_PRIMARY_ID":"1045003","THINGSPEAK_PRIMARY_ID_READ_KEY":"G9N0VEPCG26C3IE5","THINGSPEAK_SECONDARY_ID":"1045004","THINGSPEAK_SECONDARY_ID_READ_KEY":"6X3MTISL8Y09XSEU","Lat":37.6834,"Lon":-122.406137,"PM2_5Value":"20.32","LastSeen":1599234792,"Hidden":"false","Created":1587679197,"p_0_3_um":"2087.45","p_0_5_um":"623.22","p_1_0_um":"151.87","p_2_5_um":"14.17","p_5_0_um":"5.7","p_10_0_um":"1.53","pm1_0_cf_1":"11.3","pm2_5_cf_1":"20.32","pm10_0_cf_1":"23.97","pm1_0_atm":"11.3","pm2_5_atm":"20.32","pm10_0_atm":"23.97","isOwner":0,"AGE":0,"Stats":"{\"v\":20.32,\"v1\":21.01,\"v2\":19.69,\"v3\":18.54,\"v4\":18.01,\"v5\":17.59,\"v6\":5.7,\"pm\":20.32,\"lastModified\":1599234792375,\"timeSinceModified\":120041}"}]}';

if ($useLiveData) {
  $contents = file_get_contents($config['sensor-url']);
}

$data = json_decode($contents, true);

$firstSensor = $data['results'][0];

$label = $firstSensor['Label'];
$aqi = aqiFromPM($firstSensor['PM2_5Value']);

print "AQI at $label is $aqi\n";

$state['aqi'] = $aqi;
$state['sensor-read-timestamp'] = time();

$sound = false;

if (($aqi > $config['thresholds']['hi']) && ($state['last-notification'] != 'hi')) {
  $sound = 'reached-hi.mp3';
  $state['last-notification'] = 'hi';
}

if (($aqi < $config['thresholds']['low']) && ($state['last-notification'] != 'low')) {
  $sound = 'returned-to-low.mp3';
  $state['last-notification'] = 'low';
}

file_put_contents($stateFilename, json_encode($state));

if ($sound) {
  passthru("mpg123 $configDir/$sound");
}

function aqiFromPM($pm)
{
  if ($pm < 0) {
    return $pm;
  }
  if ($pm > 1000) {
    return null;
  }

  /*
   * Good                               0 - 50           0 -  15       0.0 –  12.0
   * Moderate                          51 - 100       > 15 -  40      12.1 –  35.4
   * Unhealthy for Sensitive Groups   101 – 150       > 40 –  65      35.5 –  55.4
   * Unhealthy                        151 – 200       > 65 – 150      55.5 – 150.4
   * Very Unhealthy                   201 – 300       >150 – 250     150.5 – 250.4
   * Hazardous                        301 – 400       >250 – 350     250.5 – 350.4
   * Hazardous                        401 – 500       >350 – 500     350.5 – 500
   */

  if ($pm > 350.5) {
    return calcAQI($pm, 500, 401, 500, 350.5);
  } else if ($pm > 250.5) {
    return calcAQI($pm, 400, 301, 350.4, 250.5);
  } else if ($pm > 150.5) {
    return calcAQI($pm, 300, 201, 250.4, 150.5);
  } else if ($pm > 55.5) {
    return calcAQI($pm, 200, 151, 150.4, 55.5);
  } else if ($pm > 35.5) {
    return calcAQI($pm, 150, 101, 55.4, 35.5);
  } else if ($pm > 12.1) {
    return calcAQI($pm, 100, 51, 35.4, 12.1);
  } else if ($pm >= 0) {
    return calcAQI($pm, 50, 0, 12, 0);
  }

  return null;
}

function calcAQI($Cp, $Ih, $Il, $BPh, $BPl)
{
  $a = ($Ih - $Il);
  $b = ($BPh - $BPl);
  $c = ($Cp - $BPl);
  return round((($a/$b) * $c) + $Il);
}
