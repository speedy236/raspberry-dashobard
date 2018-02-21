<?php

//shell_exec('/usr/local/bin/gpio mode 4 out');
//$leftOn = 0;

//shell_exec('/usr/local/bin/gpio write 4 1');
//shell_exec('/usr/local/bin/gpio write 4 0');

// Podle BCM!
// Pořadí: tachometr, palivoměr, otáčkoměr, teplota oleje
// 0 - tachometr
// 1 - palivoměr
// 2 - otáčkoměr
// 3 - teplota oleje
$motorPins = [[12, 16, 20, 21], [1, 7, 8, 25], [27, 22, 4, 17], [26, 19, 13, 6]];

// Stejné pořadí jako u pinů
$angles = [0, 0, 0, 0];

$queue = array();

//Přidání do fronty pro paralelní spuštění všech motorů
function addToQ($motor, $rpm, $angle){
    global $motorPins, $queue;
    array_push($queue, 'python /home/pi/helper.py ' . $motorPins[$motor][0] . ' ' . $motorPins[$motor][1] . ' ' . $motorPins[$motor][2] . ' ' . $motorPins[$motor][3] . ' ' . $rpm . ' ' . $angle . ' &');
}

//Spuštění fronty příkazů
function execQ(){
    global $queue;
    $command = '';

    foreach($queue as $value){
        $command = $command . ' ' . $value;
    }

    $queue = array();

    $command = $command . ' wait';
    exec($command);

}

/*
  ______          _
 |  ____|        | |
 | |__ _   _  ___| |
 |  __| | | |/ _ \ |
 | |  | |_| |  __/ |
 |_|   \__,_|\___|_|
*/
// --------------------------------------------------
// Syntaxe: fuelRAngle(fuelAngle(fuelPercent(Kapacita, Aktualni)));

/**
 * @param int $capacity Celková kapacita nádrže
 * @param float $current Aktuální počet litrů v nádrži
 * @return float|int Procenta zbývajícího paliva
 */
function fuelPercent($capacity, $current){
    if($capacity != 0){
        return ($current * 100) / $capacity;
    }else{
        return 0;
    }
}

/**
 * @param float $percent Procenta nádrže
 * @return float|int Rotace ve stupních od nuly
 */
function fuelAngle($percent){
    return (9 / 10) * $percent;
}

/**
 * @param float $newAngle Absolutní úhel
 * @return float Relativní úhel
 */
function fuelRAngle($newAngle){
    global $angles;
    $result = $newAngle - $angles[1];
    $angles[1] = ceil($newAngle);
    return ceil($result);
}

// --------------------------------------------------

function rpmPercent($maxRPM, $currentRPM){
    if($maxRPM != 0){
        return (100 / $maxRPM) * $currentRPM;
    }else{
        return 0;
    }
}

function rpmAngle($percent){
    return (27 / 10) * $percent;
}

function rpmRAngle($newAngle){
    global $angles;
    $result = $newAngle - $angles[2];
    $angles[2] = ceil($newAngle);
    return ceil($result);
}


$cs = 0;

$pos = 0;

$t = 0;

while(true){
    $json = file_get_contents('http://192.168.0.101:25555/api/ets2/telemetry/?' . rand());
    $data = json_decode($json, true);

    if($data['truck']['engineOn']){
        addToQ(1, 10, fuelRAngle(fuelAngle(fuelPercent($data['truck']['fuelCapacity'], $data['truck']['fuel']))));
        addToQ(2, 18, rpmRAngle(rpmAngle(rpmPercent($data['truck']['engineRpmMax'], $data['truck']['engineRpm']))));
        //execQ();
    }else{
        if($angles[1] != 0){
            $rotation = 0 - $angles[1];
            $angles[1] = 0;
            addToQ(1, 10, $rotation);
            //execQ();
        }

        if($angles[2] != 0){
            $rotation = 0 - $angles[2];
            $angles[2] = 0;
            addToQ(2, 10, $rotation);
            //execQ();
        }
    }
    execQ();

    //echo 'Rychlost: ' . round($data['truck']['speed']) . ' km/h' . PHP_EOL;

    //echo 'Speed: ' . round($data['truck']['speed']) . 'km/h' . ' RPM: ' . $data['truck']['engineRpm'] . ' Palivo: ' . $data['truck']['fuel'] . ' / ' . $data['truck']['fuelCapacity'] . ' l' . PHP_EOL;
    /*
    if($data['truck']['blinkerLeftOn']){
        if($leftOn == 0){
            shell_exec('/usr/local/bin/gpio write 4 1');
            $leftOn = 1;
        }
    }else{
        if($leftOn == 1){
            shell_exec('/usr/local/bin/gpio write 4 0');
            $leftOn = 0;
        }
    }
    *//*

        $speed = $data['truck']['speed'] - 20;

        if($speed > 0){
            $vys = $speed - $cs;
            $posun = round($vys * 1.5625);
            $pos = $pos + $posun;
            if($posun != 0){
                shell_exec('python /home/pi/kokot.py ' . $posun  . ' 3');
            }
            echo "Takze, predchozi rychlost: $cs, aktualni: $speed. Posun: $posun, pozce: " . $pos / 1.5625 . PHP_EOL;
            $cs = $speed;
            usleep(10000);
        }


    $t++;
    */
}


?>