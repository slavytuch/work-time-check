<?php

require "../vendor/autoload.php";

$result = (new \Slavytuch\WorkTimeCheck\Loader())->calculate();

?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Рабочее время</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>
<body>
<div class="container mt-5">
    <div class="row align-items-start">
        <div class="col">
            <h2>Не нашёл записей в файле</h2>
        </div>
        <div class="col">
            <h2>Не нашёл записей в трекере</h2>
        </div>
        <div class="col">
            <h2>Разница во времени</h2>
        </div>
    </div>
    <?php
    foreach ($result as $date => $info):
        if (empty($info['missingInFile']) && empty($info['missingInTracker']) && empty($info['timeDifference'])) {
            continue;
        }
        ?>
        <div class="row align-items-start">

            <div class="my-3 fw-bold"><?= $date ?></div>
            <div class="col">
                <? foreach ($info['missingInFile'] as $message => $missingInFile): ?>
                    <?php
                    echo sprintf(
                        '%s - %d %s<br>',
                        $message,
                        $missingInFile,
                        \Slavytuch\WorkTimeCheck\Util::numOfMinutes($missingInFile)
                    ); ?>
                <?endforeach; ?>
            </div>
            <div class="col">

                <? foreach ($info['missingInTracker'] as $message => $missingInTracker): ?>
                    <?php
                    echo sprintf(
                        '%s - %d %s<br>',
                        $message,
                        $missingInTracker,
                        \Slavytuch\WorkTimeCheck\Util::numOfMinutes($missingInTracker)
                    ); ?>
                <?endforeach; ?>
            </div>
            <div class="col">

                <? foreach ($info['timeDifference'] as $message => $timeDifference): ?>
                    <?php
                    echo sprintf(
                        '%s - %d %s<br>',
                        $message,
                        $timeDifference,
                        \Slavytuch\WorkTimeCheck\Util::numOfMinutes($timeDifference)
                    ); ?>
                <?endforeach; ?>
            </div>
        </div>
    <?
    endforeach; ?>
</div>

</body>
</html>