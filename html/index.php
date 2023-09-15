<?php

require "../vendor/autoload.php";

$sums = (new \Slavytuch\WorkTimeCheck\Loader())->calculate();

?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Рабочее время</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>
<body>
<div class="container mt-3">
    <div class="row align-items-start">
        <div class="col">
            <h2>Не нашёл записей</h2>
            <?php
            foreach ($sums['missing'] as $date => $entryList) {
                echo '<div class="my-3 fw-bold">' . $date . '</div>';

                foreach ($entryList as $message => $time) {
                    if ($time <= 3) {
                        continue;
                    }
                    echo sprintf('%s - %d %s<br>', $message, $time, \Slavytuch\WorkTimeCheck\Util::numOfMinutes($time));
                }
            }
            ?>
        </div>
        <div class="col">
            <h2>Разница во времени</h2>
            <?php
            foreach ($sums['diff'] as $date => $entryList) {
                echo '<div class="my-3 fw-bold">' . $date . '</div>';

                foreach ($entryList as $message => $time) {
                    if ($time <= 3) {
                        continue;
                    }
                    echo sprintf('%s - %d %s<br>', $message, $time, \Slavytuch\WorkTimeCheck\Util::numOfMinutes($time));
                }
            }
            ?>
        </div>
    </div>
</div>

</body>
</html>