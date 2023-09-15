<?php

namespace Slavytuch\WorkTimeCheck;

use BugrovWeb\YandexTracker\Api\Tracker;
use BugrovWeb\YandexTracker\Exceptions\TrackerBadMethodException;
use BugrovWeb\YandexTracker\Exceptions\TrackerBadParamsException;
use BugrovWeb\YandexTracker\Exceptions\TrackerBadResponseException;
use DateInterval;
use DateTime;
use Exception;
use Slavytuch\WorkTimeCheck\DTO\MyEntryDTO;
use Slavytuch\WorkTimeCheck\DTO\TrackerEntryDTO;

class Loader
{
    private Tracker $api;

    public function __construct()
    {
        $dotenv = \Dotenv\Dotenv::createImmutable('../');
        $dotenv->load();


        $token = $_ENV['TRACKER_TOKEN'];
        $orgId = $_ENV['TRACKER_ORGANIZATION_ID'];

        $this->api = new Tracker($token, $orgId);
    }

    /**
     * @param DateTime $from
     * @return Array<TrackerEntryDTO>
     * @throws TrackerBadMethodException
     * @throws TrackerBadParamsException
     * @throws TrackerBadResponseException
     */
    protected function getTrackerEntries(\DateTime $from): array
    {
        $me = $this->api->user()->getInfo()->send()->getResponse();
        $dateEntries = $this->api->worklog()->get()->createdBy($me['login'])->createdAt([
            'from' => $from->format('Y-m-d'),
        ])->send()->getResponse();
        $trackerEntries = [];
        foreach ($dateEntries as $entry) {
            $trackerEntries[] = new TrackerEntryDTO(
                task: $entry['issue']['key'],
                time: new DateInterval(preg_replace('/\.[0-9]+/', '', $entry['duration']))
            );
        }

        return $trackerEntries;
    }

    /**
     * @param DateTime $from
     * @return Array<MyEntryDTO>
     * @throws Exception
     */
    protected function getMyEntries(\DateTime $from)
    {
        $myFile = fopen('рабочее время.txt', 'r');

        if (!$myFile) {
            throw new Exception('Не могу открыть файл');
        }

        $myEntries = [];
        $date = $start = $from->format('d.m');

        $startFound = false;
        while (!feof($myFile)) {
            $row = fgets($myFile);
            if (!$row || $row === PHP_EOL) {
                continue;
            }

            if ($row === $start . PHP_EOL) {
                $date = $start;
                $startFound = true;
                continue;
            }

            if (!$startFound) {
                continue;
            }

            $parts = explode(' - ', $row);
            if (count($parts) < 3) {
                $date = $parts[0];
                continue;
            }

            [$timeStart, $timeEnd, $message] = $parts;

            $message = str_replace(PHP_EOL, '', $message);

            if ($message === 'нераспред') {
                $message = 'INT-718';
            } elseif ($message === 'обед') {
                continue;
            }

            $myEntries[] = new MyEntryDTO(
                message: $message,
                time: (new DateTime($timeStart))->diff(new DateTime($timeEnd)),
                date: $date
            );
        }

        return $myEntries;
    }

    public function calculate(): array
    {
        $from = \Carbon\Carbon::now()->subDays(4)->startOfDay();

        $myEntries = $this->getMyEntries($from);
        $trackerEntries = $this->getTrackerEntries($from);

        $sums = ['diff' => [], 'missing' => []];
        foreach ($myEntries as $myEntry) {
            $entryFound = false;
            foreach ($trackerEntries as $key => $trackerEntry) {
                if ($trackerEntry->task === $myEntry->message) {
                    $entryFound = true;
                    unset($trackerEntries[$key]);

                    $difference = $this->minutes($trackerEntry->time) - $this->minutes($myEntry->time);

                    if (abs($difference) <= 3) {
                        break;
                    }

                    if (!isset($sums['diff'][$myEntry->date][$myEntry->message])) {
                        $sums['diff'][$myEntry->date][$myEntry->message] = 0;
                    }

                    $sums['diff'][$myEntry->date][$myEntry->message] += $difference;

                    break;
                }
            }

            if (!$entryFound && $this->minutes($myEntry->time) > 3) {
                if (!isset($sums['missing'][$myEntry->date][$myEntry->message])) {
                    $sums['missing'][$myEntry->date][$myEntry->message] = 0;
                }
                $sums['missing'][$myEntry->date][$myEntry->message] += $this->minutes($myEntry->time);
            }
        }

        return $sums;
    }

    protected function minutes(DateInterval $interval)
    {
        return $interval->i + 60 * $interval->h;
    }
}